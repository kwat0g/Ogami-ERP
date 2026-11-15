<?php

declare(strict_types=1);

namespace Database\Seeders\Demo;

use App\Common\DemoData\DemoSeedContext;
use App\Common\DemoData\DemoSeeder;
use App\Common\Exceptions\BusinessRuleException;
use App\Modules\Accounting\Models\Customer;
use App\Modules\Auth\Models\User;
use App\Modules\B2B\Models\CustomerPortalUser;
use App\Modules\B2B\Services\CustomerPortalService;
use App\Modules\CRM\Models\Product;
use App\Modules\CRM\Models\SalesOrderItem;
use App\Modules\CRM\Services\SalesOrderSupplyService;
use App\Modules\HR\Models\Employee;
use App\Modules\Inventory\Controllers\MaterialHandoverController;
use App\Modules\Inventory\Models\Item;
use App\Modules\Inventory\Models\StockMovement;
use App\Modules\MRP\Services\CapacityPlanningService;
use App\Modules\Production\Enums\WorkOrderStatus;
use App\Modules\Production\Events\WorkOrderCompleted;
use App\Modules\Production\Models\ProductionSchedule;
use App\Modules\Production\Models\WoOperation;
use App\Modules\Production\Models\WorkOrder;
use App\Modules\Production\Models\WorkOrderCrew;
use App\Modules\Production\Models\WorkOrderOutput;
use App\Modules\Production\Services\StockProductionPlanningService;
use App\Modules\Production\Services\WorkOrderAssignmentService;
use App\Modules\Production\Services\WorkOrderOutputService;
use App\Modules\Production\Services\WorkOrderService;
use App\Modules\Production\Services\WoOperationService;
use App\Modules\Quality\Enums\InspectionEntityType;
use App\Modules\Quality\Enums\InspectionStage;
use App\Modules\Quality\Enums\InspectionStatus;
use App\Modules\Quality\Models\Inspection;
use App\Modules\Quality\Models\InspectionMeasurement;
use App\Modules\Quality\Services\InspectionService;
use App\Modules\Quality\Services\WorkOrderFinalInspectionService;
use App\Modules\Quality\Listeners\TriggerOutgoingQC;
use App\Modules\Attendance\Services\HolidayService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use RuntimeException;

/** Creates traceable, QC-released stock for every finished good with on-hand inventory. */
final class FinishedGoodsOrderableStockLayer extends DemoSeeder
{
    /** @var array<string, int> */
    private const SELLABLE_TARGETS = ['small' => 80, 'demo' => 250, 'full' => 500];

    /** @var list<string> */
    private const PORTAL_CUSTOMERS = [
        'Mitsuba Philippines Corp. Plant 3',
        'Mitsuba Philippines Corp. Plant 2',
        'Mitsuba Philippines Corp. Plant 1',
        'Hiblow Philippines Inc.',
        'Sanac Philippines Inc.',
        'Yazaki EDS Manufacturing, Inc.',
        'Yazaki-Torres Manufacturing Inc.',
        'YGP PTE. LTD. Yazaki Global Purchasing',
        'OGAMI CORPORATION JAPAN',
        'Honda Philippines Inc.',
    ];

    public function __construct(
        private readonly StockProductionPlanningService $stockProduction,
        private readonly CapacityPlanningService $capacity,
        private readonly WorkOrderAssignmentService $assignments,
        private readonly WorkOrderService $workOrders,
        private readonly WoOperationService $operations,
        private readonly WorkOrderOutputService $outputs,
        private readonly InspectionService $inspections,
        private readonly WorkOrderFinalInspectionService $finalInspections,
        private readonly TriggerOutgoingQC $outgoingQc,
        private readonly MaterialHandoverController $handovers,
        private readonly SalesOrderSupplyService $salesSupply,
        private readonly CustomerPortalService $customerPortal,
        private readonly HolidayService $holidays,
    ) {}

    public function key(): string
    {
        return 'finished-goods-orderable-stock';
    }

    /** @return list<string> */
    public function modules(): array
    {
        return ['Inventory', 'Production', 'Quality', 'CRM', 'B2B'];
    }

    /** @return list<string> */
    public function dependencies(): array
    {
        return ['production-execution-history'];
    }

