<?php

declare(strict_types=1);

namespace Database\Seeders\Demo;

use App\Common\DemoData\DemoSeedContext;
use App\Common\DemoData\DemoSeeder;
use App\Common\Services\SettingsService;
use App\Modules\Accounting\Models\Customer;
use App\Modules\Attendance\Models\Holiday;
use App\Modules\Auth\Models\User;
use App\Modules\CRM\Enums\ComplaintStatus;
use App\Modules\CRM\Models\CustomerComplaint;
use App\Modules\CRM\Models\Product;
use App\Modules\CRM\Services\ComplaintService;
use App\Modules\Inventory\Models\GoodsReceiptNote;
use App\Modules\Quality\Enums\NcrActionType;
use App\Modules\Quality\Enums\NcrDisposition;
use App\Modules\Quality\Models\Inspection;
use App\Modules\Quality\Models\NcrAction;
use App\Modules\Quality\Models\NonConformanceReport;
use App\Modules\Quality\Services\NcrService;
use Carbon\CarbonImmutable;
use RuntimeException;

/** Seeds current customer complaints, 8D response, and NCR corrective actions. */
final class QualityHistoryLayer extends DemoSeeder
{
    private const PREFIX = 'DEMO-QUALITY:';

    public function __construct(
        private readonly ComplaintService $complaints,
        private readonly NcrService $ncrs,
        private readonly SettingsService $settings,
    ) {}

    public function key(): string
    {
        return 'quality-history';
    }

    /** @return list<string> */
    public function modules(): array
    {
        return ['Quality', 'CRM', 'Dashboard'];
    }

    /** @return list<string> */
    public function dependencies(): array
    {
        return ['purchasing-receiving-history'];
    }

    protected function seedDemoData(DemoSeedContext $context): void
    {
        $asOf = $context->options->asOf->setTimezone('Asia/Manila');
        $customerService = $this->account('customer.service@accounts.ogami-demo.test', 'crm.complaints.manage');
        $qualityManager = $this->account('system.admin@accounts.ogami-demo.test', 'quality.ncr.manage');
        $qualityOwner = $this->account('qc.inspector@accounts.ogami-demo.test', 'quality.ncr.manage');
        $customer = Customer::query()->where('is_active', true)->orderBy('id')->first();
        $product = Product::query()->where('part_number', 'FC-001')->where('is_active', true)->first();
        if (! $customer instanceof Customer || ! $product instanceof Product) {
            throw new RuntimeException('Quality history requires an active demo customer and FC-001 fuse-box cover product.');
        }

        $this->seedIncomingNcrActions($context, $asOf, $qualityManager, $qualityOwner);

        $d3Hours = $this->settings->requiredInt('crm.complaint_8d.d3_due_hours', 1);
        $withinClockAt = $asOf->subMinutes(max(1, min(60, $d3Hours * 30)));
        $overdueOffset = max(3, (int) ceil(($d3Hours + 24) / 24) + 1);
        $overdueAt = $this->businessDay($asOf, $overdueOffset)->setTime(9, 0);
        $historyAt = $this->businessDay($asOf, 30)->setTime(9, 0);

        $this->ensureComplaint($context, $customerService, $qualityManager, $customer, $product, 'OPEN-WITHIN-CLOCK', $withinClockAt, live: true);
        $overdue = $this->ensureComplaint($context, $customerService, $qualityManager, $customer, $product, 'OPEN-OVERDUE', $overdueAt, live: true);
        $this->seedOverdueComplaintActions($context, $overdue, $qualityManager, $qualityOwner, $overdueAt, $asOf);

        $resolvedAt = $this->businessDay($asOf, 15)->setTime(9, 0);
        $resolved = $this->ensureComplaint($context, $customerService, $qualityManager, $customer, $product, 'RESOLVED', $resolvedAt, live: false);
        $this->resolveHistoricalComplaint($context, $resolved, $customerService, $qualityManager, $qualityOwner, $resolvedAt, close: false);

        $closed = $this->ensureComplaint($context, $customerService, $qualityManager, $customer, $product, 'CLOSED', $historyAt, live: false);
        $this->resolveHistoricalComplaint($context, $closed, $customerService, $qualityManager, $qualityOwner, $historyAt);
    }

