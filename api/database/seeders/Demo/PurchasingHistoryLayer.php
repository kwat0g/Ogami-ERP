<?php

declare(strict_types=1);

namespace Database\Seeders\Demo;

use App\Common\DemoData\DemoSeedContext;
use App\Common\DemoData\DemoSeeder;
use App\Common\Services\ApprovalService;
use App\Common\Services\BusinessPolicyService;
use App\Modules\Accounting\Models\Vendor;
use App\Modules\Auth\Models\User;
use App\Modules\B2B\Models\SupplierPortalUser;
use App\Modules\B2B\Services\PortalAccessService;
use App\Modules\B2B\Services\PortalPasswordResetService;
use App\Modules\HR\Models\Department;
use App\Modules\Inventory\Models\Item;
use App\Modules\Purchasing\Enums\PurchaseOrderResponseType;
use App\Modules\Purchasing\Enums\PurchaseOrderStatus;
use App\Modules\Purchasing\Enums\PurchaseRequestPriority;
use App\Modules\Purchasing\Enums\PurchaseRequestSourcingMethod;
use App\Modules\Purchasing\Enums\PurchaseRequestStatus;
use App\Modules\Purchasing\Enums\RfqStatus;
use App\Modules\Purchasing\Enums\SupplierQuoteResponseStatus;
use App\Modules\Purchasing\Enums\SupplierQuoteStatus;
use App\Modules\Purchasing\Models\PurchaseOrder;
use App\Modules\Purchasing\Models\PurchaseOrderResponse;
use App\Modules\Purchasing\Models\PurchaseRequest;
use App\Modules\Purchasing\Models\RequestForQuote;
use App\Modules\Purchasing\Models\RfqDocument;
use App\Modules\Purchasing\Models\SupplierQuote;
use App\Modules\Purchasing\Services\PurchaseOrderService;
use App\Modules\Purchasing\Services\PurchaseRequestService;
use App\Modules\Purchasing\Services\RequestForQuoteService;
use App\Modules\Purchasing\Services\RfqAwardService;
use App\Modules\Purchasing\Services\SupplierQuoteService;
use App\Modules\Purchasing\Services\SupplierResponseService;
use App\Modules\Purchasing\Services\VendorSourcingService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/** Seeds the current PR, RFQ, quote, PO and supplier-response workflows. */
final class PurchasingHistoryLayer extends DemoSeeder
{
    private const MARKER = 'DEMO-PURCHASING:';

    /** @var list<array{name:string,email_prefix:string}> */
    private const SUPPLIER_CONTACTS = [
        ['name' => 'Althea Mendoza', 'email_prefix' => 'althea.mendoza'],
        ['name' => 'Nico Villanueva', 'email_prefix' => 'nico.villanueva'],
        ['name' => 'Jessa Bautista', 'email_prefix' => 'jessa.bautista'],
        ['name' => 'Marco Reyes', 'email_prefix' => 'marco.reyes'],
    ];

    public function __construct(
        private readonly PurchaseRequestService $purchaseRequests,
        private readonly PurchaseOrderService $purchaseOrders,
        private readonly VendorSourcingService $sourcing,
        private readonly RequestForQuoteService $rfqs,
        private readonly SupplierQuoteService $quotes,
        private readonly RfqAwardService $awards,
        private readonly SupplierResponseService $supplierResponses,
        private readonly ApprovalService $approvals,
        private readonly BusinessPolicyService $businessPolicy,
        private readonly PortalAccessService $portalAccess,
        private readonly PortalPasswordResetService $passwordResets,
    ) {}

    public function key(): string
    {
        return 'purchasing-history';
    }

    /** @return list<string> */
    public function modules(): array
    {
        return ['Purchasing', 'Inventory', 'Accounting', 'B2B', 'Auth', 'Dashboard'];
    }

    /** @return list<string> */
    public function dependencies(): array
    {
        // Register spare items before supplier listings so every generated
        // opening-stock requisition has a current sourcing candidate.
        return ['maintenance-spare-parts', 'supplier-listings', 'opening-stock-balances', 'workforce-identities'];
    }

    protected function seedDemoData(DemoSeedContext $context): void
    {
        $asOf = $context->options->asOf->setTimezone('Asia/Manila')->startOfDay();
        $historyStart = $asOf->subMonthsNoOverflow(18)->startOfDay();
        $actors = [
            'admin' => $this->account('system.admin@accounts.ogami-demo.test', 'purchasing.pr.create'),
            'requester' => $this->account('department.head@accounts.ogami-demo.test', 'purchasing.pr.create'),
            'buyer' => $this->account('purchasing.officer@accounts.ogami-demo.test', 'purchasing.po.create'),
            'finance' => $this->account('finance.officer@accounts.ogami-demo.test', 'purchasing.pr.approve'),
            'vp' => $this->account('vice.president@accounts.ogami-demo.test', 'purchasing.pr.approve'),
        ];
        $item = Item::query()->where('code', 'RM-001')->where('is_active', true)->first();
        if (! $item instanceof Item) {
            throw new RuntimeException('Purchasing history requires the active RM-001 resin catalog item.');
        }
        $vendor = $this->preferredPricedVendor((int) $item->id);
        $department = Department::query()->where('code', 'WH')->first();
        if (! $department) {
            throw new RuntimeException('Purchasing history requires the current WH owning department.');
        }

        $this->processOpeningRequisitions($context, $historyStart, $actors, (int) $department->id);
        $this->seedPurchaseRequestStates($context, $asOf, $actors, $item, $vendor);
        $this->seedPurchaseOrderStates($context, $asOf, $actors, $item, $vendor);
        $this->seedRfqStates($context, $asOf, $actors, $item, $vendor);
    }