    protected function seedDemoData(DemoSeedContext $context): void
    {
        $targetByScale = self::SELLABLE_TARGETS[$context->options->scale]
            ?? throw new RuntimeException('Unsupported scale for finished-good orderable stock.');
        $asOf = $context->options->asOf->setTimezone('Asia/Manila')->startOfDay();
        $items = Item::query()
            ->active()
            ->where('item_type', 'finished_good')
            ->whereHas('stockLevels', fn ($levels) => $levels->where('quantity', '>', 0))
            ->orderBy('code')
            ->get();
        if ($items->isEmpty()) {
            throw new RuntimeException('Finished-good orderable stock requires at least one active finished-good item with positive on-hand stock.');
        }

        $customers = Customer::query()->active()->whereIn('name', self::PORTAL_CUSTOMERS)->orderBy('code')->get();
        if ($customers->count() !== count(self::PORTAL_CUSTOMERS)) {
            throw new RuntimeException('Finished-good orderability requires all ten requested active customer accounts.');
        }
        foreach ($customers as $customer) {
            if (! CustomerPortalUser::query()->where('customer_id', $customer->id)->where('is_active', true)->exists()) {
                throw new RuntimeException("Customer {$customer->name} has no active portal login for orderability verification.");
            }
        }

        $products = Product::query()->active()
            ->whereIn('part_number', $items->pluck('code'))
            ->with(['inspectionSpec.currentRevision', 'inspectionSpec.items'])
            ->orderBy('part_number')
            ->get()
            ->keyBy('part_number');
        $missingProducts = $items->pluck('code')->diff($products->keys())->values()->all();
        if ($missingProducts !== []) {
            throw new RuntimeException('Finished-good stock has no active product mapping for: '.implode(', ', $missingProducts));
        }

        foreach ($products as $product) {
            $readiness = $this->stockProduction->productReadiness($product);
            if (! $readiness['eligible']) {
                throw new BusinessRuleException("{$product->part_number} cannot be released as orderable stock: ".implode(' ', $readiness['blockers']));
            }
        }

        $planner = $this->actor('production.wo.create', 'mrp.plans.run');
        $productionManager = $this->actor('production.work_orders.lifecycle', 'production.wo.record');
        $warehouse = $this->actor('inventory.issue.create');
        $qcMaker = $this->actor('quality.inspections.manage');
        $qcChecker = $this->actor('quality.inspections.review', excluding: (int) $qcMaker->id);
        $dates = $this->recentWorkingDates($asOf, max(1, (int) ceil($products->count() / 6)));

        foreach ($products->values() as $index => $product) {
            /** @var Item $item */
            $item = $items->firstWhere('code', $product->part_number);
            $productionDay = $dates[(int) floor($index / 6)];
            $previousDay = $this->previousWorkingDay($productionDay);
            $createdAt = $previousDay->setTime(15, 0);
            $startAt = $productionDay->setTime(8, 0);
            $endAt = $productionDay->setTime(17, 0);
            $target = $targetByScale + (int) ceil((float) $item->safety_stock);
            if ($target > 5000) {
                throw new BusinessRuleException("Finished-good {$item->code} needs {$target} pieces to retain safety stock and the sellable target; stock production is capped at 5,000 pieces per work order.");
            }

            $workOrder = $context->withoutNotifications(fn () => $context->at($createdAt, fn () => $context->asUser($planner, fn (): WorkOrder => $this->stockProduction->create([
                'request_key' => $this->requestKey($item->code, $asOf),
                'product_id' => (int) $product->id,
                'quantity_target' => $target,
                'planned_start' => $startAt->toDateTimeString(),
                'planned_end' => $endAt->toDateTimeString(),
                'preferred_production_date' => $productionDay->toDateString(),
                'minimum_workers' => 1,
                'maximum_workers' => 2,
            ], $planner))));

            $this->completeStockWorkOrder(
                $context,
                $workOrder,
                $product,
                $item,
                $planner,
                $productionManager,
                $warehouse,
                $qcMaker,
                $qcChecker,
                $createdAt,
                $target,
            );

            $this->assertOrderable($product, $targetByScale);
            $context->records()->remember('inventory.orderable-stock.'.$item->code, $workOrder->fresh());
            $this->command?->info("{$item->code}: created or verified {$target} QC-released stock pieces.");
        }

        $this->assertCustomerPortals($products->values(), $customers, $asOf);
    }

