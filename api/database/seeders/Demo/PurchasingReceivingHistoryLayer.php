<?php

declare(strict_types=1);

namespace Database\Seeders\Demo;

use App\Common\DemoData\DemoSeedContext;
use App\Common\DemoData\DemoSeeder;
use App\Modules\Accounting\Enums\BillPaymentStatus;
use App\Modules\Accounting\Enums\BillStatus;
use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\Bill;
use App\Modules\Accounting\Models\BillPayment;
use App\Modules\Accounting\Services\BillService;
use App\Modules\Attendance\Models\Holiday;
use App\Modules\Auth\Models\User;
use App\Modules\B2B\Models\SupplierPortalUser;
use App\Modules\B2B\Services\SupplierInvoiceService;
use App\Modules\Inventory\Enums\GrnStatus;
use App\Modules\Inventory\Models\GoodsReceiptNote;
use App\Modules\Inventory\Models\Item;
use App\Modules\Inventory\Models\WarehouseLocation;
use App\Modules\Inventory\Services\GrnService;
use App\Modules\Purchasing\Enums\PurchaseOrderStatus;
use App\Modules\Purchasing\Models\PurchaseOrder;
use App\Modules\Purchasing\Models\PurchaseOrderItem;
use App\Modules\Purchasing\Models\PurchaseRequest;
use App\Modules\Quality\Enums\InspectionStatus;
use App\Modules\Quality\Enums\NcrDisposition;
use App\Modules\Quality\Models\Inspection;
use App\Modules\Quality\Models\InspectionMeasurement;
use App\Modules\Quality\Models\NonConformanceReport;
use App\Modules\Quality\Services\InspectionService;
use App\Modules\Quality\Services\NcrService;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Support\Carbon;
use RuntimeException;

/** Seeds current GRN, incoming-QC, supplier-invoice, AP and payment workflows. */
final class PurchasingReceivingHistoryLayer extends DemoSeeder
{
    private const MARKER = 'DEMO-RECEIVING:';

    public function __construct(
        private readonly GrnService $grns,
        private readonly InspectionService $inspections,
        private readonly NcrService $ncrs,
        private readonly BillService $bills,
        private readonly SupplierInvoiceService $supplierInvoices,
    ) {}

    public function key(): string
    {
        return 'purchasing-receiving-history';
    }

    /** @return list<string> */
    public function modules(): array
    {
        return ['Inventory', 'Quality', 'Purchasing', 'Accounting', 'B2B', 'Dashboard'];
    }

    /** @return list<string> */
    public function dependencies(): array
    {
        return ['purchasing-history'];
    }

