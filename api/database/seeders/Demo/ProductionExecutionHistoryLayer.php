<?php

declare(strict_types=1);

namespace Database\Seeders\Demo;

use App\Common\DemoData\DemoSeedContext;
use App\Common\DemoData\DemoSeeder;
use App\Modules\Attendance\Models\Holiday;
use App\Modules\Auth\Models\User;
use App\Modules\CRM\Models\Product;
use App\Modules\HR\Models\Employee;
use App\Modules\Inventory\Controllers\MaterialHandoverController;
use App\Modules\Inventory\Models\StockMovement;
use App\Modules\MRP\Services\CapacityPlanningService;
use App\Modules\Production\Enums\WorkOrderStatus;
use App\Modules\Production\Models\ProductionSchedule;
use App\Modules\Production\Models\WorkOrder;
use App\Modules\Production\Models\WorkOrderCrew;
use App\Modules\Production\Models\WorkOrderOutput;
use App\Modules\Production\Services\StockProductionPlanningService;
use App\Modules\Production\Services\WorkOrderAssignmentService;
use App\Modules\Production\Services\WorkOrderOutputService;
use App\Modules\Production\Services\WorkOrderService;
use App\Modules\Quality\Enums\InspectionEntityType;
use App\Modules\Quality\Enums\InspectionStage;
use App\Modules\Quality\Enums\InspectionStatus;
use App\Modules\Quality\Models\Inspection;
use App\Modules\Quality\Models\InspectionMeasurement;
use App\Modules\Quality\Services\InspectionService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use RuntimeException;

/** Seeds one historical, partially completed production run through current services. */
final class ProductionExecutionHistoryLayer extends DemoSeeder
{
    private const REQUEST_KEY = '6fbeec8d-0000-5000-8000-000000000004';

    private const OUTPUT_KEY = 'demo-production-execution-fc001-batch-1';

    public function __construct(
        private readonly StockProductionPlanningService $stockProduction,
        private readonly CapacityPlanningService $capacity,
        private readonly WorkOrderService $workOrders,
        private readonly WorkOrderAssignmentService $assignments,
        private readonly WorkOrderOutputService $outputs,
        private readonly InspectionService $inspections,
        private readonly MaterialHandoverController $handovers,
    ) {}

    public function key(): string
    {
        return 'production-execution-history';
    }

    /** @return list<string> */
    public function modules(): array
    {
        return ['Production', 'Inventory', 'Quality', 'Dashboard'];
    }

    /** @return list<string> */
    public function dependencies(): array
    {
        return ['production-history'];
    }

    protected function seedDemoData(DemoSeedContext $context): void
    {
        $asOf = $context->options->asOf->setTimezone('Asia/Manila')->startOfDay();
        $planner = $this->account('ppc.head@accounts.ogami-demo.test', 'production.wo.create');
        $manager = $this->account('production.manager@accounts.ogami-demo.test', 'production.work_orders.lifecycle');
        $managerNeedsOutputPermission = $manager->hasPermission('production.wo.record');
        if (! $managerNeedsOutputPermission) {
            throw new RuntimeException('Production execution history requires the production manager to hold production.wo.record.');
        }
        $qc = $this->account('qc.inspector@accounts.ogami-demo.test', 'quality.inspections.manage');
        $warehouse = $this->account('warehouse.staff@accounts.ogami-demo.test', 'inventory.issue.create');
        $product = Product::query()->where('part_number', 'FC-001')->where('is_active', true)->first();
        if (! $product instanceof Product) {
            throw new RuntimeException('Production execution history requires the active FC-001 fuse-box cover product.');
        }

        $start = $this->businessDay($asOf, 35)->setTime(8, 0);
        $createdAt = $this->businessDay($start, 1)->setTime(9, 0);
        $deadline = $this->businessDay($start, -5)->setTime(17, 0);
        $workOrder = $this->ensureWorkOrder($context, $planner, $product, $createdAt, $start, $deadline);
        $slot = $this->ensureSchedule($context, $workOrder, $start);
        $crew = $this->ensureCrew($context, $workOrder, $slot, $manager);
        $this->ensureConfirmed($context, $workOrder, $slot, $planner);
        $this->ensureStarted($context, $workOrder, $slot, $crew, $manager);
        $this->ensureMaterialHandovers($context, $workOrder, $crew, $warehouse);
        $this->ensurePassingInProcessInspection($context, $workOrder, $crew, $manager, $qc);
        $output = $this->ensureOutput($context, $workOrder, $crew, $manager, $start->addMinutes(45));

        if ($workOrder->fresh()->status !== WorkOrderStatus::InProgress
            || (int) $workOrder->fresh()->quantity_good >= (int) $workOrder->quantity_target
            || (int) $output->good_count !== ($workOrder->usesFinishFirstQc() ? 80 : 75)
            || $output->production_receipt_movement_id === null) {
            throw new RuntimeException('The production execution scenario must remain an 80-piece, traceable partial run.');
        }

        $context->records()->remember('production.execution.partial', $workOrder->fresh());
        $context->records()->remember('production.execution.output', $output->fresh());
    }