    /**
     * Verify every existing customer-specific catalog entry through the same
     * service used by portal ordering. This deliberately preserves each
     * customer's configured product scope while ensuring every finished good
     * is orderable by at least one of its authorized customers.
     *
     * @param  Collection<int, Product>  $products
     * @param  Collection<int, Customer>  $customers
     */
    private function assertCustomerPortals(Collection $products, Collection $customers, CarbonImmutable $asOf): void
    {
        $expectedProducts = $products->keyBy('part_number');
        $coveredParts = [];

        foreach ($customers as $customer) {
            $catalog = collect($this->customerPortal->catalog((int) $customer->id, $asOf->toDateString()))
                ->keyBy('part_number');
            $authorizedParts = $customer->portalProducts()->whereIn('products.id', $products->pluck('id'))
                ->orderBy('products.part_number')->pluck('products.part_number')->map(static fn ($part): string => (string) $part);
            $missing = $authorizedParts->diff($catalog->keys())->values()->all();
            if ($missing !== []) {
                throw new RuntimeException("Customer portal {$customer->code} is missing its authorized finished goods: ".implode(', ', $missing));
            }

            foreach ($authorizedParts as $partNumber) {
                $coveredParts[$partNumber] = true;
                $entry = $catalog->get($partNumber);
                $available = (string) ($entry['available_quantity'] ?? '0.000');
                if (bccomp($available, '0.000', 3) <= 0) {
                    throw new RuntimeException("Customer portal {$customer->code} cannot order {$partNumber}: sellable quantity is {$available}.");
                }
                if (($entry['unit_price'] ?? null) === null || ($entry['pricing_issue'] ?? null) !== null) {
                    throw new RuntimeException("Customer portal {$customer->code} cannot order {$partNumber}: no valid selling price is available for {$asOf->toDateString()}.");
                }
            }
        }

        $uncovered = $expectedProducts->keys()->diff(array_keys($coveredParts))->values()->all();
        if ($uncovered !== []) {
            throw new RuntimeException('These stocked finished goods are absent from every requested customer portal: '.implode(', ', $uncovered));
        }
    }

    private function completeStockWorkOrder(
        DemoSeedContext $context,
        WorkOrder $workOrder,
        Product $product,
        Item $item,
        User $planner,
        User $productionManager,
        User $warehouse,
        User $qcMaker,
        User $qcChecker,
        CarbonImmutable $createdAt,
        int $target,
    ): void {
        $workOrder->refresh();
        if (in_array($workOrder->status, [WorkOrderStatus::Completed, WorkOrderStatus::Closed], true)) {
            $this->ensureOutgoingRelease($context, $workOrder, $qcMaker, $qcChecker);

            return;
        }
        if (! in_array($workOrder->status, [WorkOrderStatus::Planned, WorkOrderStatus::Confirmed, WorkOrderStatus::InProgress], true)) {
            throw new RuntimeException("Orderable-stock work order {$workOrder->wo_number} is in unsupported state {$workOrder->status->value}.");
        }

        $slot = $this->ensureSchedule($context, $workOrder, $createdAt->addMinutes(10));
        $crew = $this->ensureCrew($context, $workOrder, $slot, $productionManager);
        $this->ensureConfirmed($context, $workOrder, $slot, $planner);
        $this->ensureStarted($context, $workOrder, $slot, $crew, $productionManager);
        $this->ensureMaterialHandovers($context, $workOrder, $crew, $warehouse);

        $start = $slot->scheduled_start->toImmutable();
        $this->passStartInspection($context, $workOrder, $qcMaker, $qcChecker, $start->addMinutes(5));
        $operationStart = $start->addMinutes(15);
        $this->runOperations($context, $workOrder, $crew, $productionManager, $qcMaker, $qcChecker, $operationStart, $product->part_number);
        $outputAt = $operationStart->addMinutes(max(1, $workOrder->operations()->count()) * 12 + 20);
        $output = $workOrder->outputs()->where('idempotency_key', $this->outputKey($item->code, $context))->first();
        if (! $output instanceof WorkOrderOutput) {
            $outputPayload = [
                'crew_id' => (int) $crew->id,
                'recording_reason' => "Production records the completed {$product->part_number} stock replenishment batch.",
                'remarks' => 'Completed stock batch routed to outgoing inspection before customer allocation.',
            ];
            if ($workOrder->usesFinishFirstQc()) {
                $outputPayload['produced_count'] = $target;
            } else {
                $outputPayload += ['good_count' => $target, 'reject_count' => 0];
            }
            $output = $context->withoutNotifications(fn () => $context->at($outputAt, fn () => $context->asUser($productionManager, fn (): WorkOrderOutput => $this->outputs->record(
                $workOrder->fresh(),
                $outputPayload,
                (int) $productionManager->id,
                $this->outputKey($item->code, $context),
            ))));
        }
        $output = $output->fresh();
        if ($workOrder->fresh()->status === WorkOrderStatus::InProgress) {
            $context->withoutNotifications(fn () => $context->at($outputAt->addMinutes(2), fn () => $context->asUser($productionManager, fn () => $this->workOrders->complete($workOrder->fresh()))));
        }
        if (! in_array($workOrder->fresh()->status, [WorkOrderStatus::Completed, WorkOrderStatus::Closed], true)) {
            throw new RuntimeException("Orderable-stock work order {$workOrder->wo_number} did not complete after its production output.");
        }

        $this->ensureOutgoingRelease($context, $workOrder->fresh(), $qcMaker, $qcChecker, $outputAt->addMinutes(5));
    }