    protected function seedDemoData(DemoSeedContext $context): void
    {
        $asOf = $context->options->asOf->setTimezone('Asia/Manila')->startOfDay();
        $actors = [
            'receiver' => $this->account('warehouse.staff@accounts.ogami-demo.test', 'inventory.grn.create'),
            'inspector' => $this->account('qc.inspector@accounts.ogami-demo.test', 'quality.inspections.manage'),
            'checker' => $this->account('system.admin@accounts.ogami-demo.test', 'quality.inspections.review'),
            'mrb' => $this->account('system.admin@accounts.ogami-demo.test', 'quality.ncr.manage'),
            'finance' => $this->account('finance.officer@accounts.ogami-demo.test', 'accounting.bills.pay'),
            'voider' => $this->account('system.admin@accounts.ogami-demo.test', 'accounting.bills.void_payment'),
            'buyer' => $this->account('purchasing.officer@accounts.ogami-demo.test', 'purchasing.po.create'),
        ];
        $item = Item::query()->where('code', 'RM-001')->where('is_active', true)->first();
        if (! $item instanceof Item) {
            throw new RuntimeException('Purchasing receiving history requires the active RM-001 resin item.');
        }
        $location = WarehouseLocation::query()->where('code', 'A-01')->where('is_active', true)->first();
        if (! $location instanceof WarehouseLocation) {
            throw new RuntimeException('Purchasing receiving history requires the active raw-material bin A-01.');
        }

        $acknowledgedPo = $this->scenarioOrder('PO-ACKNOWLEDGED', [
            PurchaseOrderStatus::Acknowledged,
            PurchaseOrderStatus::PartiallyReceived,
            PurchaseOrderStatus::Received,
            PurchaseOrderStatus::Closed,
        ]);
        $poLine = $acknowledgedPo->items()->where('item_id', $item->id)->first();
        if ($poLine === null) {
            throw new RuntimeException("Receiving history requires RM-001 on {$acknowledgedPo->po_number}.");
        }
        $supplier = SupplierPortalUser::query()
            ->where('vendor_id', $acknowledgedPo->vendor_id)
            ->where('is_active', true)
            ->orderBy('id')
            ->first();
        if (! $supplier instanceof SupplierPortalUser) {
            throw new RuntimeException('Purchasing receiving history requires the active demo supplier portal account.');
        }
        $cash = Account::query()->where('code', '1020')->where('is_active', true)->first();
        if (! $cash instanceof Account) {
            throw new RuntimeException('Purchasing receiving history requires the configured active bank account 1020.');
        }

        $sentPo = $this->scenarioOrder('PO-SENT', [PurchaseOrderStatus::Sent]);
        $draftAt = $this->businessDay($asOf, 4)->setTime(8, 30);
        $expected = $context->at($draftAt, fn () => $context->asUser($actors['buyer'], fn () => $this->grns->createDraftForPo($sentPo->fresh(), $actors['buyer'])
        ));
        if (! $expected instanceof GoodsReceiptNote || $expected->status !== GrnStatus::Draft) {
            throw new RuntimeException("The expected-receipt draft for {$sentPo->po_number} was not staged.");
        }
        $context->records()->remember('purchasing-receiving.grn.expected', $expected);

        $this->seedAcceptedBill($context, $asOf, $actors, $supplier, $acknowledgedPo, $poLine, $location, 'PAYMENT-VOID', 1, 4, payment: 'void');
        $this->seedAcceptedBill($context, $asOf, $actors, $supplier, $acknowledgedPo, $poLine, $location, 'PAYMENT-PARTIAL', 1, 3, payment: 'partial');
        $this->seedAcceptedBill($context, $asOf, $actors, $supplier, $acknowledgedPo, $poLine, $location, 'PAYMENT-PAID', 1, 2, payment: 'full');
        $this->seedAcceptedBill($context, $asOf, $actors, $supplier, $acknowledgedPo, $poLine, $location, 'BILL-CANCELLED', 1, 1, cancelDraft: true);
        $this->seedAcceptedBill($context, $asOf, $actors, $supplier, $acknowledgedPo, $poLine, $location, 'BILL-UNPAID', 1, 1, postUnpaid: true);
        $this->seedPartiallyAcceptedReceipt($context, $asOf, $actors, $acknowledgedPo, $poLine, $location);
        $this->seedRejectedReceipt($context, $asOf, $actors, $acknowledgedPo, $poLine, $location);
        $this->seedLivePendingQc($context, $asOf, $actors, $acknowledgedPo, $poLine, $location);
    }