    /**
     * Complete the low-stock PRs created by the opening-stock after-commit listener.
     * The service rejects auto PR submission until a user assigns an owning
     * department; the admin's normal draft-edit action supplies Warehouse.
     *
     * @param  array<string, User>  $actors
     */
    private function processOpeningRequisitions(
        DemoSeedContext $context,
        CarbonImmutable $historyStart,
        array $actors,
        int $warehouseDepartmentId,
    ): void {
        $completionLimit = match ($context->options->scale) {
            'small' => 1,
            'full' => 5,
            default => 3,
        };
        $completed = 0;
        PurchaseRequest::query()
            ->where('is_auto_generated', true)
            ->whereDate('date', $historyStart->toDateString())
            ->where('reason', 'like', 'Auto-generated:%')
            ->with('items.item')
            ->orderBy('id')
            ->chunkById(50, function (Collection $requests) use ($context, $historyStart, $actors, $warehouseDepartmentId, $completionLimit, &$completed): void {
                foreach ($requests as $pr) {
                    if (! $pr instanceof PurchaseRequest) {
                        continue;
                    }
                    $context->withoutNotifications(function () use ($context, $pr, $historyStart, $actors, $warehouseDepartmentId, $completionLimit, &$completed): void {
                        $completeWorkflow = $completed < $completionLimit;
                        $this->advanceOpeningRequisition($context, $pr, $historyStart, $actors, $warehouseDepartmentId, $completeWorkflow);
                        if ($completeWorkflow) {
                            $completed++;
                        }
                    });
                }
            });
    }

    /** @param array<string, User> $actors */
    private function advanceOpeningRequisition(
        DemoSeedContext $context,
        PurchaseRequest $pr,
        CarbonImmutable $historyStart,
        array $actors,
        int $warehouseDepartmentId,
        bool $completeWorkflow,
    ): void {
        $buyer = $actors['buyer'];
        $admin = $actors['admin'];
        $created = $pr->created_at?->toImmutable() ?? $historyStart;
        $stepTime = $created->setTimezone('Asia/Manila')->addDay();

        if ($pr->status === PurchaseRequestStatus::Draft) {
            $context->at($stepTime, fn () => $context->asUser($admin, fn () => $this->purchaseRequests->update(
                $pr->fresh(),
                ['department_id' => $warehouseDepartmentId],
                $admin,
            )));
            $pr->refresh();
        }

        if ($pr->status === PurchaseRequestStatus::Draft && $pr->department_id === null) {
            throw new RuntimeException("Opening replenishment {$pr->pr_number} needs an owning department; normal PR update did not assign Warehouse.");
        }

        if ($pr->status === PurchaseRequestStatus::Draft && $pr->sourcing_method === null) {
            $context->at($stepTime->addMinutes(30), fn () => $context->asUser($admin, fn () => $this->purchaseRequests->setSourcingMethod(
                $pr->fresh(),
                PurchaseRequestSourcingMethod::DirectPo,
                $admin,
            )));
            $pr->refresh();
        }

        if (! $completeWorkflow) {
            if ($pr->status !== PurchaseRequestStatus::Draft
                || $pr->department_id === null
                || $pr->sourcing_method === null) {
                throw new RuntimeException("Opening replenishment {$pr->pr_number} did not remain a valid editable draft.");
            }

            return;
        }

        if ($pr->status === PurchaseRequestStatus::Draft) {
            $context->at($stepTime->addHours(1), fn () => $context->asUser($admin, fn () => $this->purchaseRequests->submit($pr->fresh(), $admin)));
            $pr->refresh();
        }
        if ($pr->status === PurchaseRequestStatus::Pending) {
            $this->approvePurchaseRequest($context, $pr, $actors, $stepTime->addDays(2));
            $pr->refresh();
        }
        if ($pr->status === PurchaseRequestStatus::Approved) {
            $vendorMap = [];
            foreach ($pr->items as $line) {
                if ($line->item_id === null) {
                    throw new RuntimeException("Opening replenishment {$pr->pr_number} contains an unlinked line and cannot convert to a PO.");
                }
                $candidate = $this->preferredPricedVendor((int) $line->item_id);
                $vendorMap[(int) $line->id] = (int) $candidate->id;
            }
            $context->at($stepTime->addDays(4), fn () => $context->asUser($buyer, fn () => $this->purchaseOrders->convertFromPr(
                $pr->fresh(),
                $vendorMap,
                $buyer,
                expectedDeliveryDate: $stepTime->addDays(14)->toDateString(),
            )));
            $pr->refresh();
        }
        if ($pr->status === PurchaseRequestStatus::Converted) {
            $orders = $pr->purchaseOrders()->whereNot('status', PurchaseOrderStatus::Cancelled->value)->orderBy('id')->get();
            foreach ($orders as $index => $po) {
                $poTime = $stepTime->addDays(5 + ($index * 3));
                $this->submitOrder($context, $po, $buyer, $poTime);
                $this->approvePurchaseOrder($context, $po, $actors, $poTime->addDay());
                $this->sendOrder($context, $po, $buyer, $poTime->addDays(2));
            }
        }
        if ($pr->status !== PurchaseRequestStatus::Converted
            || ! $pr->purchaseOrders()->where('status', PurchaseOrderStatus::Sent->value)->exists()) {
            throw new RuntimeException("Opening replenishment {$pr->pr_number} did not reach a converted PR with a live PO.");
        }
    }