    private function ensureSchedule(DemoSeedContext $context, WorkOrder $workOrder, CarbonImmutable $at): ProductionSchedule
    {
        $schedule = $workOrder->schedules()->where('reserves_capacity', true)
            ->whereIn('status', ['pending', 'confirmed', 'executed'])->latest('id')->first();
        if ($schedule instanceof ProductionSchedule) {
            return $schedule;
        }
        if ($workOrder->status !== WorkOrderStatus::Planned) {
            throw new RuntimeException("Orderable-stock work order {$workOrder->wo_number} has no capacity schedule for its current state.");
        }

        $context->withoutNotifications(fn () => $context->at($at, fn () => $this->capacity->run([(int) $workOrder->id])));
        $schedule = $workOrder->schedules()->where('reserves_capacity', true)->where('status', 'pending')->latest('id')->first();
        if (! $schedule instanceof ProductionSchedule) {
            $reasons = collect($workOrder->fresh()->scheduling_diagnostic['reasons'] ?? [])->implode(' ');
            throw new RuntimeException("Capacity planning could not schedule {$workOrder->wo_number}: ".($reasons !== '' ? $reasons : 'No capacity slot was returned.'));
        }

        return $schedule;
    }

    private function ensureCrew(DemoSeedContext $context, WorkOrder $workOrder, ProductionSchedule $slot, User $manager): WorkOrderCrew
    {
        $crew = $this->assignments->currentCrew($workOrder);
        if ($crew instanceof WorkOrderCrew) {
            return $crew;
        }
        $worker = $this->assignments->eligibleWorkers($workOrder, $slot->scheduled_start, $slot->scheduled_end)->first();
        if (! $worker instanceof Employee) {
            throw new RuntimeException("No eligible production employee is available for {$workOrder->wo_number}.");
        }

        return $context->withoutNotifications(fn () => $context->at($slot->scheduled_start->toImmutable(), fn () => $context->asUser($manager, fn (): WorkOrderCrew => $this->assignments->assign($workOrder, [
            'employee_ids' => [(int) $worker->id],
            'recorder_employee_id' => (int) $worker->id,
            'minimum_workers' => 1,
            'maximum_workers' => 2,
            'expected_crew_id' => null,
            'reason' => "Production crew assigned to the {$workOrder->product->part_number} stock batch.",
        ], $manager))));
    }

    private function ensureConfirmed(DemoSeedContext $context, WorkOrder $workOrder, ProductionSchedule $slot, User $planner): void
    {
        if (in_array($workOrder->status, [WorkOrderStatus::Confirmed, WorkOrderStatus::InProgress], true)) {
            return;
        }
        if ($workOrder->status !== WorkOrderStatus::Planned) {
            throw new RuntimeException("{$workOrder->wo_number} cannot be confirmed from {$workOrder->status->value}.");
        }
        $context->withoutNotifications(fn () => $context->at($slot->scheduled_start->toImmutable(), fn () => $context->asUser($planner, fn () => $this->capacity->confirm([(int) $slot->id], (int) $planner->id))));
        $workOrder->refresh();
    }