    /** @param array<string, User> $actors */
    private function seedAcceptedBill(
        DemoSeedContext $context,
        CarbonImmutable $asOf,
        array $actors,
        SupplierPortalUser $supplier,
        PurchaseOrder $po,
        PurchaseOrderItem $poLine,
        WarehouseLocation $location,
        string $scenario,
        int $quantity,
        int $businessDaysAgo,
        ?string $payment = null,
        bool $cancelDraft = false,
        bool $postUnpaid = false,
    ): void {
        $date = $this->businessDay($asOf, $businessDaysAgo);
        $context->withoutNotifications(function () use ($context, $actors, $supplier, $po, $poLine, $location, $scenario, $quantity, $date, $payment, $cancelDraft, $postUnpaid): void {
            $grn = $this->receive($context, $actors['receiver'], $po, $poLine, $location, $scenario, $quantity, $date->setTime(8, 0));
            $this->passIncomingInspection($context, $grn, $actors, $date->setTime(9, 0));
            $bill = $this->billForAcceptedReceipt($context, $grn, $actors['checker'], $date->setTime(9, 30));

            if ($cancelDraft) {
                if ($bill->status !== BillStatus::Cancelled) {
                    $context->at($date->setTime(10, 0), fn () => $context->asUser($actors['finance'], fn () => $this->bills->cancel($bill->fresh(), $actors['finance'], Carbon::parse($date->toDateString()), 'Demo supplier price review cancelled the unposted draft.')
                    ));
                }
                $context->records()->remember('purchasing-receiving.bill.'.strtolower($scenario), $bill->fresh());

                return;
            }

            $this->submitSupplierInvoice($context, $bill, $supplier, $date->setTime(10, 0), $scenario);
            if ($bill->fresh()->status === BillStatus::Draft) {
                $context->at($date->setTime(10, 30), fn () => $context->asUser($actors['finance'], fn () => $this->bills->postDraft($bill->fresh(), $actors['finance'])
                ));
            }

            if ($payment !== null) {
                $paid = $this->recordPayment($context, $bill->fresh(), (int) Account::query()->where('code', '1020')->value('id'), $actors['finance'], $date->setTime(11, 0), $scenario, $payment);
                if ($payment === 'void' && $paid->status === BillPaymentStatus::Posted) {
                    $context->at($date->setTime(12, 0), fn () => $context->asUser($actors['voider'], fn () => $this->bills->voidPayment($bill->fresh(), $paid->fresh(), [
                        'void_date' => $date->toDateString(),
                        'reason' => 'Demo bank reconciliation identified a duplicate remittance reference.',
                    ], $actors['voider'])
                    ));
                }
            } elseif ($postUnpaid && $bill->fresh()->status !== BillStatus::Unpaid) {
                throw new RuntimeException("Scenario {$scenario} should remain an unpaid supplier bill.");
            }
            $context->records()->remember('purchasing-receiving.bill.'.strtolower($scenario), $bill->fresh());
        });
    }

    /** @param array<string, User> $actors */
    private function seedPartiallyAcceptedReceipt(
        DemoSeedContext $context,
        CarbonImmutable $asOf,
        array $actors,
        PurchaseOrder $po,
        PurchaseOrderItem $poLine,
        WarehouseLocation $location,
    ): void {
        $at = $this->businessDay($asOf, 1)->setTime(8, 0);
        $context->withoutNotifications(function () use ($context, $actors, $po, $poLine, $location, $at): void {
            $grn = $this->receive($context, $actors['receiver'], $po, $poLine, $location, 'GRN-PARTIAL-ACCEPT', 2, $at);
            $this->failIncomingInspection($context, $grn, $actors, $at->addHours(1));
            $inspection = $this->inspectionFor($grn);
            $ncr = NonConformanceReport::query()->where('inspection_id', $inspection->id)->first()
                ?? $this->ncrs->openFromInspectionFailure($inspection, $actors['inspector']);
            if ($ncr->mrb_decided_at === null) {
                $this->contextual($context, $at->addHours(2), $actors['mrb'], fn () => $this->ncrs->setDisposition(
                    $ncr->fresh(),
                    'return_to_supplier',
                    'Incoming resin lot contained an out-of-spec moisture sample.',
                    'Supplier to submit an 8D response and segregate the remainder.',
                    $actors['mrb'],
                    '1.000',
                    [
                        'inspected_quantity' => '2.000',
                        'evidence_reference' => 'DEMO-RH-SORT-PARTIAL-ACCEPT',
                        'performed_by' => $actors['inspector']->id,
                        'recovery_passed' => true,
                    ],
                ));
            } elseif ($this->dispositionValue($ncr) !== NcrDisposition::ReturnToSupplier->value) {
                throw new RuntimeException('The existing partial-acceptance NCR has a conflicting final MRB disposition.');
            }
            $fresh = $grn->fresh();
            if ($fresh->status !== GrnStatus::PartialAccepted || (string) $fresh->items()->firstOrFail()->quantity_accepted !== '1.000') {
                throw new RuntimeException('The MRB sorted-good receipt did not settle at one accepted kilogram.');
            }
            $this->billForAcceptedReceipt($context, $fresh, $actors['checker'], $at->addHours(3));
            $context->records()->remember('purchasing-receiving.grn.partial-accepted', $fresh);
        });
    }