    private function ensureWorkOrder(
        DemoSeedContext $context,
        User $planner,
        Product $product,
        CarbonImmutable $createdAt,
        CarbonImmutable $start,
        CarbonImmutable $deadline,
    ): WorkOrder {
        $existing = WorkOrder::query()->where('stock_creation_request_key', self::REQUEST_KEY)->first();
        if ($existing instanceof WorkOrder) {
            return $existing;
        }

        return $context->withoutNotifications(fn () => $context->at($createdAt, fn () => $context->asUser($planner, fn () => $this->stockProduction->create([
            'request_key' => self::REQUEST_KEY,
            'product_id' => (int) $product->id,
            // PKG-001 is a bulk bag at 0.0200 PCS per finished piece; a 100-piece lot reserves whole bags.
            'quantity_target' => 100,
            'planned_start' => $start->toDateTimeString(),
            'planned_end' => $deadline->toDateTimeString(),
            'preferred_production_date' => $start->toDateString(),
            'minimum_workers' => 1,
            'maximum_workers' => 2,
        ], $planner))));
    }

    private function ensureSchedule(DemoSeedContext $context, WorkOrder $workOrder, CarbonImmutable $start): ProductionSchedule
    {
        $existing = $workOrder->schedules()->whereIn('status', ['pending', 'confirmed', 'executed'])
            ->where('reserves_capacity', true)->latest('id')->first();
        if ($existing instanceof ProductionSchedule) {
            return $existing;
        }
        if ($workOrder->status !== WorkOrderStatus::Planned) {
            throw new RuntimeException("Work order {$workOrder->wo_number} is not planned and has no active execution slot.");
        }

        return $context->at($this->businessDay($start, 1)->setTime(9, 0), function () use ($workOrder): ProductionSchedule {
            $result = $this->capacity->run([(int) $workOrder->id]);
            if ($result['scheduled'] === []) {
                $reasons = collect($result['conflicts'])->flatMap(fn (array $conflict): array => $conflict['reasons'])->unique()->implode(' ');
                throw new RuntimeException("The capacity planner could not schedule {$workOrder->wo_number}: {$reasons}");
            }

            return ProductionSchedule::query()->where('work_order_id', $workOrder->id)
                ->where('status', 'pending')->where('reserves_capacity', true)->latest('id')->firstOrFail();
        });
    }

    private function ensureCrew(DemoSeedContext $context, WorkOrder $workOrder, ProductionSchedule $slot, User $manager): WorkOrderCrew
    {
        $crew = $this->assignments->currentCrew($workOrder);
        if ($crew) {
            return $crew;
        }
        $worker = $this->assignments->eligibleWorkers($workOrder, $slot->scheduled_start, $slot->scheduled_end)->first();
        if (! $worker instanceof Employee) {
            throw new RuntimeException("No eligible employee can be assigned to the production scenario {$workOrder->wo_number}.");
        }

        return $context->withoutNotifications(fn () => $context->at($slot->scheduled_start->toImmutable(), fn () => $context->asUser($manager, fn () => $this->assignments->assign($workOrder, [
            'employee_ids' => [(int) $worker->id],
            'recorder_employee_id' => (int) $worker->id,
            'minimum_workers' => 1,
            'maximum_workers' => 2,
            'expected_crew_id' => null,
            'reason' => 'Synthetic demo crew for the historical FC-001 production run.',
        ], $manager))));
    }