    private function ensureStarted(DemoSeedContext $context, WorkOrder $workOrder, ProductionSchedule $slot, WorkOrderCrew $crew, User $manager): void
    {
        if ($workOrder->status === WorkOrderStatus::InProgress) {
            return;
        }
        if ($workOrder->status !== WorkOrderStatus::Confirmed) {
            throw new RuntimeException("{$workOrder->wo_number} cannot start from {$workOrder->status->value}.");
        }
        $memberIds = $crew->members()->pluck('employee_id')->map(static fn ($id): int => (int) $id)->all();
        $context->withoutNotifications(fn () => $context->at($slot->scheduled_start->toImmutable(), fn () => $context->asUser($manager, fn () => $this->workOrders->start(
            $workOrder->fresh(),
            (int) $manager->id,
            ['expected_crew_id' => (int) $crew->id, 'presence_confirmed_employee_ids' => $memberIds],
        ))));
        $workOrder->refresh();
    }

    private function ensureMaterialHandovers(DemoSeedContext $context, WorkOrder $workOrder, WorkOrderCrew $crew, User $warehouse): void
    {
        $recipientId = (int) $crew->recorder_employee_id;
        StockMovement::query()->forWorkOrder((int) $workOrder->id)->pendingHandover()->orderBy('id')->get()
            ->each(function (StockMovement $movement) use ($context, $warehouse, $recipientId): void {
                $at = $movement->created_at->toImmutable()->addMinutes(2);
                $request = Request::create('/api/v1/inventory/stock-movements/'.$movement->hash_id.'/handover', 'POST', [
                    'received_by_employee_id' => app('hashids')->encode($recipientId),
                    'expected_quantity' => bcadd((string) $movement->quantity, '0', 3),
                ]);
                $request->setUserResolver(static fn (): User => $warehouse);
                $context->withoutNotifications(fn () => $context->at($at, fn () => $context->asUser($warehouse, fn () => $this->handovers->store($request, $movement))));
            });
    }

    private function passStartInspection(DemoSeedContext $context, WorkOrder $workOrder, User $maker, User $checker, CarbonImmutable $at): void
    {
        if ($workOrder->usesFinishFirstQc()) {
            return;
        }
        $inspection = Inspection::query()->where('stage', InspectionStage::InProcess->value)
            ->where('entity_type', InspectionEntityType::WorkOrder->value)->where('entity_id', $workOrder->id)
            ->whereNull('wo_operation_id')->whereNull('work_order_output_id')
            ->where('status', '!=', InspectionStatus::Cancelled->value)->first();
        if ($inspection instanceof Inspection) {
            $this->passInspection($context, $inspection, $maker, $checker, $at);
        }
    }