    private function seedIncomingNcrActions(
        DemoSeedContext $context,
        CarbonImmutable $asOf,
        User $qualityManager,
        User $qualityOwner,
    ): void {
        $grn = GoodsReceiptNote::query()->where('remarks', 'DEMO-RECEIVING:GRN-PARTIAL-ACCEPT')->first();
        if (! $grn instanceof GoodsReceiptNote) {
            throw new RuntimeException('Quality history requires the incoming partial-acceptance receipt from receiving history.');
        }
        $inspection = Inspection::query()->where('stage', 'incoming')->where('entity_type', 'grn')
            ->where('entity_id', $grn->id)->orderBy('id')->first();
        $ncr = $inspection ? NonConformanceReport::query()->where('inspection_id', $inspection->id)->first() : null;
        if (! $ncr instanceof NonConformanceReport || $ncr->status->isTerminal()) {
            throw new RuntimeException('The partial-acceptance incoming NCR is missing or no longer open for CAPA actions.');
        }

        $mrbAt = $ncr->mrb_decided_at
            ? CarbonImmutable::parse($ncr->mrb_decided_at)->setTimezone('Asia/Manila')
            : CarbonImmutable::parse($ncr->created_at)->setTimezone('Asia/Manila');
        $performedAt = $mrbAt->addHour();
        $this->ensureAction($context, $ncr, NcrActionType::Containment, 'INCOMING:CONTAINMENT', 'Segregated the remaining supplier resin lot and attached the verified sorting record.', $qualityManager, $qualityOwner, $performedAt, $this->businessDay($performedAt, -1));
        $this->ensureAction($context, $ncr, NcrActionType::Corrective, 'INCOMING:CORRECTIVE', 'Supplier to correct the resin drying and moisture-control step identified in the receiving failure.', $qualityManager, $qualityOwner, $performedAt->addMinutes(10), $this->businessDay($performedAt, -5));
        $this->ensureAction($context, $ncr, NcrActionType::Preventive, 'INCOMING:PREVENTIVE', 'Add the revised resin moisture certificate check to the supplier incoming checklist.', $qualityManager, $qualityOwner, $performedAt->addMinutes(20), $this->businessDay($performedAt, -14));
    }

    private function ensureComplaint(
        DemoSeedContext $context,
        User $customerService,
        User $qualityManager,
        Customer $customer,
        Product $product,
        string $scenario,
        CarbonImmutable $createdAt,
        bool $live,
    ): CustomerComplaint {
        $description = self::PREFIX.$scenario.': Customer reported a fitment concern on synthetic FC-001 fuse-box covers.';
        $existing = CustomerComplaint::query()->where('description', $description)->first();
        if ($existing instanceof CustomerComplaint) {
            return $existing->fresh(['ncr', 'eightDReport']);
        }

        $create = fn () => $context->at($createdAt, fn () => $context->asUser($customerService, fn () => $this->complaints->create([
            'customer_id' => (int) $customer->id,
            'product_id' => (int) $product->id,
            'received_date' => $createdAt->toDateString(),
            'severity' => 'high',
            'description' => $description,
            'affected_quantity' => 12,
            'assigned_to' => $customerService->id,
        ], $customerService)));

        $complaint = $live ? $create() : $context->withoutNotifications($create);
        if (! $complaint instanceof CustomerComplaint || $complaint->ncr_id === null) {
            throw new RuntimeException("Quality complaint scenario {$scenario} did not create its linked Quality NCR.");
        }
        if ($complaint->status !== ComplaintStatus::Open) {
            throw new RuntimeException("Quality complaint scenario {$scenario} did not start open.");
        }
        $context->records()->remember('quality-history.complaint.'.strtolower($scenario), $complaint);

        return $complaint->fresh(['ncr', 'eightDReport']);
    }

    private function seedOverdueComplaintActions(
        DemoSeedContext $context,
        CustomerComplaint $complaint,
        User $qualityManager,
        User $qualityOwner,
        CarbonImmutable $createdAt,
        CarbonImmutable $asOf,
    ): void {
        $ncr = $complaint->ncr;
        if (! $ncr instanceof NonConformanceReport || $ncr->status->isTerminal()) {
            throw new RuntimeException('The overdue customer complaint must retain its linked open NCR.');
        }
        $dueDate = $this->businessDay($asOf->subDay(), 0)->toDateString();
        $this->ensureAction($context, $ncr, NcrActionType::Containment, 'OVERDUE:CONTAINMENT', 'Request customer sample return and segregate suspect material pending laboratory review.', $qualityManager, $qualityOwner, $createdAt, $dueDate);
        $this->ensureAction($context, $ncr, NcrActionType::Corrective, 'OVERDUE:CORRECTIVE', 'Verify the molding parameter correction for the affected fitment feature.', $qualityManager, $qualityOwner, $createdAt->addMinutes(10), $dueDate);
        $this->ensureAction($context, $ncr, NcrActionType::Preventive, 'OVERDUE:PREVENTIVE', 'Add the fitment gauge check to the product control plan and operator start-up sheet.', $qualityManager, $qualityOwner, $createdAt->addMinutes(20), $dueDate);
    }