    /** @param array<string, User> $actors */
    private function seedRejectedReceipt(
        DemoSeedContext $context,
        CarbonImmutable $asOf,
        array $actors,
        PurchaseOrder $po,
        PurchaseOrderItem $poLine,
        WarehouseLocation $location,
    ): void {
        $at = $this->businessDay($asOf, 0)->setTime(8, 0);
        $context->withoutNotifications(function () use ($context, $actors, $po, $poLine, $location, $at): void {
            $grn = $this->receive($context, $actors['receiver'], $po, $poLine, $location, 'GRN-REJECTED', 1, $at);
            $this->failIncomingInspection($context, $grn, $actors, $at->addHour());
            $inspection = $this->inspectionFor($grn);
            $ncr = NonConformanceReport::query()->where('inspection_id', $inspection->id)->first()
                ?? $this->ncrs->openFromInspectionFailure($inspection, $actors['inspector']);
            if ($ncr->mrb_decided_at === null) {
                $this->contextual($context, $at->addHours(2), $actors['mrb'], fn () => $this->ncrs->setDisposition(
                    $ncr->fresh(),
                    'return_to_supplier',
                    'The supplier lot failed incoming material release criteria.',
                    'Supplier replacement lot required before this material can be used.',
                    $actors['mrb'],
                ));
            } elseif ($this->dispositionValue($ncr) !== NcrDisposition::ReturnToSupplier->value) {
                throw new RuntimeException('The existing rejected-lot NCR has a conflicting final MRB disposition.');
            }
            if ($grn->fresh()->status !== GrnStatus::Rejected) {
                throw new RuntimeException('The full-lot MRB return did not reject its GRN.');
            }
            $context->records()->remember('purchasing-receiving.grn.rejected', $grn->fresh());
        });
    }

    /** @param array<string, User> $actors */
    private function seedLivePendingQc(
        DemoSeedContext $context,
        CarbonImmutable $asOf,
        array $actors,
        PurchaseOrder $po,
        PurchaseOrderItem $poLine,
        WarehouseLocation $location,
    ): void {
        $at = $this->businessDay($asOf, 0)->setTime(14, 0);
        $grn = $this->receive($context, $actors['receiver'], $po, $poLine, $location, 'LIVE-PENDING-QC', 1, $at);
        if ($grn->status !== GrnStatus::PendingQc) {
            throw new RuntimeException('The live receiving scenario must remain pending incoming QC.');
        }
        $context->records()->remember('purchasing-receiving.grn.live-pending-qc', $grn);
    }

    private function receive(
        DemoSeedContext $context,
        User $receiver,
        PurchaseOrder $po,
        PurchaseOrderItem $poLine,
        WarehouseLocation $location,
        string $scenario,
        int $quantity,
        CarbonImmutable $at,
    ): GoodsReceiptNote {
        $key = strtolower($scenario);
        $created = $context->at($at, fn () => $context->asUser($receiver, fn () => $this->grns->create(
            $po->fresh(),
            [[
                'purchase_order_item_id' => (int) $poLine->id,
                'item_id' => (int) $poLine->item_id,
                'location_id' => (int) $location->id,
                'quantity_received' => number_format($quantity, 3, '.', ''),
                'lot_number' => 'DEMO-RH-LOT-'.$key,
                'supplier_lot_reference' => 'SUP-RH-LOT-'.$key,
                'remarks' => 'Supplier delivery reference for '.$scenario.'.',
            ]],
            [
                'received_date' => $at->toDateString(),
                'remarks' => self::MARKER.$scenario,
                'idempotency_key' => 'demo-receiving-'.substr(hash('sha256', $key), 0, 24),
            ],
            $receiver,
        )));
        $context->records()->remember('purchasing-receiving.grn.'.strtolower($scenario), $created);

        return $created;
    }