    private function runOperations(
        DemoSeedContext $context,
        WorkOrder $workOrder,
        WorkOrderCrew $crew,
        User $productionManager,
        User $maker,
        User $checker,
        CarbonImmutable $at,
        string $partNumber,
    ): void {
        $operator = $crew->members()->with('employee')->first()?->employee;
        if (! $operator instanceof Employee) {
            throw new RuntimeException("The production crew for {$workOrder->wo_number} has no employee operator.");
        }

        foreach ($workOrder->operations()->with('routingOperation')->orderBy('sequence')->get() as $index => $operation) {
            $stepAt = $at->addMinutes($index * 12);
            if (in_array($operation->status->value, ['completed', 'skipped'], true)) {
                continue;
            }
            if ($operation->status->value === 'pending' && bccomp((string) $operation->routingOperation?->setup_time_minutes, '0', 2) > 0) {
                $context->withoutNotifications(fn () => $context->at($stepAt, fn () => $context->asUser($productionManager, fn () => $this->operations->startSetup($operation, $operator))));
                $context->withoutNotifications(fn () => $context->at($stepAt->addMinutes(2), fn () => $this->operations->endSetup($operation->fresh())));
                $operation->refresh();
            }
            if (in_array($operation->status->value, ['pending', 'setup'], true)) {
                $context->withoutNotifications(fn () => $context->at($stepAt->addMinutes(3), fn () => $context->asUser($productionManager, fn () => $this->operations->startOperation($operation->fresh(), $operator))));
                $operation->refresh();
            }
            if ($operation->status->value === 'in_progress' && bccomp((string) $operation->qty_completed, (string) $workOrder->quantity_target, 4) < 0) {
                $context->withoutNotifications(fn () => $context->at($stepAt->addMinutes(5), fn () => $context->asUser($productionManager, fn () => $this->operations->recordOutput(
                    $operation,
                    (float) $workOrder->quantity_target,
                    0,
                    null,
                    'Operator records the verified completed quantity at this routing step.',
                ))));
            }
            if ($operation->routingOperation?->qc_required) {
                $inspection = Inspection::query()->where('wo_operation_id', $operation->id)
                    ->where('status', '!=', InspectionStatus::Cancelled->value)->first();
                if (! $inspection instanceof Inspection) {
                    $inspection = $this->inspections->create([
                        'stage' => InspectionStage::InProcess->value,
                        'product_id' => (int) $workOrder->product_id,
                        'batch_quantity' => (int) $operation->qty_completed,
                        'entity_type' => InspectionEntityType::WorkOrder->value,
                        'entity_id' => (int) $workOrder->id,
                        'wo_operation_id' => (int) $operation->id,
                        'notes' => "Inspection required by the active {$partNumber} routing operation.",
                    ], $workOrder->creator);
                }
                $this->passInspection($context, $inspection, $maker, $checker, $stepAt->addMinutes(8));
            }
            if ($operation->fresh()->status->value === 'in_progress') {
                $context->withoutNotifications(fn () => $context->at($stepAt->addMinutes(10), fn () => $this->operations->completeOperation($operation->fresh())));
            }
        }
    }

    private function passInspection(DemoSeedContext $context, Inspection $inspection, User $maker, User $checker, CarbonImmutable $at): void
    {
        $inspection = $inspection->fresh(['measurements']);
        if (in_array($inspection->status, [InspectionStatus::Passed, InspectionStatus::Failed], true)) {
            return;
        }
        if ($inspection->usesConfirmedChecks()) {
            $this->passConfirmedFinalInspection($context, $inspection, $maker, $checker, $at);

            return;
        }
        if ($inspection->status !== InspectionStatus::AwaitingReview) {
            $rows = InspectionMeasurement::query()->where('inspection_id', $inspection->id)->orderBy('sample_index')->orderBy('id')->get();
            if ($rows->isEmpty()) {
                throw new RuntimeException("Inspection {$inspection->inspection_number} has no specification measurements.");
            }
            if ($inspection->usesChecklistCapture()) {
                $items = $rows->groupBy(static fn (InspectionMeasurement $row): string => $row->checklistKey())
                    ->map(static fn ($group): array => ['id' => (int) $group->first()->id, 'result' => 'passed'])
                    ->values()->all();
                $context->withoutNotifications(fn () => $context->at($at, fn () => $context->asUser($maker, fn () => $this->inspections->recordItemResults(
                    $inspection,
                    ['items' => $items, 'submit' => false],
                    $maker,
                ))));
                $inspection->refresh();
                $context->withoutNotifications(fn () => $context->at($at->addMinute(), fn () => $context->asUser($maker, fn () => $this->inspections->recordBatchResult(
                    $inspection,
                    [
                        'coverage_confirmed' => true,
                        'inspected_quantity' => min((int) $inspection->batch_quantity, max(1, (int) $inspection->sample_size)),
                        'sample_defect_count' => 0,
                        'result' => 'passed',
                        'critical_defect' => false,
                    ],
                    $maker,
                ))));
            } else {
                $patches = [];
                foreach ($rows as $row) {
                    $patch = ['is_pass' => true];
                    if ($row->requiresSizeReading()) {
                        if ($row->nominal_value === null) {
                            throw new RuntimeException("Inspection measurement {$row->id} requires a reading but has no nominal value.");
                        }
                        $patch['measured_value'] = (string) $row->nominal_value;
                    }
                    $patches[(int) $row->id] = $patch;
                }
                $context->withoutNotifications(fn () => $context->at($at, fn () => $context->asUser($maker, function () use ($inspection, $patches, $maker): void {
                    $this->inspections->recordMeasurements($inspection, $patches, $maker);
                    $this->inspections->complete($inspection->fresh(), $maker);
                })));
            }
            $inspection->refresh();
        }
        if ($inspection->status === InspectionStatus::AwaitingReview) {
            $decision = $inspection->proposed_result?->value ?? (string) $inspection->proposed_result;
            $context->withoutNotifications(fn () => $context->at($at->addMinutes(2), fn () => $context->asUser($checker, fn () => $this->inspections->review(
                $inspection,
                $decision,
                null,
                $checker,
                (int) $inspection->result_version,
            ))));
            $inspection->refresh();
        }
        if ($inspection->status !== InspectionStatus::Passed) {
            throw new RuntimeException("Inspection {$inspection->inspection_number} did not pass recorded evidence.");
        }
    }