    /** @param array<string, User> $actors */
    private function seedPurchaseRequestStates(
        DemoSeedContext $context,
        CarbonImmutable $asOf,
        array $actors,
        Item $item,
        Vendor $vendor,
    ): void {
        $requester = $actors['requester'];
        $finance = $actors['finance'];
        $price = $this->sourcingPrice((int) $item->id, (int) $vendor->id);
        $lead = max(1, (int) ($item->lead_time_days ?? 7));

        $draft = $this->ensureRequest($context, 'PR-DRAFT', $requester, $item, $vendor, '10', $price, $asOf->subDay(), PurchaseRequestSourcingMethod::DirectPo);
        $this->assertRequestState($draft, PurchaseRequestStatus::Draft, 'PR-DRAFT');

        $pendingFinance = $this->ensureRequest($context, 'PR-PENDING-FINANCE', $requester, $item, $vendor, '10', $price, $asOf->subDays(3), PurchaseRequestSourcingMethod::DirectPo);
        $this->submitRequest($context, $pendingFinance, $requester, $asOf->subDays(3)->addHours(1));
        $this->assertRequestState($pendingFinance, PurchaseRequestStatus::Pending, 'PR-PENDING-FINANCE');

        $vpThreshold = $this->workflowThreshold('purchase_request', 'vice_president');
        $highQuantity = $this->quantityOver($vpThreshold, $price);
        $pendingVp = $this->ensureRequest($context, 'PR-PENDING-VP', $requester, $item, $vendor, $highQuantity, $price, $asOf->subDays(5), PurchaseRequestSourcingMethod::DirectPo);
        $this->submitRequest($context, $pendingVp, $requester, $asOf->subDays(5)->addHours(1));
        $this->approvePurchaseRequest($context, $pendingVp, $actors, $asOf->subDays(4), stopAfterRole: 'finance_officer');
        $this->assertRequestState($pendingVp, PurchaseRequestStatus::Pending, 'PR-PENDING-VP');

        $rejected = $this->ensureRequest($context, 'PR-REJECTED', $requester, $item, $vendor, '12', $price, $asOf->subMonthsNoOverflow(8), PurchaseRequestSourcingMethod::DirectPo);
        $this->submitRequest($context, $rejected, $requester, $asOf->subMonthsNoOverflow(8)->addHours(1));
        if ($rejected->fresh()->status === PurchaseRequestStatus::Pending) {
            $context->at($asOf->subMonthsNoOverflow(8)->addDays(2), fn () => $context->asUser($finance, fn () => $this->purchaseRequests->reject(
                $rejected->fresh(),
                $finance,
                'Historical budget review: request revised in the next planning cycle.',
            )));
        }
        $this->assertRequestState($rejected, PurchaseRequestStatus::Rejected, 'PR-REJECTED');

        $cancelled = $this->ensureRequest($context, 'PR-CANCELLED', $requester, $item, $vendor, '8', $price, $asOf->subMonthsNoOverflow(12), PurchaseRequestSourcingMethod::DirectPo);
        if ($cancelled->status === PurchaseRequestStatus::Draft) {
            $context->at($asOf->subMonthsNoOverflow(12)->addHours(2), fn () => $context->asUser($requester, fn () => $this->purchaseRequests->cancel($cancelled->fresh(), $requester)));
        }
        $this->assertRequestState($cancelled, PurchaseRequestStatus::Cancelled, 'PR-CANCELLED');
    }

    /** @param array<string, User> $actors */
    private function seedPurchaseOrderStates(
        DemoSeedContext $context,
        CarbonImmutable $asOf,
        array $actors,
        Item $item,
        Vendor $vendor,
    ): void {
        $buyer = $actors['buyer'];
        $finance = $actors['finance'];
        $vpThreshold = (string) $this->businessPolicy->purchaseOrderVpThreshold();
        $lowPrice = $this->sourcingPrice((int) $item->id, (int) $vendor->id);
        $lowQty = '10';
        $highQty = $this->quantityOver($vpThreshold, $lowPrice);

        $draft = $this->ensureOrderFromRequest($context, 'PO-DRAFT', $actors, $item, $vendor, $lowQty, $asOf->subDays(2));
        $this->assertOrderState($draft, PurchaseOrderStatus::Draft, 'PO-DRAFT');

        $pendingFinance = $this->ensureOrderFromRequest($context, 'PO-PENDING-FINANCE', $actors, $item, $vendor, $lowQty, $asOf->subDays(3));
        $this->submitOrder($context, $pendingFinance, $buyer, $asOf->subDays(3)->addHours(1));
        $this->assertOrderState($pendingFinance, PurchaseOrderStatus::PendingApproval, 'PO-PENDING-FINANCE');

        $pendingVp = $this->ensureOrderFromRequest($context, 'PO-PENDING-VP', $actors, $item, $vendor, $highQty, $asOf->subDays(4));
        $this->submitOrder($context, $pendingVp, $buyer, $asOf->subDays(4)->addHours(1));
        $this->approvePurchaseOrder($context, $pendingVp, $actors, $asOf->subDays(3), stopAfterRole: 'finance_officer');
        $this->assertOrderState($pendingVp, PurchaseOrderStatus::PendingApproval, 'PO-PENDING-VP');

        $approved = $this->ensureOrderFromRequest($context, 'PO-APPROVED', $actors, $item, $vendor, $lowQty, $asOf->subDays(14));
        $this->submitOrder($context, $approved, $buyer, $asOf->subDays(14)->addHours(1));
        $this->approvePurchaseOrder($context, $approved, $actors, $asOf->subDays(13));
        $this->assertOrderState($approved, PurchaseOrderStatus::Approved, 'PO-APPROVED');

        // Keep one approved PO parked at the manual-transmission step. The
        // approval listener correctly auto-publishes to active supplier portal
        // users, so invite the portal contact only after this scenario is built.
        $portalUser = $this->ensureSupplierPortalUser($context, $vendor, $actors['admin'], $asOf);

        $sent = $this->ensureOrderFromRequest($context, 'PO-SENT', $actors, $item, $vendor, $lowQty, $asOf->subDays(8));
        $this->submitOrder($context, $sent, $buyer, $asOf->subDays(8)->addHours(1));
        $this->approvePurchaseOrder($context, $sent, $actors, $asOf->subDays(7));
        $this->sendOrder($context, $sent, $buyer, $asOf->subDays(6));
        $this->assertOrderState($sent, PurchaseOrderStatus::Sent, 'PO-SENT');

        $acknowledged = $this->ensureOrderFromRequest($context, 'PO-ACKNOWLEDGED', $actors, $item, $vendor, $lowQty, $asOf->subDays(10));
        $this->submitOrder($context, $acknowledged, $buyer, $asOf->subDays(10)->addHours(1));
        $this->approvePurchaseOrder($context, $acknowledged, $actors, $asOf->subDays(9));
        $this->sendOrder($context, $acknowledged, $buyer, $asOf->subDays(8));
        $this->respondToOrder($context, $acknowledged, $vendor, $portalUser, PurchaseOrderResponseType::Accept, $asOf->subDays(7));
        $this->assertOrderState($acknowledged, PurchaseOrderStatus::Acknowledged, 'PO-ACKNOWLEDGED');

        $proposed = $this->ensureOrderFromRequest($context, 'PO-SUPPLIER-PROPOSED', $actors, $item, $vendor, $lowQty, $asOf->subDays(4));
        $this->submitOrder($context, $proposed, $buyer, $asOf->subDays(4)->addHours(1));
        $this->approvePurchaseOrder($context, $proposed, $actors, $asOf->subDays(3));
        $this->sendOrder($context, $proposed, $buyer, $asOf->subDays(2));
        $this->respondToOrder($context, $proposed, $vendor, $portalUser, PurchaseOrderResponseType::Propose, $asOf->subDay());
        $this->assertOrderState($proposed, PurchaseOrderStatus::SupplierProposed, 'PO-SUPPLIER-PROPOSED');

        $declined = $this->ensureOrderFromRequest($context, 'PO-SUPPLIER-DECLINED', $actors, $item, $vendor, $lowQty, $asOf->subDays(5));
        $this->submitOrder($context, $declined, $buyer, $asOf->subDays(5)->addHours(1));
        $this->approvePurchaseOrder($context, $declined, $actors, $asOf->subDays(4));
        $this->sendOrder($context, $declined, $buyer, $asOf->subDays(3));
        $this->respondToOrder($context, $declined, $vendor, $portalUser, PurchaseOrderResponseType::Decline, $asOf->subDays(2));
        $this->assertOrderState($declined, PurchaseOrderStatus::SupplierDeclined, 'PO-SUPPLIER-DECLINED');

        $cancelled = $this->ensureOrderFromRequest($context, 'PO-CANCELLED', $actors, $item, $vendor, $lowQty, $asOf->subMonthsNoOverflow(10));
        if ($cancelled->status === PurchaseOrderStatus::Draft) {
            $context->at($asOf->subMonthsNoOverflow(10)->addDays(1), fn () => $context->asUser($buyer, fn () => $this->purchaseOrders->cancel(
                $cancelled->fresh(),
                'Supplier capacity was reassigned to an urgent customer program.',
                $buyer,
            )));
        }
        $this->assertOrderState($cancelled, PurchaseOrderStatus::Cancelled, 'PO-CANCELLED');
    }