    private function ensureConfirmed(DemoSeedContext $context, WorkOrder $workOrder, ProductionSchedule $slot, User $planner): void
    {
        if ($workOrder->status === WorkOrderStatus::Confirmed || $workOrder->status === WorkOrderStatus::InProgress) {
            return;
        }
        if ($workOrder->status !== WorkOrderStatus::Planned) {
            throw new RuntimeException("Work order {$workOrder->wo_number} cannot be confirmed from {$workOrder->status->value}.");
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
            throw new RuntimeException("Work order {$workOrder->wo_number} cannot start from {$workOrder->status->value}.");
        }

        $employeeIds = $crew->members->pluck('employee_id')->map(static fn ($id): int => (int) $id)->all();
        $context->withoutNotifications(fn () => $context->at($slot->scheduled_start->toImmutable(), fn () => $context->asUser($manager, fn () => $this->workOrders->start(
            $workOrder->fresh(),
            (int) $manager->id,
            [
                'expected_crew_id' => $crew->id,
                'presence_confirmed_employee_ids' => $employeeIds,
            ],
        ))));
        $workOrder->refresh();
    }

    private function ensurePassingInProcessInspection(DemoSeedContext $context, WorkOrder $workOrder, WorkOrderCrew $crew, User $manager, User $qc): void
    {
        // The application's finish-first policy deliberately skips in-process
        // inspection. Its output service accepts a produced_count batch instead.
        if ($workOrder->usesFinishFirstQc()) {
            return;
        }

        $inspection = Inspection::query()->where('stage', InspectionStage::InProcess->value)
            ->where('entity_type', InspectionEntityType::WorkOrder->value)->where('entity_id', $workOrder->id)
            ->whereNull('wo_operation_id')->where('status', '!=', InspectionStatus::Cancelled->value)->first();
        if (! $inspection instanceof Inspection) {
            throw new RuntimeException("Starting {$workOrder->wo_number} did not create its required in-process QC inspection.");
        }
        $at = $workOrder->actual_start?->toImmutable()->addMinutes(15)
            ?? throw new RuntimeException("Work order {$workOrder->wo_number} has no recorded start time for its QC result.");
        if ($inspection->startup_trial_required) {
            $trialExists = $workOrder->outputs()->where('trial_inspection_id', $inspection->id)->exists();
            if (! $trialExists) {
                $context->withoutNotifications(fn () => $context->at($at, fn () => $context->asUser($manager, fn () => $this->outputs->recordTrial(
                    $workOrder->fresh(),
                    [
                        'trial_quantity' => (int) $inspection->sample_size,
                        'crew_id' => (int) $crew->id,
                        'recording_reason' => 'The manager records the operator startup sample during the seeded history replay.',
                    ],
                    (int) $manager->id,
                    'demo-production-execution-fc001-startup-trial',
                ))));
            }
        }

        if ($inspection->status === InspectionStatus::Passed) {
            if ($inspection->startup_trial_required) {
                $context->withoutNotifications(fn () => $context->at($at->addMinutes(5), fn () => $context->asUser($qc, fn () => $this->outputs->settleTrial(
                    $inspection->fresh(), $qc, (int) $inspection->sample_size,
                ))));
            }

            return;
        }
        $context->withoutNotifications(fn () => $context->at($at, function () use ($context, $inspection, $qc): void {
            $rows = InspectionMeasurement::query()->where('inspection_id', $inspection->id)->orderBy('id')->get();
            if ($rows->isEmpty()) {
                throw new RuntimeException('The in-process inspection has no specification measurements to record.');
            }
            $readings = [];
            foreach ($rows as $row) {
                $reading = ['is_pass' => true];
                if ($row->requiresSizeReading()) {
                    if ($row->nominal_value === null) {
                        throw new RuntimeException("Inspection measurement {$row->id} requires a nominal reading but has none.");
                    }
                    $reading['measured_value'] = (string) $row->nominal_value;
                }
                $readings[(int) $row->id] = $reading;
            }

            $context->asUser($qc, function () use ($inspection, $qc, $readings): void {
                $this->inspections->recordMeasurements($inspection, $readings, $qc);
                $requirements = InspectionMeasurement::query()->where('inspection_id', $inspection->id)
                    ->orderBy('sample_index')->orderBy('id')->get()
                    ->groupBy(static fn (InspectionMeasurement $row): string => $row->checklistKey())
                    ->map(static fn ($group): array => ['id' => (int) $group->first()->id, 'result' => 'passed'])
                    ->values()->all();
                $this->inspections->recordItemResults($inspection->fresh(), ['items' => $requirements, 'submit' => false], $qc);
                $result = $this->inspections->recordBatchResult($inspection->fresh(), [
                    'coverage_confirmed' => true,
                    'inspected_quantity' => (int) $inspection->sample_size,
                    'sample_defect_count' => 0,
                    'result' => 'passed',
                    'critical_defect' => false,
                ], $qc);
                if ($result->status !== InspectionStatus::Passed) {
                    throw new RuntimeException('The synthetic in-process inspection did not pass its recorded measurement evidence.');
                }
            });
        }));

        if ($inspection->startup_trial_required) {
            $context->withoutNotifications(fn () => $context->at($at->addMinutes(5), fn () => $context->asUser($qc, fn () => $this->outputs->settleTrial(
                $inspection->fresh(), $qc, (int) $inspection->sample_size,
            ))));
        }
    }