    /** @param array<string, User> $actors */
    private function passIncomingInspection(DemoSeedContext $context, GoodsReceiptNote $grn, array $actors, CarbonImmutable $at): void
    {
        $inspection = $this->inspectionFor($grn);
        if ($inspection->status === InspectionStatus::Passed) {
            return;
        }
        if ($inspection->status !== InspectionStatus::Draft && $inspection->status !== InspectionStatus::InProgress) {
            throw new RuntimeException("Passing QC for {$grn->grn_number} found inspection state {$inspection->status->value}.");
        }

        $rows = $inspection->measurements()->orderBy('sample_index')->orderBy('id')->get();
        if ($rows->isEmpty()) {
            throw new RuntimeException("Incoming inspection {$inspection->inspection_number} has no criteria.");
        }
        $submitted = $context->at($at, fn () => $context->asUser($actors['inspector'], function () use ($inspection, $rows, $actors) {
            if ($inspection->usesChecklistCapture()) {
                return $this->inspections->recordBatchResult($inspection->fresh(), [
                    'expected_result_version' => (int) $inspection->result_version,
                    'coverage_confirmed' => true,
                    'inspected_quantity' => max(1, (int) $inspection->sample_size),
                    'items' => $rows->map(fn (InspectionMeasurement $row): array => [
                        'id' => (int) $row->id,
                        'result' => 'passed',
                        ...($row->requiresSizeReading() ? ['measured_value' => $row->nominal_value] : []),
                    ])->all(),
                ], $actors['inspector']);
            }

            $checklist = [];
            $measurements = [];
            foreach ($rows as $row) {
                if ($row->hasTolerance()) {
                    $measurements[] = ['_id' => (int) $row->id, 'measured_value' => $row->nominal_value];
                } else {
                    $checklist[] = ['_id' => (int) $row->id, 'is_pass' => true];
                }
            }

            return $this->inspections->recordLotResult($inspection->fresh(), [
                'expected_result_version' => (int) $inspection->result_version,
                'checklist' => $checklist,
                'measurements' => $measurements,
                'sample_defect_count' => 0,
                'complete' => true,
                'coverage_confirmed' => true,
                'defects_removed_confirmed' => true,
            ], $actors['inspector']);
        }));

        $this->reviewInspection($context, $submitted, $actors, $at->addMinutes(10));
        $this->settleQc($context, $grn, $actors['receiver'], $at->addMinutes(15));
        if ($grn->fresh()->status !== GrnStatus::Accepted) {
            throw new RuntimeException("Passing incoming QC did not accept {$grn->grn_number}.");
        }
    }

    /** @param array<string, User> $actors */
    private function failIncomingInspection(DemoSeedContext $context, GoodsReceiptNote $grn, array $actors, CarbonImmutable $at): void
    {
        $inspection = $this->inspectionFor($grn);
        if ($inspection->status === InspectionStatus::Failed) {
            return;
        }
        if ($inspection->status !== InspectionStatus::Draft && $inspection->status !== InspectionStatus::InProgress) {
            throw new RuntimeException("Failing QC for {$grn->grn_number} found inspection state {$inspection->status->value}.");
        }

        $rows = $inspection->measurements()->orderBy('sample_index')->orderBy('id')->get();
        if ($rows->isEmpty()) {
            throw new RuntimeException("Incoming inspection {$inspection->inspection_number} has no criteria.");
        }
        $submitted = $context->at($at, fn () => $context->asUser($actors['inspector'], function () use ($inspection, $rows, $actors) {
            if ($inspection->usesChecklistCapture()) {
                $firstId = (int) $rows->first()->id;

                return $this->inspections->recordBatchResult($inspection->fresh(), [
                    'expected_result_version' => (int) $inspection->result_version,
                    'coverage_confirmed' => true,
                    'inspected_quantity' => max(1, (int) $inspection->sample_size),
                    'physical_defects_confirmed' => true,
                    'problem_description' => 'Incoming lot failed supplier certificate and visual acceptance criteria.',
                    'items' => $rows->map(function (InspectionMeasurement $row) use ($firstId): array {
                        $failed = (int) $row->id === $firstId;

                        return [
                            'id' => (int) $row->id,
                            'result' => $failed ? 'failed' : 'passed',
                            ...($failed ? ['problem_description' => 'Moisture or contamination indication outside the receiving specification.'] : []),
                            ...($row->requiresSizeReading() ? ['measured_value' => $failed ? $this->outOfToleranceValue($row) : $row->nominal_value] : []),
                        ];
                    })->all(),
                ], $actors['inspector']);
            }

            $checklist = [];
            $measurements = [];
            foreach ($rows as $index => $row) {
                if ($row->hasTolerance()) {
                    $measurements[] = [
                        '_id' => (int) $row->id,
                        'measured_value' => $index === 0
                            ? $this->outOfToleranceValue($row)
                            : $row->nominal_value,
                    ];
                } else {
                    $checklist[] = ['_id' => (int) $row->id, 'is_pass' => $index !== 0];
                }
            }

            return $this->inspections->recordLotResult($inspection->fresh(), [
                'expected_result_version' => (int) $inspection->result_version,
                'checklist' => $checklist,
                'measurements' => $measurements,
                'sample_defect_count' => 1,
                'complete' => true,
                'coverage_confirmed' => true,
                'defects_removed_confirmed' => false,
            ], $actors['inspector']);
        }));

        $this->reviewInspection($context, $submitted, $actors, $at->addMinutes(10));
        $inspection->refresh();
        if ($inspection->status !== InspectionStatus::Failed) {
            throw new RuntimeException("Incoming QC failure did not produce a checked failure for {$grn->grn_number}.");
        }
        $context->at($at->addMinutes(15), fn () => $context->asUser($actors['receiver'], fn () => $this->settleQc($context, $grn, $actors['receiver'], $at->addMinutes(15))
        ));
    }