    private function passConfirmedFinalInspection(DemoSeedContext $context, Inspection $inspection, User $maker, User $checker, CarbonImmutable $at): void
    {
        $output = $inspection->workOrderOutput()->with('workOrder')->first();
        $workOrder = $output?->workOrder;
        if (! $workOrder instanceof WorkOrder || $inspection->stage !== InspectionStage::Outgoing) {
            throw new RuntimeException("Confirmed-check inspection {$inspection->inspection_number} is not linked to a completed outgoing-QC work order.");
        }

        $state = $this->finalInspections->show($workOrder, $maker);
        if (($state['blocker'] ?? null) !== null) {
            throw new RuntimeException("Final QC for {$workOrder->wo_number} is blocked: {$state['blocker']}");
        }
        if ($inspection->status !== InspectionStatus::AwaitingReview) {
            $outputs = $workOrder->outputs()->whereNull('rework_ncr_id')->where('good_count', '>', 0)->pluck('id');
            $openInspections = Inspection::query()->whereIn('work_order_output_id', $outputs)
                ->where('stage', InspectionStage::Outgoing->value)
                ->whereIn('status', [InspectionStatus::Draft->value, InspectionStatus::InProgress->value])
                ->orderBy('id')->get();
            if ($openInspections->isEmpty()) {
                throw new RuntimeException("Final QC for {$workOrder->wo_number} has no open batch to submit.");
            }
            $batches = $openInspections->map(static fn (Inspection $row): array => [
                'inspection_id' => $row->hash_id,
                'coverage_confirmed' => true,
                'inspected_quantity' => (int) $row->batch_quantity,
                'checks_confirmed' => true,
            ])->values()->all();
            $context->withoutNotifications(fn () => $context->at($at, fn () => $context->asUser($maker, fn () => $this->finalInspections->submit(
                $workOrder,
                ['manifest' => $state['manifest'], 'batches' => $batches],
                $maker,
            ))));
            $inspection->refresh();
        }

        if ($inspection->status === InspectionStatus::AwaitingReview) {
            $state = $this->finalInspections->show($workOrder, $maker);
            $context->withoutNotifications(fn () => $context->at($at->addMinutes(2), fn () => $context->asUser($checker, fn () => $this->finalInspections->review(
                $workOrder,
                ['manifest' => $state['manifest']],
                $checker,
            ))));
            $inspection->refresh();
        }

        if ($inspection->status !== InspectionStatus::Passed) {
            throw new RuntimeException("Confirmed-check outgoing inspection {$inspection->inspection_number} did not pass its final-QC workflow.");
        }
    }