    private function ensureMaterialHandovers(DemoSeedContext $context, WorkOrder $workOrder, WorkOrderCrew $crew, User $warehouse): void
    {
        $recipient = Employee::query()->find((int) $crew->recorder_employee_id);
        if (! $recipient instanceof Employee) {
            throw new RuntimeException("The assigned production recorder for {$workOrder->wo_number} is missing.");
        }

        foreach (StockMovement::query()->forWorkOrder((int) $workOrder->id)->pendingHandover()->orderBy('id')->get() as $movement) {
            $context->withoutNotifications(fn () => $context->at($movement->created_at->toImmutable()->addMinutes(2), function () use ($context, $warehouse, $movement, $recipient): void {
                $request = Request::create('/api/v1/inventory/stock-movements/'.$movement->hash_id.'/handover', 'POST', [
                    'received_by_employee_id' => $recipient->hash_id,
                    'expected_quantity' => bcadd((string) $movement->quantity, '0', 3),
                ]);
                $request->setUserResolver(static fn (): User => $warehouse);
                $context->asUser($warehouse, fn () => $this->handovers->store($request, $movement));
            }));
        }
    }

    private function ensureOutput(DemoSeedContext $context, WorkOrder $workOrder, WorkOrderCrew $crew, User $manager, CarbonImmutable $at): WorkOrderOutput
    {
        $existing = $workOrder->outputs()->where('idempotency_key', self::OUTPUT_KEY)->first();
        if ($existing instanceof WorkOrderOutput) {
            return $existing;
        }

        $payload = [
            'crew_id' => (int) $crew->id,
            'recording_reason' => 'The manager records the operator shift report during the seeded history replay.',
            'remarks' => 'Synthetic historical shift report; 20 pieces remain on the planned work order.',
        ];
        if ($workOrder->usesFinishFirstQc()) {
            $payload['produced_count'] = 80;
        } else {
            $payload += ['good_count' => 75, 'reject_count' => 0, 'defects' => []];
        }

        return $context->withoutNotifications(fn () => $context->at($at, fn () => $context->asUser($manager, fn () => $this->outputs->record(
            $workOrder->fresh(),
            $payload,
            (int) $manager->id,
            self::OUTPUT_KEY,
        ))));
    }

    private function account(string $email, string $permission): User
    {
        $user = User::query()->where('email', $email)->first();
        if (! $user instanceof User || ! $user->is_active || ! $user->hasPermission($permission)) {
            throw new RuntimeException("Production execution history requires active demo actor {$email} with {$permission}.");
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
            && ! Holiday::query()->where('is_recurring', true)
                ->whereRaw("to_char(date, 'MM-DD') = ?", [$day->format('m-d')])->exists();
    }
}