    /** @param array<string, User> $actors */
    private function seedRfqStates(
        DemoSeedContext $context,
        CarbonImmutable $asOf,
        array $actors,
        Item $item,
        Vendor $vendor,
    ): void {
        $buyer = $actors['buyer'];
        $anotherVendor = $this->alternatePricedVendor((int) $item->id, (int) $vendor->id);

        $this->ensureRfq($context, 'RFQ-DRAFT', $asOf->subDays(3), $actors, $item, [[$vendor, null]], publish: false);
        $open = $this->ensureRfq($context, 'RFQ-OPEN', $asOf->subDay(), $actors, $item, [[$vendor, null]], publish: true);
        if ($open->status === RfqStatus::Open) {
            $this->ensureQuote($context, 'RFQ-OPEN:DRAFT', $open, $vendor, $buyer, $item, submit: false, at: $asOf);
        }
        $cancelled = $this->ensureRfq($context, 'RFQ-CANCELLED', $asOf->subMonthsNoOverflow(12), $actors, $item, [[$vendor, null]], publish: false);
        if ($cancelled->status === RfqStatus::Draft) {
            $context->at($asOf->subMonthsNoOverflow(12)->addHours(3), fn () => $context->asUser($buyer, fn () => $this->rfqs->cancel(
                $cancelled->fresh(),
                'The customer schedule moved and the material requirement was withdrawn.',
                $buyer,
            )));
        }

        $closed = $this->ensureRfq($context, 'RFQ-CLOSED', $asOf->subMonthsNoOverflow(7), $actors, $item, [[$vendor, null]], publish: true);
        if ($closed->status === RfqStatus::Open) {
            $quote = $this->ensureQuote($context, 'RFQ-CLOSED', $closed, $vendor, $buyer, $item, submit: true, at: $asOf->subMonthsNoOverflow(7)->addDay());
            $context->at($asOf->subMonthsNoOverflow(7)->addDays(2), fn () => $context->asUser($buyer, fn () => $this->rfqs->closeNow($closed->fresh(), $buyer)));
            $closed->refresh();
            if ($quote->status !== SupplierQuoteStatus::Submitted) {
                throw new RuntimeException('The closed RFQ scenario did not retain its submitted supplier quotation.');
            }
        }

        $invitees = [[$vendor, null]];
        if ($anotherVendor instanceof Vendor) {
            $invitees[] = [$anotherVendor, 'Invited as a secondary source for delivered-cost comparison.'];
        }
        $awarded = $this->ensureRfq($context, 'RFQ-AWARDED', $asOf->subMonthsNoOverflow(4), $actors, $item, $invitees, publish: true);
        if ($awarded->status === RfqStatus::Open) {
            $winningQuote = $this->ensureQuote($context, 'RFQ-AWARDED:WINNER', $awarded, $vendor, $buyer, $item, submit: true, at: $asOf->subMonthsNoOverflow(4)->addDay());
            if ($anotherVendor instanceof Vendor) {
                $this->ensureQuote($context, 'RFQ-AWARDED:ALTERNATE', $awarded, $anotherVendor, $buyer, $item, submit: true, at: $asOf->subMonthsNoOverflow(4)->addDay(), priceFactor: '1.05');
            }
            $context->at($asOf->subMonthsNoOverflow(4)->addDays(2), fn () => $context->asUser($buyer, fn () => $this->rfqs->closeNow($awarded->fresh(), $buyer)));
            $awarded->refresh();
            $rfqLine = $awarded->items()->firstOrFail();
            $quoteLine = $winningQuote->items()->firstOrFail();
            $context->at($asOf->subMonthsNoOverflow(4)->addDays(3), fn () => $context->asUser($buyer, fn () => $this->awards->award(
                $awarded->fresh(),
                'Awarded to the lowest evaluated delivered cost meeting the required resin specification and delivery window.',
                [[
                    'request_for_quote_item_id' => (int) $rfqLine->id,
                    'supplier_quote_item_id' => (int) $quoteLine->id,
                ]],
                $buyer,
            )));
        }
        $this->assertRfqState($awarded, RfqStatus::Awarded, 'RFQ-AWARDED');
        $this->assertRfqState($closed, RfqStatus::Closed, 'RFQ-CLOSED');
        $this->assertRfqState($cancelled, RfqStatus::Cancelled, 'RFQ-CANCELLED');
    }