    private function resolveHistoricalComplaint(
        DemoSeedContext $context,
        CustomerComplaint $complaint,
        User $customerService,
        User $qualityManager,
        User $qualityOwner,
        CarbonImmutable $at,
        bool $close = true,
    ): void {
        $ncr = $complaint->ncr;
        if (! $ncr instanceof NonConformanceReport) {
            throw new RuntimeException('The historical customer complaint is missing its linked NCR.');
        }
        $this->ensureAction($context, $ncr, NcrActionType::Containment, 'CLOSED:CONTAINMENT', 'Customer stock was contained and traceability confirmed for the affected shipment.', $qualityManager, $qualityOwner, $at->addHours(1), $this->businessDay($at, -1));
        $this->ensureAction($context, $ncr, NcrActionType::Corrective, 'CLOSED:CORRECTIVE', 'Mold vent cleaning and process window correction were verified against the returned fitment sample.', $qualityManager, $qualityOwner, $at->addHours(2), $this->businessDay($at, -3));
        $this->ensureAction($context, $ncr, NcrActionType::Preventive, 'CLOSED:PREVENTIVE', 'The updated start-up checklist and layered audit item were released to the production team.', $qualityManager, $qualityOwner, $at->addHours(3), $this->businessDay($at, -5));

        $report = $complaint->fresh('eightDReport')->eightDReport;
        if ($report === null || $report->finalized_at === null) {
            $this->contextual($context, $at->addHours(4), $customerService, fn () => $this->complaints->update8DReport(
                $complaint->fresh(),
                [
                    'd1_team' => 'Customer Service, Quality, and Injection Production',
                    'd2_problem' => $complaint->description,
                    'd3_containment' => 'Customer stock segregated; affected lot traceability checked against delivery records.',
                    'd4_root_cause' => 'A worn mold vent increased variation at the customer fitment surface.',
                    'd5_corrective_action' => 'Clean the vent and restore the approved process window.',
                    'd6_verification' => 'Retained samples passed the fitment gauge and visual inspection.',
                    'd7_prevention' => 'Added vent-cleaning frequency and fitment check to the control plan.',
                    'd8_recognition' => 'Customer Service and Quality closed the response after customer acceptance.',
                ],
            ));
            $this->contextual($context, $at->addHours(5), $customerService, fn () => $this->complaints->finalize8D($complaint->fresh(), $customerService));
        }

        if ($ncr->disposition === null) {
            $this->contextual($context, $at->addHours(6), $qualityManager, fn () => $this->ncrs->setDisposition(
                $ncr->fresh(),
                NcrDisposition::Rework->value,
                'Mold vent wear caused the reported fitment variation.',
                'Vent restored and the validated injection process window reinstated.',
                $qualityManager,
            ));
        } elseif ($ncr->disposition !== NcrDisposition::Rework) {
            throw new RuntimeException('The historical complaint NCR has a conflicting disposition.');
        }
        if (! $ncr->fresh()->status->isTerminal()) {
            $this->contextual($context, $at->addHours(7), $qualityManager, fn () => $this->ncrs->close($ncr->fresh(), $qualityManager));
        }

        $complaint->refresh();
        if ($complaint->status === ComplaintStatus::Open) {
            $this->contextual($context, $at->addHours(8), $customerService, fn () => $this->complaints->resolve($complaint->fresh()));
        }
        if ($close && $complaint->fresh()->status === ComplaintStatus::Resolved) {
            $this->contextual($context, $at->addHours(9), $customerService, fn () => $this->complaints->close($complaint->fresh()));
        }
        $expectedStatus = $close ? ComplaintStatus::Closed : ComplaintStatus::Resolved;
        if ($complaint->fresh()->status !== $expectedStatus) {
            throw new RuntimeException("The historical customer complaint did not reach its service-supported {$expectedStatus->value} state.");
        }
    }

    private function ensureAction(
        DemoSeedContext $context,
        NonConformanceReport $ncr,
        NcrActionType $type,
        string $key,
        string $description,
        User $actor,
        User $owner,
        CarbonImmutable $performedAt,
        CarbonImmutable|string $dueDate,
    ): NcrAction {
        $markedDescription = self::PREFIX.$key.': '.$description;
        $existing = NcrAction::query()->where('ncr_id', $ncr->id)->where('description', $markedDescription)->first();
        if ($existing instanceof NcrAction) {
            return $existing;
        }

        $due = $dueDate instanceof CarbonImmutable ? $dueDate->toDateString() : $dueDate;

        return $context->at($performedAt, fn () => $context->asUser($actor, fn () => $this->ncrs->addAction($ncr->fresh(), [
            'action_type' => $type->value,
            'description' => $markedDescription,
            'performed_at' => $performedAt,
            'due_date' => $due,
            'owner_id' => $owner->id,
        ], $actor)));
    }

    private function account(string $email, string $permission): User
    {
        $user = User::query()->where('email', $email)->where('is_active', true)->first();
        if (! $user instanceof User || ! $user->hasPermission($permission)) {
            throw new RuntimeException("Quality history requires active demo actor {$email} with {$permission}.");
        }

        return $user;
    }

    private function businessDay(CarbonImmutable $asOf, int $offset): CarbonImmutable
    {
        $direction = $offset < 0 ? 1 : -1;
        $remaining = abs($offset);
        $day = $asOf->setTimezone('Asia/Manila')->startOfDay();
        while (! $this->isBusinessDay($day)) {
            $day = $day->addDays($direction);
        }
        while ($remaining > 0) {
            $day = $day->addDays($direction);
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
            && ! Holiday::query()->where('is_recurring', true)->whereRaw("to_char(date, 'MM-DD') = ?", [$day->format('m-d')])->exists();
    }

    /** @param \Closure(): mixed $action */
    private function contextual(DemoSeedContext $context, CarbonImmutable $at, User $user, \Closure $action): mixed
    {
        return $context->at($at, fn () => $context->asUser($user, $action));
    }
}