    private function ensureOutgoingRelease(
        DemoSeedContext $context,
        WorkOrder $workOrder,
        User $maker,
        User $checker,
        ?CarbonImmutable $at = null,
    ): void {
        $workOrder = $workOrder->fresh();
        if ($workOrder->status === WorkOrderStatus::Completed) {
            $when = $at ?? $workOrder->actual_end?->toImmutable()->addMinutes(1) ?? now()->toImmutable();
            $context->withoutNotifications(fn () => $context->at($when, fn () => $this->outgoingQc->handle(new WorkOrderCompleted($workOrder))));
        }

        $outputs = $workOrder->outputs()->whereNull('rework_ncr_id')->where('good_count', '>', 0)->orderBy('id')->get();
        if ($outputs->isEmpty()) {
            throw new RuntimeException("Completed orderable-stock work order {$workOrder->wo_number} has no good production output.");
        }
        foreach ($outputs as $output) {
            $inspection = Inspection::query()->where('work_order_output_id', $output->id)
                ->where('stage', InspectionStage::Outgoing->value)->where('status', '!=', InspectionStatus::Cancelled->value)
                ->latest('id')->first();
            if (! $inspection instanceof Inspection) {
                $inspection = $this->finalInspections->ensureForCompletedWorkOrder($workOrder, $maker)
                    ->first(fn (Inspection $row): bool => (int) $row->work_order_output_id === (int) $output->id);
            }
            if (! $inspection instanceof Inspection) {
                throw new RuntimeException("No outgoing inspection was created for {$output->batch_code}.");
            }
            $this->passInspection($context, $inspection, $maker, $checker, $at ?? $inspection->created_at->toImmutable()->addMinutes(1));
            $inspection->refresh();
            if (! $inspection->canReleaseOutgoing()) {
                throw new RuntimeException("Outgoing inspection {$inspection->inspection_number} passed without a releasable quantity.");
            }
        }
    }

    private function assertOrderable(Product $product, int $target): void
    {
        $line = new SalesOrderItem([
            'product_id' => (int) $product->id,
            'quantity' => '1',
            'fulfillment_policy' => 'stock_first',
        ]);
        $line->setRelation('product', $product);
        $available = array_reduce($this->salesSupply->sources($line), static fn (string $sum, array $source): string => bcadd($sum, (string) $source['quantity'], 3), '0.000');
        if (bccomp($available, '0.000', 3) <= 0) {
            throw new RuntimeException("{$product->part_number} has no QC-released, unreserved quantity available for customer orders after its stock batch was completed.");
        }
        if (bccomp($available, (string) $target, 3) < 0) {
            $this->command?->warn("{$product->part_number}: {$available} pieces are available after retained safety stock and existing commitments; the target was {$target}.");
        }
    }

    private function actor(string $permission, ?string $secondPermission = null, ?int $excluding = null): User
    {
        $query = User::query()->where('is_active', true)
            ->whereHas('role.permissions', static fn ($permissions) => $permissions->where('slug', $permission));
        if ($secondPermission !== null) {
            $query->whereHas('role.permissions', static fn ($permissions) => $permissions->where('slug', $secondPermission));
        }
        if ($excluding !== null) {
            $query->whereKeyNot($excluding);
        }

        return $query->orderBy('id')->first()
            ?? throw new RuntimeException('Finished-good stock release requires an active actor with '.$permission.($secondPermission ? ' and '.$secondPermission : '').'.');
    }

    /** @return list<CarbonImmutable> Oldest first, excluding weekends and app holidays. */
    private function recentWorkingDates(CarbonImmutable $asOf, int $limit): array
    {
        $dates = [];
        $cursor = $asOf->subDay();
        $holidayDates = $this->holidays->datesBetween($cursor->subDays(max(30, $limit * 4)), $cursor);
        while (count($dates) < $limit) {
            if ($cursor->dayOfWeekIso <= 5 && ! isset($holidayDates[$cursor->toDateString()])) {
                $dates[] = $cursor;
            }
            $cursor = $cursor->subDay();
        }

        return array_reverse($dates);
    }

    private function previousWorkingDay(CarbonImmutable $date): CarbonImmutable
    {
        $dates = $this->recentWorkingDates($date, 1);

        return $dates[0];
    }

    private function requestKey(string $partNumber, CarbonImmutable $asOf): string
    {
        $hex = substr(hash('sha256', 'ogami-finished-good-orderable-stock:'.$asOf->toDateString().':'.$partNumber), 0, 32);
        $hex[12] = '5';
        $hex[16] = 'a';

        return substr($hex, 0, 8).'-'.substr($hex, 8, 4).'-'.substr($hex, 12, 4).'-'.substr($hex, 16, 4).'-'.substr($hex, 20, 12);
    }

    private function outputKey(string $partNumber, DemoSeedContext $context): string
    {
        return 'ogi-orderable-stock-'.strtolower($partNumber).'-'.$context->options->asOf->format('Ymd');
    }
}