    /** @param array<string, User> $actors */
    private function reviewInspection(DemoSeedContext $context, Inspection $inspection, array $actors, CarbonImmutable $at): void
    {
        $fresh = $inspection->fresh();
        if ($fresh->status === InspectionStatus::Passed || $fresh->status === InspectionStatus::Failed) {
            return;
        }
        if ($fresh->status !== InspectionStatus::AwaitingReview) {
            throw new RuntimeException("Incoming inspection {$fresh->inspection_number} did not reach checker review.");
        }
        $decision = $fresh->proposed_result?->value ?? (string) $fresh->proposed_result;
        $context->at($at, fn () => $context->asUser($actors['checker'], fn () => $this->inspections->review(
            $fresh,
            $decision,
            $decision === 'failed' ? 'Supplier lot does not meet the current incoming release plan.' : null,
            $actors['checker'],
            (int) $fresh->result_version,
        )));
    }

    private function settleQc(DemoSeedContext $context, GoodsReceiptNote $grn, User $receiver, CarbonImmutable $at): string
    {
        return $context->at($at, fn () => $context->asUser($receiver, fn () => $this->grns->settleIncomingQc($grn->fresh(), $receiver)));
    }

    private function inspectionFor(GoodsReceiptNote $grn): Inspection
    {
        $lineId = $grn->items()->orderBy('id')->value('id');
        $inspection = Inspection::query()
            ->where('stage', 'incoming')
            ->where('entity_type', 'grn')
            ->where('entity_id', $grn->id)
            ->where('grn_item_id', $lineId)
            ->first();
        if (! $inspection instanceof Inspection) {
            throw new RuntimeException("GRN {$grn->grn_number} has no synchronously staged incoming inspection.");
        }

        return $inspection;
    }

    private function outOfToleranceValue(InspectionMeasurement $row): string
    {
        $low = $row->tolerance_min !== null ? (float) $row->tolerance_min : null;
        $high = $row->tolerance_max !== null ? (float) $row->tolerance_max : null;
        if ($low !== null) {
            return number_format($low - max(1.0, abs($low) * 0.1), 4, '.', '');
        }
        if ($high !== null) {
            return number_format($high + max(1.0, abs($high) * 0.1), 4, '.', '');
        }

        throw new RuntimeException("Inspection requirement {$row->parameter_name} has no measurable limit to support a failed sample.");
    }

    private function dispositionValue(NonConformanceReport $ncr): ?string
    {
        $disposition = $ncr->disposition;

        return $disposition instanceof NcrDisposition ? $disposition->value : $disposition;
    }

    private function billForAcceptedReceipt(
        DemoSeedContext $context,
        GoodsReceiptNote $grn,
        User $automationActor,
        CarbonImmutable $at,
    ): Bill {
        $existing = Bill::query()->where('goods_receipt_note_id', $grn->id)->first();
        if ($existing instanceof Bill) {
            return $existing;
        }
        $bill = $context->at($at, fn () => $context->asUser($automationActor, fn () => $this->bills->createDraftForGrn(
            $grn->fresh(),
            $automationActor,
        )));
        if (! $bill instanceof Bill) {
            throw new RuntimeException("Accepted GRN {$grn->grn_number} did not stage its supplier bill.");
        }

        return $bill;
    }