    private function alternatePricedVendor(int $itemId, int $excludedVendorId): ?Vendor
    {
        foreach ($this->sourcing->candidatesForItem($itemId) as $candidate) {
            $candidateId = (int) ($candidate['vendor_id'] ?? 0);
            $price = $candidate['price'] ?? null;
            if ($candidateId === $excludedVendorId || $price === null || bccomp((string) $price, '0', 4) <= 0) {
                continue;
            }

            $vendor = Vendor::query()->find($candidateId);
            if ($vendor instanceof Vendor && $vendor->isPurchasable()) {
                return $vendor;
            }
        }

        // The small scale qualifies only one supplier for RM-001. The full
        // scale supplier-listing layer supplies a second priced source, so this
        // becomes a comparative bid there without inventing a vendor or price.
        return null;
    }

    /**
     * @param  array<string, User>  $actors
     * @param  list<array{0:Vendor,1:?string}>  $invitees
     */
    private function ensureRfq(
        DemoSeedContext $context,
        string $code,
        CarbonImmutable $createdAt,
        array $actors,
        Item $item,
        array $invitees,
        bool $publish,
    ): RequestForQuote {
        $marker = self::MARKER.$code;
        $existing = RequestForQuote::query()->where('title', 'like', $marker.'%')->first();
        if ($existing instanceof RequestForQuote) {
            return $existing;
        }

        $request = $this->ensureRequest(
            $context,
            'RFQ-PR:'.$code,
            $actors['requester'],
            $item,
            $invitees[0][0],
            '40',
            $this->sourcingPrice((int) $item->id, (int) $invitees[0][0]->id),
            $createdAt,
            PurchaseRequestSourcingMethod::Rfq,
        );
        $this->submitRequest($context, $request, $actors['requester'], $createdAt->addHours(1));
        if ($request->fresh()->status !== PurchaseRequestStatus::Approved) {
            throw new RuntimeException("RFQ source request {$marker} did not pass the need-approval step.");
        }

        $invitations = array_map(static fn (array $invitee): array => [
            'vendor_id' => (int) $invitee[0]->id,
            'exception_reason' => $invitee[1],
        ], $invitees);
        $created = $context->at($createdAt->addHours(2), fn () => $context->asUser($actors['buyer'], fn () => $this->rfqs->createFromPurchaseRequest(
            $request->fresh(),
            [
                'title' => $marker.' | RM-001 resin supply comparison',
                'instructions' => 'Quote the specified resin grade, full requested quantity, delivery date, freight, and tax treatment.',
                'closes_at' => $createdAt->addDays(7)->format('Y-m-d H:i:s'),
                'invitations' => $invitations,
                'publish' => $publish,
            ],
            $actors['buyer'],
        )));

        return $created->fresh();
    }

    private function ensureQuote(
        DemoSeedContext $context,
        string $code,
        RequestForQuote $rfq,
        Vendor $vendor,
        User $buyer,
        Item $item,
        bool $submit,
        CarbonImmutable $at,
        string $priceFactor = '1.00',
    ): SupplierQuote {
        $marker = self::MARKER.'QUOTE:'.$code;
        $existing = SupplierQuote::query()->where('notes', $marker)->first();
        if ($existing instanceof SupplierQuote) {
            return $existing;
        }
        $rfqLine = $rfq->items()->firstOrFail();
        $basePrice = $this->sourcingPrice((int) $item->id, (int) $vendor->id);
        $unitPrice = bcmul($basePrice, $priceFactor, 4);
        if ($submit) {
            $this->attachQuotationPdf($context, $rfq, $vendor, $buyer, $marker, $at);
        }
        $context->at($at, fn () => $context->asUser($buyer, fn () => $this->quotes->save(
            $rfq->fresh(),
            (int) $vendor->id,
            [
                'vat_treatment' => 'exclusive',
                'freight_amount' => '0.00',
                'quote_valid_until' => $at->addDays(60)->toDateString(),
                'payment_terms' => 'Net 30',
                'notes' => $marker,
                'items' => [[
                    'request_for_quote_item_id' => (int) $rfqLine->id,
                    'response_status' => SupplierQuoteResponseStatus::Quoted->value,
                    'offered_quantity' => (string) $rfqLine->quantity,
                    'unit_price' => $unitPrice,
                    'lead_time_days' => max(1, (int) ($item->lead_time_days ?? 7)),
                ]],
            ],
            $submit,
            capturedBy: $buyer,
        )));

        return SupplierQuote::query()->where('notes', $marker)->firstOrFail();
    }