    private function submitSupplierInvoice(
        DemoSeedContext $context,
        Bill $bill,
        SupplierPortalUser $supplier,
        CarbonImmutable $at,
        string $scenario,
    ): void {
        $fresh = $bill->fresh(['goodsReceiptNote', 'purchaseOrder']);
        if ($fresh->supplier_invoice_id !== null) {
            return;
        }
        $grn = $fresh->goodsReceiptNote;
        $po = $fresh->purchaseOrder;
        if (! $grn || ! $po) {
            throw new RuntimeException("Bill {$fresh->bill_number} is missing its receiving document chain.");
        }
        $context->at($at, fn () => $context->asUser($supplier, fn () => $this->supplierInvoices->submitInvoice(
            (int) $supplier->vendor_id,
            (int) $supplier->id,
            $po,
            [
                'bill_number' => 'OGD-RH-'.str_replace('-', '', $scenario),
                'date' => $at->toDateString(),
                'goods_receipt_note_id' => $grn->hash_id,
                'remarks' => 'Synthetic supplier invoice for thesis demonstration.',
            ],
        )));
    }

    private function recordPayment(
        DemoSeedContext $context,
        Bill $bill,
        int $cashAccountId,
        User $finance,
        CarbonImmutable $at,
        string $scenario,
        string $kind,
    ): BillPayment {
        $amount = match ($kind) {
            'partial' => bcdiv(bcmul((string) $bill->total_amount, '0.60', 4), '1', 2),
            default => (string) $bill->balance,
        };
        $key = 'demo-rh-payment-'.strtolower($scenario);
        $existing = BillPayment::query()->where('bill_id', $bill->id)->where('idempotency_key', $key)->first();
        if ($existing) {
            return $existing;
        }
        $cash = Account::query()->findOrFail($cashAccountId);
        $payment = $context->at($at, fn () => $context->asUser($finance, fn () => $this->bills->recordPayment($bill->fresh(), [
            'cash_account_id' => $cash->hash_id,
            'payment_date' => $at->toDateString(),
            'amount' => $amount,
            'payment_method' => 'bank_transfer',
            'reference_number' => 'DEMO-RH-'.str_replace('-', '', $scenario),
            'idempotency_key' => $key,
        ], $finance)));
        $context->records()->remember('purchasing-receiving.payment.'.strtolower($scenario), $payment);

        return $payment;
    }

    private function scenarioOrder(string $scenario, array $allowedStatuses): PurchaseOrder
    {
        $request = PurchaseRequest::query()
            ->where('reason', 'DEMO-PURCHASING:PO-PR:'.$scenario)
            ->first();
        if (! $request) {
            throw new RuntimeException("Purchasing receiving history requires the {$scenario} request scenario.");
        }
        $order = PurchaseOrder::query()
            ->where('purchase_request_id', $request->id)
            ->whereIn('status', array_map(static fn (PurchaseOrderStatus $status): string => $status->value, $allowedStatuses))
            ->first();
        if (! $order) {
            throw new RuntimeException("Purchasing request {$scenario} has no PO in an allowed receiving state.");
        }

        return $order;
    }

    private function account(string $email, string $permission): User
    {
        $user = User::query()->where('email', $email)->where('is_active', true)->first();
        if (! $user instanceof User) {
            throw new RuntimeException("Purchasing receiving history requires active workforce actor {$email}.");
        }
        if (! $user->hasPermission($permission)) {
            throw new RuntimeException("Purchasing receiving actor {$email} lacks {$permission} permission.");
        }

        return $user;
    }

    private function businessDay(CarbonImmutable $asOf, int $offset): CarbonImmutable
    {
        $day = $asOf->setTimezone('Asia/Manila')->startOfDay();
        while (! $this->isBusinessDay($day)) {
            $day = $day->subDay();
        }
        $remaining = $offset;
        while ($remaining > 0) {
            $day = $day->subDay();
            if ($this->isBusinessDay($day)) {
                $remaining--;
            }
        }

        return $day;
    }

    private function isBusinessDay(CarbonImmutable $day): bool
    {
        if ($day->isWeekend()) {
            return false;
        }

        return ! Holiday::query()->whereDate('date', $day->toDateString())->exists()
            && ! Holiday::query()
                ->where('is_recurring', true)
                ->whereRaw("to_char(date, 'MM-DD') = ?", [$day->format('m-d')])
                ->exists();
    }

    /** @param Closure(): mixed $action */
    private function contextual(DemoSeedContext $context, CarbonImmutable $at, User $user, Closure $action): mixed
    {
        return $context->at($at, fn () => $context->asUser($user, $action));
    }
}