    private function attachQuotationPdf(
        DemoSeedContext $context,
        RequestForQuote $rfq,
        Vendor $vendor,
        User $buyer,
        string $marker,
        CarbonImmutable $at,
    ): void {
        $filename = Str::slug($marker).'-quotation.pdf';
        $existing = RfqDocument::query()
            ->where('request_for_quote_id', $rfq->id)
            ->where('vendor_id', $vendor->id)
            ->where('document_type', 'quotation_pdf')
            ->where('original_filename', $filename)
            ->first();
        if ($existing instanceof RfqDocument) {
            if (! Storage::disk('local')->exists($existing->file_path)) {
                throw new RuntimeException("Seed-owned quotation attachment {$filename} is missing; restore the demo snapshot before reseeding.");
            }

            return;
        }

        $temporaryPath = tempnam(sys_get_temp_dir(), 'ogami-demo-quote-');
        if ($temporaryPath === false) {
            throw new RuntimeException('Could not prepare the synthetic supplier quotation attachment.');
        }
        if (file_put_contents($temporaryPath, $this->placeholderQuotationPdf($vendor->name, $marker)) === false) {
            @unlink($temporaryPath);
            throw new RuntimeException('Could not write the synthetic supplier quotation attachment.');
        }

        $upload = new class($temporaryPath, $filename, 'application/pdf', UPLOAD_ERR_OK, true) extends UploadedFile
        {
            public function hashName($path = null): string
            {
                $name = 'demo-'.substr(hash('sha256', $this->getClientOriginalName()), 0, 32).'.pdf';

                return $path === null || $path === '' ? $name : trim((string) $path, '/').'/'.$name;
            }
        };

        try {
            $context->at($at, fn () => $context->asUser($buyer, fn () => $this->rfqs->uploadInternalDocument(
                $rfq->fresh(),
                $upload,
                'quotation_pdf',
                (int) $vendor->id,
                $buyer,
            )));
        } finally {
            @unlink($temporaryPath);
        }
    }

    private function placeholderQuotationPdf(string $vendorName, string $quoteMarker): string
    {
        $lines = [
            'OGAMI DEMO DATA - FICTIONAL SUPPLIER QUOTATION',
            $vendorName,
            $quoteMarker,
            'For demonstration only. No commercial offer or real supplier commitment.',
        ];
        $commands = "BT\n/F1 11 Tf\n50 760 Td\n";
        foreach ($lines as $index => $line) {
            $text = str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], Str::ascii($line));
            if ($index > 0) {
                $commands .= "0 -18 Td\n";
            }
            $commands .= '('.$text.") Tj\n";
        }
        $commands .= 'ET';

        $objects = [
            '<< /Type /Catalog /Pages 2 0 R >>',
            '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >>',
            '<< /Length '.strlen($commands).">>\nstream\n{$commands}\nendstream",
            '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
        ];
        $pdf = "%PDF-1.4\n";
        $offsets = [0];
        foreach ($objects as $index => $object) {
            $offsets[] = strlen($pdf);
            $pdf .= ($index + 1)." 0 obj\n{$object}\nendobj\n";
        }
        $xrefOffset = strlen($pdf);
        $pdf .= "xref\n0 ".(count($objects) + 1)."\n0000000000 65535 f \n";
        foreach (array_slice($offsets, 1) as $offset) {
            $pdf .= str_pad((string) $offset, 10, '0', STR_PAD_LEFT)." 00000 n \n";
        }
        $pdf .= "trailer\n<< /Size ".(count($objects) + 1)." /Root 1 0 R >>\nstartxref\n{$xrefOffset}\n%%EOF\n";

        return $pdf;
    }

    /** @param array<string, User> $actors */
    private function ensureRequest(
        DemoSeedContext $context,
        string $code,
        User $requester,
        Item $item,
        Vendor $vendor,
        string $quantity,
        string $unitPrice,
        CarbonImmutable $createdAt,
        PurchaseRequestSourcingMethod $sourcingMethod,
    ): PurchaseRequest {
        $marker = self::MARKER.$code;
        $existing = PurchaseRequest::query()->where('reason', $marker)->first();
        if ($existing instanceof PurchaseRequest) {
            $line = $existing->items()->first();
            if (! $line || (int) $line->item_id !== (int) $item->id
                || bccomp((string) $line->quantity, $quantity, 3) !== 0
                || bccomp((string) $line->estimated_unit_price, $unitPrice, 4) !== 0
                || (int) $existing->requested_by !== (int) $requester->id) {
                throw new RuntimeException("Seed-owned purchase request {$marker} differs from this deterministic layer; restore its snapshot before reseeding.");
            }

            return $existing;
        }

        $departmentId = $requester->employee?->department_id;
        if ($departmentId === null) {
            throw new RuntimeException("Purchasing request actor {$requester->email} needs an employee department.");
        }
        $leadDays = max(1, (int) ($item->lead_time_days ?? 7));
        $payload = [
            'date' => $createdAt->toDateString(),
            'required_delivery_date' => $createdAt->addDays($leadDays)->toDateString(),
            'reason' => $marker,
            'priority' => PurchaseRequestPriority::Normal->value,
            'sourcing_method' => $sourcingMethod->value,
            'department_id' => (int) $departmentId,
            'items' => [[
                'item_id' => (int) $item->id,
                'description' => (string) $item->name,
                'quantity' => $quantity,
                'unit' => (string) $item->unit_of_measure,
                'estimated_unit_price' => $unitPrice,
                'suggested_vendor_id' => (int) $vendor->id,
                'purpose' => 'Replenish production resin inventory for the current customer schedule.',
            ]],
        ];

        return $context->at($createdAt, fn (): PurchaseRequest => $context->asUser($requester, fn (): PurchaseRequest => $this->purchaseRequests->create($payload, $requester)));
    }

    /** @param array<string, User> $actors */
    private function approvePurchaseRequest(
        DemoSeedContext $context,
        PurchaseRequest $pr,
        array $actors,
        CarbonImmutable $at,
        ?string $stopAfterRole = null,
    ): void {
        $cursor = 0;
        while ($pr->fresh()->status === PurchaseRequestStatus::Pending) {
            $next = $this->approvals->nextStep($pr->fresh());
            if ($next === null) {
                throw new RuntimeException("Purchase request {$pr->pr_number} is pending without a current approval step.");
            }
            $role = (string) $next->role_slug;
            if ($stopAfterRole !== null && $role !== $stopAfterRole) {
                break;
            }
            $approver = match ($role) {
                'finance_officer' => $actors['finance'],
                'vice_president' => $actors['vp'],
                default => throw new RuntimeException("Purchasing approval chain names unsupported role {$role} for {$pr->pr_number}."),
            };
            $instant = $at->addMinutes($cursor * 5);
            $context->at($instant, fn () => $context->asUser($approver, fn () => $this->purchaseRequests->approve(
                $pr->fresh(),
                $approver,
                'Reviewed against the approved demo department budget and current production requirement.',
            )));
            $cursor++;
        }
    }

    /** @param array<string, User> $actors */
    private function approvePurchaseOrder(
        DemoSeedContext $context,
        PurchaseOrder $po,
        array $actors,
        CarbonImmutable $at,
        ?string $stopAfterRole = null,
    ): void {
        $cursor = 0;
        while ($po->fresh()->status === PurchaseOrderStatus::PendingApproval) {
            $next = $this->approvals->nextStep($po->fresh());
            if ($next === null) {
                throw new RuntimeException("Purchase order {$po->po_number} is pending without a current approval step.");
            }
            $role = (string) $next->role_slug;
            if ($stopAfterRole !== null && $role !== $stopAfterRole) {
                break;
            }
            $approver = match ($role) {
                'finance_officer' => $actors['finance'],
                'vice_president' => $actors['vp'],
                default => throw new RuntimeException("Purchase order approval chain names unsupported role {$role} for {$po->po_number}."),
            };
            $instant = $at->addMinutes($cursor * 5);
            $context->at($instant, fn () => $context->asUser($approver, fn () => $this->purchaseOrders->approve(
                $po->fresh(),
                $approver,
                'Reviewed supplier price, quantity, and delivery commitment.',
            )));
            $cursor++;
        }
    }

    private function submitRequest(DemoSeedContext $context, PurchaseRequest $pr, User $requester, CarbonImmutable $at): void
    {
        if ($pr->fresh()->status !== PurchaseRequestStatus::Draft) {
            return;
        }
        $context->at($at, fn () => $context->asUser($requester, fn () => $this->purchaseRequests->submit($pr->fresh(), $requester)));
    }

    private function submitOrder(DemoSeedContext $context, PurchaseOrder $po, User $buyer, CarbonImmutable $at): void
    {
        if ($po->fresh()->status !== PurchaseOrderStatus::Draft) {
            return;
        }
        $context->at($at, fn () => $context->asUser($buyer, fn () => $this->purchaseOrders->submit($po->fresh(), $buyer)));
    }

    private function sendOrder(DemoSeedContext $context, PurchaseOrder $po, User $buyer, CarbonImmutable $at): void
    {
        if ($po->fresh()->status !== PurchaseOrderStatus::Approved) {
            return;
        }
        $context->at($at, fn () => $context->asUser($buyer, fn () => $this->purchaseOrders->markAsSent(
            $po->fresh(),
            'portal',
            $buyer,
        )));
    }

    /** @param array<string, User> $actors */
    private function ensureOrderFromRequest(
        DemoSeedContext $context,
        string $code,
        array $actors,
        Item $item,
        Vendor $vendor,
        string $quantity,
        CarbonImmutable $createdAt,
    ): PurchaseOrder {
        $marker = self::MARKER.'PO-PR:'.$code;
        $pr = $this->ensureRequest(
            $context,
            'PO-PR:'.$code,
            $actors['requester'],
            $item,
            $vendor,
            $quantity,
            $this->sourcingPrice((int) $item->id, (int) $vendor->id),
            $createdAt,
            PurchaseRequestSourcingMethod::DirectPo,
        );
        $this->submitRequest($context, $pr, $actors['requester'], $createdAt->addHours(1));
        $this->approvePurchaseRequest($context, $pr, $actors, $createdAt->addDays(1));
        $pr->refresh();
        if (! in_array($pr->status, [PurchaseRequestStatus::Approved, PurchaseRequestStatus::Converted], true)) {
            throw new RuntimeException("Purchase order scenario {$code} requires an approved source PR; found {$pr->status->value}.");
        }

        $existing = PurchaseOrder::query()->where('purchase_request_id', $pr->id)->orderBy('id')->first();
        if ($existing instanceof PurchaseOrder) {
            return $existing;
        }
        $line = $pr->items()->firstOrFail();
        $deliveryDate = $createdAt->addDays(max(5, (int) ($item->lead_time_days ?? 7)))->toDateString();
        $orders = $context->at($createdAt->addDays(2), fn () => $context->asUser($actors['buyer'], fn (): array => $this->purchaseOrders->convertFromPr(
            $pr->fresh(),
            [(int) $line->id => (int) $vendor->id],
            $actors['buyer'],
            expectedDeliveryDate: $deliveryDate,
        )));
        if (! isset($orders[0])) {
            throw new RuntimeException("Purchase order scenario {$code} did not create a supplier order.");
        }

        return $orders[0]->fresh();
    }

    private function respondToOrder(
        DemoSeedContext $context,
        PurchaseOrder $po,
        Vendor $vendor,
        SupplierPortalUser $portalUser,
        PurchaseOrderResponseType $type,
        CarbonImmutable $at,
    ): void {
        $existing = PurchaseOrderResponse::query()
            ->where('purchase_order_id', $po->id)
            ->where('response_type', $type->value)
            ->first();
        if ($existing instanceof PurchaseOrderResponse) {
            return;
        }
        $data = ['type' => $type->value, 'notes' => 'Demo supplier response recorded through its active supplier portal account.'];
        if ($type === PurchaseOrderResponseType::Propose) {
            $data['proposed_delivery_date'] = $po->expected_delivery_date?->addDays(3)->toDateString()
                ?? $at->addDays(20)->toDateString();
        }
        $context->at($at, fn () => $context->asUser($portalUser, fn () => $this->supplierResponses->respond(
            $po->fresh(),
            (int) $vendor->id,
            (int) $portalUser->id,
            $data,
        )));
    }

    private function ensureSupplierPortalUser(
        DemoSeedContext $context,
        Vendor $vendor,
        User $administrator,
        CarbonImmutable $asOf,
    ): SupplierPortalUser {
        $password = (string) config('demo.credentials.password', '');
        if ($password === '') {
            throw new RuntimeException('DEMO_ACCOUNT_PASSWORD must be configured before creating supplier portal accounts.');
        }
        $contact = self::SUPPLIER_CONTACTS[((int) $vendor->id - 1) % count(self::SUPPLIER_CONTACTS)];
        $email = $contact['email_prefix'].'.'.Str::slug((string) ($vendor->code ?? $vendor->id), '.').'@portal.ogami-demo.test';
        $existing = SupplierPortalUser::withTrashed()->whereRaw('LOWER(email) = ?', [$email])->first();
        if ($existing instanceof SupplierPortalUser) {
            if ($existing->trashed()
                || (int) $existing->vendor_id !== (int) $vendor->id
                || ! $existing->is_active
                || ! $existing->is_authorized_signatory
                || $existing->must_change_password
                || ! Hash::check($password, (string) $existing->getRawOriginal('password'))) {
                throw new RuntimeException("Seed-owned supplier portal account {$email} has drifted; restore the demo snapshot before reseeding.");
            }

            return $existing;
        }

        return $context->withoutNotifications(fn () => $context->at($asOf, fn (): SupplierPortalUser => DB::transaction(function () use ($context, $vendor, $contact, $email, $administrator, $password): SupplierPortalUser {
            $request = Request::create('/demo-data/purchasing-history', 'POST', server: [
                'HTTP_HOST' => 'localhost',
                'HTTP_USER_AGENT' => 'Ogami demo data seeder',
                'REMOTE_ADDR' => '127.0.0.1',
            ]);
            $invited = $context->asUser($administrator, fn (): SupplierPortalUser => $this->portalAccess->inviteSupplier(
                $vendor,
                $contact['name'],
                $email,
                $administrator,
                $request,
                isAuthorizedSignatory: true,
            ));
            $invitation = $this->passwordResets->issueInvitationToken('supplier', $invited);
            $this->passwordResets->reset('supplier', $invitation['token'], $password, $request);

            return $invited->fresh();
        })));
    }

    private function preferredPricedVendor(int $itemId): Vendor
    {
        foreach ($this->sourcing->candidatesForItem($itemId) as $candidate) {
            if (($candidate['price'] ?? null) === null || bccomp((string) $candidate['price'], '0', 4) <= 0) {
                continue;
            }
            $vendor = Vendor::query()->find((int) $candidate['vendor_id']);
            if ($vendor instanceof Vendor && $vendor->isPurchasable()) {
                return $vendor;
            }
        }

        $itemCode = Item::query()->whereKey($itemId)->value('code') ?? (string) $itemId;

        throw new RuntimeException("Purchasing history cannot source {$itemCode}: no active supplier has a positive current approved/listed/last-purchase price.");
    }

    private function sourcingPrice(int $itemId, int $vendorId): string
    {
        $price = $this->sourcing->priceFor($itemId, $vendorId);
        if ($price === null || bccomp($price, '0', 4) <= 0) {
            throw new RuntimeException('Purchasing history requires a positive price from the current supplier sourcing service.');
        }

        return $price;
    }

    private function workflowThreshold(string $workflowType, string $roleSlug): string
    {
        $workflow = DB::table('workflow_definitions')->where('workflow_type', $workflowType)->where('is_active', true)->first();
        if (! $workflow) {
            throw new RuntimeException("Purchasing history requires the active {$workflowType} approval workflow.");
        }
        $steps = is_array($workflow->steps) ? $workflow->steps : json_decode((string) $workflow->steps, true);
        foreach ((array) $steps as $step) {
            if (($step['role'] ?? null) === $roleSlug && isset($step['threshold'])) {
                return (string) $step['threshold'];
            }
        }

        throw new RuntimeException("The active {$workflowType} workflow has no threshold step for {$roleSlug}; cannot create a representative approval case.");
    }

    private function quantityOver(string $threshold, string $unitPrice): string
    {
        $quantity = bcadd(bcdiv($threshold, $unitPrice, 0), '1', 0);

        return bccomp(bcmul($quantity, $unitPrice, 2), $threshold, 2) > 0 ? $quantity : bcadd($quantity, '1', 0);
    }

    private function account(string $email, string $permission): User
    {
        $user = User::query()->with('employee')->where('email', $email)->where('is_active', true)->first();
        if (! $user instanceof User) {
            throw new RuntimeException("Purchasing history requires active workforce actor {$email}.");
        }
        if (! $user->hasPermission($permission)) {
            throw new RuntimeException("Purchasing history actor {$email} lacks required {$permission} permission.");
        }

        return $user;
    }

    private function assertRequestState(PurchaseRequest $pr, PurchaseRequestStatus $expected, string $code): void
    {
        $fresh = $pr->fresh();
        if (! $fresh || $fresh->status !== $expected) {
            throw new RuntimeException("Purchasing scenario {$code} expected PR status {$expected->value}; found ".($fresh?->status?->value ?? 'missing').'.');
        }
    }

    private function assertOrderState(PurchaseOrder $po, PurchaseOrderStatus $expected, string $code): void
    {
        $fresh = $po->fresh();
        if (! $fresh || $fresh->status !== $expected) {
            throw new RuntimeException("Purchasing scenario {$code} expected PO status {$expected->value}; found ".($fresh?->status?->value ?? 'missing').'.');
        }
    }

    private function assertRfqState(RequestForQuote $rfq, RfqStatus $expected, string $code): void
    {
        $fresh = $rfq->fresh();
        if (! $fresh || $fresh->status !== $expected) {
            throw new RuntimeException("Purchasing scenario {$code} expected RFQ status {$expected->value}; found ".($fresh?->status?->value ?? 'missing').'.');
        }
    }
}
