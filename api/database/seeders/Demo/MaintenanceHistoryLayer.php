<?php

declare(strict_types=1);

namespace Database\Seeders\Demo;

use App\Common\DemoData\DemoSeedContext;
use App\Common\DemoData\DemoSeeder;
use App\Modules\Attendance\Models\Holiday;
use App\Modules\Attendance\Services\ShiftAssignmentService;
use App\Modules\Auth\Models\User;
use App\Modules\HR\Models\Employee;
use App\Modules\Inventory\Enums\ItemType;
use App\Modules\Inventory\Models\Item;
use App\Modules\Inventory\Models\StockLevel;
use App\Modules\Inventory\Models\WarehouseLocation;
use App\Modules\Inventory\Services\StockLocationSummaryService;
use App\Modules\Maintenance\Enums\MaintainableType;
use App\Modules\Maintenance\Enums\MaintenancePriority;
use App\Modules\Maintenance\Enums\MaintenanceScheduleInterval;
use App\Modules\Maintenance\Enums\MaintenanceWorkOrderStatus;
use App\Modules\Maintenance\Enums\MaintenanceWorkOrderType;
use App\Modules\Maintenance\Models\MaintenanceLog;
use App\Modules\Maintenance\Models\MaintenancePartRequest;
use App\Modules\Maintenance\Models\MaintenanceSchedule;
use App\Modules\Maintenance\Models\MaintenanceWorkOrder;
use App\Modules\Maintenance\Services\MaintenancePartRequestService;
use App\Modules\Maintenance\Services\MaintenanceWorkOrderService;
use App\Modules\MRP\Enums\MachineStatus;
use App\Modules\MRP\Enums\MoldStatus;
use App\Modules\MRP\Models\Machine;
use App\Modules\MRP\Models\Mold;
use App\Modules\MRP\Services\MachineService;
use Carbon\CarbonImmutable;
use RuntimeException;

/** Seeds service-backed maintenance work history and current repair scenarios. */
final class MaintenanceHistoryLayer extends DemoSeeder
{
    private const COMPLETED_MARKER = '[DEMO-MAINT-HIST:COMPLETED-BREAKDOWN]';

    private const LIVE_MARKER = '[DEMO-MAINT-HIST:LIVE-BREAKDOWN]';

    private const ASSIGNED_DESCRIPTION = '[DEMO-MAINT-HIST:ASSIGNED] Scheduled mold cooling-circuit inspection.';

    private const CANCELLED_DESCRIPTION = '[DEMO-MAINT-HIST:CANCELLED] Tooling inspection withdrawn after a revised production window.';

    private const COMPLETED_PART_REQUEST = '4a5ef278-4ea1-4b75-9a7e-580a76001101';

    private const LIVE_PART_REQUEST = '4a5ef278-4ea1-4b75-9a7e-580a76001102';

    public function __construct(
        private readonly MaintenanceWorkOrderService $workOrders,
        private readonly MaintenancePartRequestService $partRequests,
        private readonly MachineService $machines,
        private readonly StockLocationSummaryService $stockAvailability,
    ) {}

    public function key(): string
    {
        return 'maintenance-history';
    }

    /** @return list<string> */
    public function modules(): array
    {
        return ['Maintenance', 'Inventory', 'Production', 'Dashboard'];
    }

    /** @return list<string> */
    public function dependencies(): array
    {
        return ['maintenance-master-data', 'opening-stock-balances', 'production-history', 'workforce-identities'];
    }

    protected function seedDemoData(DemoSeedContext $context): void
    {
        $asOf = $context->options->asOf->setTimezone('Asia/Manila')->startOfDay();
        $supervisor = $this->account('maintenance.supervisor@accounts.ogami-demo.test', 'maintenance.wo.assign');
        $technicianOne = $this->account('maintenance.tech.one@accounts.ogami-demo.test', 'maintenance.wo.complete');
        $technicianTwo = $this->account('maintenance.tech.two@accounts.ogami-demo.test', 'maintenance.wo.complete');
        $warehouse = $this->account('warehouse.staff@accounts.ogami-demo.test', 'inventory.issue.create');
        $productionManager = $this->account('production.manager@accounts.ogami-demo.test', 'maintenance.view');

        $historyAt = $this->businessDayBefore($asOf, 42)->setTime(8, 0);
        $cancellationCreatedAt = $this->businessDayBefore($asOf, 16)->setTime(8, 0);
        $cancellationAt = $this->businessDayBefore($asOf, 15)->setTime(10, 0);
        $liveAt = $this->businessDayBefore($asOf, 1)->setTime(10, 0);
        $duePmAt = $liveAt->setTime(8, 0);

        $completed = $this->ensureCompletedBreakdown(
            $context,
            $historyAt,
            $supervisor,
            $technicianOne,
            $warehouse,
            $productionManager,
        );
        $context->records()->remember('maintenance.history.completed-breakdown', $completed);

        $cancelled = $this->ensureCancelledOrder($context, $cancellationCreatedAt, $cancellationAt, $supervisor, $technicianOne);
        $context->records()->remember('maintenance.history.cancelled', $cancelled);

        $assigned = $this->ensureAssignedOrder($context, $liveAt, $supervisor, $technicianOne, (int) $cancelled->maintainable_id);
        $context->records()->remember('maintenance.history.assigned', $assigned);

        $duePm = $this->ensureDuePreventiveOrder($context, $duePmAt, $supervisor);
        $context->records()->remember('maintenance.history.due-pm', $duePm);

        $live = $this->ensureLiveBreakdown($context, $liveAt, $supervisor, $technicianTwo);
        $context->records()->remember('maintenance.history.live-breakdown', $live);
    }

    private function ensureCompletedBreakdown(
        DemoSeedContext $context,
        CarbonImmutable $at,
        User $supervisor,
        User $technician,
        User $warehouse,
        User $verifier,
    ): MaintenanceWorkOrder {
        return $context->withoutNotifications(function () use ($context, $at, $supervisor, $technician, $warehouse, $verifier): MaintenanceWorkOrder {
            $order = $this->breakdownOrder(self::COMPLETED_MARKER);
            if (! $order) {
                $machine = $this->idleMachine($context, $at);
                $context->at($at, fn () => $context->asUser($supervisor, fn () => $this->machines->transitionStatus(
                    $machine,
                    MachineStatus::Breakdown,
                    self::COMPLETED_MARKER.' Hydraulic pressure dropped during the scheduled run; isolate the press and inspect the heater-zone circuit.',
                )));
                $order = $this->breakdownOrder(self::COMPLETED_MARKER)
                    ?? throw new RuntimeException('The machine breakdown listener did not create its corrective maintenance work order.');
            }

            if ($order->status === MaintenanceWorkOrderStatus::Completed) {
                return $order;
            }

            $this->ensureAssigned($context, $order, $technician, $supervisor, $at);
            $order = $order->fresh();
            if ($order->status === MaintenanceWorkOrderStatus::Assigned) {
                $context->at($at, fn () => $context->asUser($technician, fn () => $this->workOrders->start($order, $technician)));
            }

            $workAt = $at->addMinutes(20);
            $initial = $this->ensureWorkRecord(
                $context,
                $order->fresh(),
                $technician,
                'DEMO-MAINT-HIST: replace a failed barrel heater band',
                'Isolated the heater circuit and verified the connector was sound.',
                'in_progress',
                $workAt,
            );
            $request = $this->ensurePartRequest(
                $context,
                $order->fresh(),
                $initial,
                $technician,
                self::COMPLETED_PART_REQUEST,
                $workAt->addMinutes(2),
            );
            $context->at($workAt->addMinutes(5), fn () => $context->asUser($warehouse, fn () => $this->partRequests->confirm(
                $request,
                (int) $technician->employee_id,
                '1.000',
                $warehouse,
            )));

            $this->ensureWorkRecord(
                $context,
                $order->fresh(),
                $technician,
                'DEMO-MAINT-HIST: heater-zone functional check passed',
                'Replaced the heater band and confirmed stable temperature at operating setpoint.',
                'passed',
                $workAt->addMinutes(25),
            );

            $order = $order->fresh();
            if ($order->status === MaintenanceWorkOrderStatus::InProgress) {
                $context->at($workAt->addMinutes(30), function () use ($context, $order, $technician, $verifier): void {
                    $context->asUser($technician, fn () => $this->workOrders->reconcileParts(
                        $order->fresh(),
                        ['confirm_consumed' => true],
                        $technician,
                    ));

                    if ($order->fresh()->verification_required) {
                        $context->asUser($technician, fn () => $this->workOrders->submitRepair($order->fresh(), [
                            'diagnosis' => 'Barrel heater band failed open; heater circuit wiring tested within range.',
                            'repair_action' => 'Replaced the band heater and reseated the terminal connection.',
                            'functional_test_result' => 'Temperature stabilized at the approved setpoint during a 20-minute test.',
                        ], $technician));
                        $context->asUser($verifier, fn () => $this->workOrders->verifyRepair(
                            $order->fresh(),
                            ['decision' => 'accept'],
                            $verifier,
                        ));
                    } else {
                        $context->asUser($technician, fn () => $this->workOrders->complete($order->fresh(), [
                            'remarks' => 'Repair verified by a stable heater-zone operating test.',
                        ], $technician));
                    }
                });
            }

            $completed = $order->fresh();
            if ($completed->status !== MaintenanceWorkOrderStatus::Completed) {
                throw new RuntimeException('The historical breakdown repair did not reach the completed state through its configured recovery workflow.');
            }

            return $completed;
        });
    }

    private function ensureCancelledOrder(
        DemoSeedContext $context,
        CarbonImmutable $createdAt,
        CarbonImmutable $cancelledAt,
        User $supervisor,
        User $technician,
    ): MaintenanceWorkOrder {
        $order = MaintenanceWorkOrder::query()->where('description', self::CANCELLED_DESCRIPTION)->first();
        if ($order?->status === MaintenanceWorkOrderStatus::Cancelled) {
            return $order;
        }

        $context->withoutNotifications(function () use ($context, $createdAt, $cancelledAt, $supervisor, $technician, &$order): void {
            if (! $order) {
                $mold = $this->availableMold($context, $createdAt);
                $order = $context->at($createdAt, fn () => $context->asUser($supervisor, fn () => $this->workOrders->create([
                    'maintainable_type' => MaintainableType::Mold->value,
                    'maintainable_id' => (int) $mold->id,
                    'type' => MaintenanceWorkOrderType::Preventive->value,
                    'priority' => MaintenancePriority::Low->value,
                    'description' => self::CANCELLED_DESCRIPTION,
                ], $supervisor, manual: true)));
            }

            $this->ensureAssigned($context, $order, $technician, $supervisor, $cancelledAt);
            $offShiftAt = $this->offShiftMoment($technician, $cancelledAt);
            $context->at($offShiftAt, fn () => $context->asUser($supervisor, function () use ($order, $supervisor): void {
                $this->workOrders->reconcileParts($order->fresh(), [
                    'inspection_reason' => 'Supervisor inspected after the assigned technician left the shift; no parts were issued.',
                ], $supervisor);
                $this->workOrders->cancel(
                    $order->fresh(),
                    'Cancelled after the planned tooling window changed; no parts had been issued.',
                    $supervisor,
                );
            }));
        });

        return $order->fresh();
    }

    private function offShiftMoment(User $technician, CarbonImmutable $workDate): CarbonImmutable
    {
        $employee = Employee::query()->find($technician->employee_id)
            ?? throw new RuntimeException('Maintenance cancellation scenario requires an employee-backed technician.');
        $shift = app(ShiftAssignmentService::class)->assignmentFor($employee, $workDate)?->shift
            ?? throw new RuntimeException('Maintenance cancellation scenario requires an assigned technician shift.');
        $start = CarbonImmutable::parse($workDate->toDateString().' '.$shift->start_time, 'Asia/Manila');
        $end = CarbonImmutable::parse($workDate->toDateString().' '.$shift->end_time, 'Asia/Manila');
        if ($end->lte($start)) {
            $end = $end->addDay();
        }

        return $end->addMinute();
    }

    private function ensureAssignedOrder(
        DemoSeedContext $context,
        CarbonImmutable $at,
        User $supervisor,
        User $technician,
        ?int $exceptMoldId = null,
    ): MaintenanceWorkOrder {
        $order = MaintenanceWorkOrder::query()->where('description', self::ASSIGNED_DESCRIPTION)->first();
        if (! $order) {
            $mold = $this->availableMold($context, $at, $exceptMoldId);
            $order = $context->at($at, fn () => $context->asUser($supervisor, fn () => $this->workOrders->create([
                'maintainable_type' => MaintainableType::Mold->value,
                'maintainable_id' => (int) $mold->id,
                'type' => MaintenanceWorkOrderType::Preventive->value,
                'priority' => MaintenancePriority::Medium->value,
                'description' => self::ASSIGNED_DESCRIPTION,
            ], $supervisor, manual: true)));
        }

        $this->ensureAssigned($context, $order, $technician, $supervisor, $at);

        return $order->fresh();
    }

    private function ensureDuePreventiveOrder(DemoSeedContext $context, CarbonImmutable $at, User $supervisor): MaintenanceWorkOrder
    {
        $existing = MaintenanceWorkOrder::query()->whereNotNull('schedule_id')
            ->where('type', MaintenanceWorkOrderType::Preventive->value)
            ->whereNotIn('status', [MaintenanceWorkOrderStatus::Completed->value, MaintenanceWorkOrderStatus::Cancelled->value])
            ->whereHas('schedule', fn ($query) => $query->where('description', 'like', 'Calendar PM:%'))
            ->orderBy('id')->first();
        if ($existing) {
            return $existing;
        }

        $schedule = $context->at($at, fn () => MaintenanceSchedule::query()
            ->where('is_active', true)
            ->where('interval_type', MaintenanceScheduleInterval::Days->value)
            ->where('description', 'like', 'Calendar PM:%')
            ->whereNotNull('next_due_at')
            ->where('next_due_at', '<=', $at->toDateTimeString())
            ->orderBy('next_due_at')->orderBy('id')->limit(100)->get()
            ->first(fn (MaintenanceSchedule $candidate): bool => $this->workOrders
                ->selectableTargets($candidate->maintainable_type)
                ->whereKey($candidate->maintainable_id)
                ->exists() && ! $this->hasOpenMaintenanceOrder($candidate->maintainable_type, (int) $candidate->maintainable_id)));

        if (! $schedule) {
            throw new RuntimeException('Maintenance history requires an active calendar PM schedule that is due on the prior business day.');
        }

        $order = $context->at($at, fn () => $context->asUser($supervisor, fn () => $this->workOrders->create([
            'priority' => MaintenancePriority::High->value,
        ], $supervisor, $schedule)));

        if ($order->status !== MaintenanceWorkOrderStatus::Open) {
            throw new RuntimeException('The due preventive maintenance order must remain open for the live PM scenario.');
        }

        return $order;
    }

    private function ensureLiveBreakdown(DemoSeedContext $context, CarbonImmutable $at, User $supervisor, User $technician): MaintenanceWorkOrder
    {
        $order = $this->breakdownOrder(self::LIVE_MARKER);
        if (! $order) {
            $machine = $this->idleMachine($context, $at, $this->completedBreakdownMachineId(), avoidReservedCapacity: true);
            $context->at($at, fn () => $context->asUser($supervisor, fn () => $this->machines->transitionStatus(
                $machine,
                MachineStatus::Breakdown,
                self::LIVE_MARKER.' Repeated hydraulic pressure fluctuation; hold the press and inspect the return filter and seal.',
            )));
            $order = $this->breakdownOrder(self::LIVE_MARKER)
                ?? throw new RuntimeException('The machine breakdown listener did not create the live corrective maintenance work order.');
        }

        if ($order->status === MaintenanceWorkOrderStatus::Open) {
            $this->ensureAssigned($context, $order, $technician, $supervisor, $at);
            $order = $order->fresh();
        }
        if ($order->status === MaintenanceWorkOrderStatus::Assigned) {
            $context->at($at, fn () => $context->asUser($technician, fn () => $this->workOrders->start($order, $technician)));
            $order = $order->fresh();
        }
        if ($order->status === MaintenanceWorkOrderStatus::InProgress) {
            $work = $this->ensureWorkRecord(
                $context,
                $order,
                $technician,
                'DEMO-MAINT-HIST: inspect hydraulic return circuit',
                'Isolated the press and found a return-filter restriction; breakdown repair is awaiting warehouse handover.',
                'in_progress',
                $at->addMinutes(20),
            );
            $this->ensurePartRequest(
                $context,
                $order,
                $work,
                $technician,
                self::LIVE_PART_REQUEST,
                $at->addMinutes(25),
            );
        }

        if ($order->status !== MaintenanceWorkOrderStatus::InProgress) {
            throw new RuntimeException('The current breakdown scenario must remain in progress for continuation during the demo.');
        }

        return $order->fresh();
    }

    private function ensureAssigned(DemoSeedContext $context, MaintenanceWorkOrder $order, User $technician, User $supervisor, CarbonImmutable $at): void
    {
        if ($order->status !== MaintenanceWorkOrderStatus::Open) {
            return;
        }

        $context->at($at, fn () => $context->asUser($supervisor, fn () => $this->workOrders->assign(
            $order->fresh(),
            (int) $technician->employee_id,
            $supervisor,
            [
                'expected_assignee_id' => $order->assigned_to,
                'presence_attestation_reason' => 'Supervisor confirmed the assigned technician was present for the scheduled maintenance shift.',
            ],
        )));
    }

    private function ensureWorkRecord(
        DemoSeedContext $context,
        MaintenanceWorkOrder $order,
        User $technician,
        string $finding,
        string $action,
        string $result,
        CarbonImmutable $at,
    ): MaintenanceLog {
        $existing = MaintenanceLog::query()->where('work_order_id', $order->id)->where('finding', $finding)->first();
        if ($existing) {
            return $existing;
        }

        return $context->at($at, fn () => $context->asUser($technician, fn () => $this->workOrders->recordWork(
            $order->fresh(),
            ['finding' => $finding, 'action_taken' => $action, 'result' => $result],
            $technician,
        )));
    }

    private function ensurePartRequest(
        DemoSeedContext $context,
        MaintenanceWorkOrder $order,
        MaintenanceLog $work,
        User $technician,
        string $operationKey,
        CarbonImmutable $at,
    ): MaintenancePartRequest {
        $request = MaintenancePartRequest::query()->where('operation_key', $operationKey)->first();
        if ($request) {
            return $request;
        }

        [$item, $location] = $this->sparePartWithUsableStock($context, $at);

        return $context->at($at, fn () => $context->asUser($technician, fn () => $this->partRequests->request($order->fresh(), [
            'work_record_id' => (int) $work->id,
            'item_id' => (int) $item->id,
            'location_id' => (int) $location->id,
            'quantity' => '1.000',
            'operation_key' => $operationKey,
        ], $technician)));
    }

    /** @return array{Item, WarehouseLocation} */
    private function sparePartWithUsableStock(DemoSeedContext $context, CarbonImmutable $at): array
    {
        $location = WarehouseLocation::query()->where('code', 'D-01')->where('is_active', true)->first()
            ?? throw new RuntimeException('Maintenance history requires the active D-01 spare-parts location.');
        $levels = StockLevel::query()->with(['item', 'location.zone.warehouse'])
            ->where('location_id', $location->id)
            ->whereHas('item', fn ($query) => $query->where('item_type', ItemType::SparePart->value)->where('is_active', true))
            ->orderBy('item_id')->limit(100)->get();

        return $context->at($at, function () use ($levels, $location): array {
            foreach ($levels as $level) {
                $available = $this->stockAvailability->availability(collect([$level]))['usable_quantity'];
                if (bccomp((string) $available, '1.000', 3) >= 0) {
                    return [$level->item, $location];
                }
            }

            throw new RuntimeException('Maintenance history requires at least one unit of usable spare-part stock in location D-01.');
        });
    }

    private function breakdownOrder(string $marker): ?MaintenanceWorkOrder
    {
        return MaintenanceWorkOrder::query()
            ->where('type', MaintenanceWorkOrderType::Corrective->value)
            ->where('description', 'like', '%'.$marker.'%')
            ->orderBy('id')->first();
    }

    private function completedBreakdownMachineId(): ?int
    {
        return $this->breakdownOrder(self::COMPLETED_MARKER)?->maintainable_type === MaintainableType::Machine
            ? $this->breakdownOrder(self::COMPLETED_MARKER)?->maintainable_id
            : null;
    }

    private function idleMachine(
        DemoSeedContext $context,
        CarbonImmutable $at,
        ?int $exceptId = null,
        bool $avoidReservedCapacity = false,
    ): Machine {
        return $context->at($at, fn () => $this->workOrders->selectableTargets(MaintainableType::Machine)
            ->where('status', MachineStatus::Idle->value)
            ->whereNotExists(fn ($orders) => $orders->selectRaw('1')->from('maintenance_work_orders')
                ->whereColumn('maintainable_id', 'machines.id')
                ->where('maintainable_type', MaintainableType::Machine->value)
                ->whereIn('status', [MaintenanceWorkOrderStatus::Open->value, MaintenanceWorkOrderStatus::Assigned->value, MaintenanceWorkOrderStatus::InProgress->value]))
            ->when($avoidReservedCapacity, fn ($query) => $query->whereNotExists(fn ($reservations) => $reservations
                ->selectRaw('1')->from('production_schedules')
                ->whereColumn('machine_id', 'machines.id')
                ->where('reserves_capacity', true)
                ->whereIn('status', ['pending', 'confirmed', 'executed'])
                ->where('scheduled_end', '>', $at->toDateTimeString())))
            ->when($exceptId !== null, fn ($query) => $query->where('id', '!=', $exceptId))
            ->orderBy('machine_code')->first())
            ?? throw new RuntimeException('Maintenance history requires an idle injection machine without an active maintenance order or conflicting capacity reservation.');
    }

    private function availableMold(DemoSeedContext $context, CarbonImmutable $at, ?int $exceptId = null): Mold
    {
        return $context->at($at, fn () => $this->workOrders->selectableTargets(MaintainableType::Mold)
            ->where('status', MoldStatus::Available->value)
            ->whereNotExists(fn ($orders) => $orders->selectRaw('1')->from('maintenance_work_orders')
                ->whereColumn('maintainable_id', 'molds.id')
                ->where('maintainable_type', MaintainableType::Mold->value)
                ->whereIn('status', [MaintenanceWorkOrderStatus::Open->value, MaintenanceWorkOrderStatus::Assigned->value, MaintenanceWorkOrderStatus::InProgress->value]))
            ->when($exceptId !== null, fn ($query) => $query->where('id', '!=', $exceptId))
            ->orderBy('mold_code')->first())
            ?? throw new RuntimeException('Maintenance history requires an available mold that is not reserved for production.');
    }

    private function hasOpenMaintenanceOrder(MaintainableType $type, int $targetId): bool
    {
        return MaintenanceWorkOrder::query()->where('maintainable_type', $type->value)
            ->where('maintainable_id', $targetId)
            ->whereIn('status', [MaintenanceWorkOrderStatus::Open->value, MaintenanceWorkOrderStatus::Assigned->value, MaintenanceWorkOrderStatus::InProgress->value])
            ->exists();
    }

    private function businessDayBefore(CarbonImmutable $anchor, int $businessDays): CarbonImmutable
    {
        $day = $anchor->setTimezone('Asia/Manila')->startOfDay();
        $remaining = $businessDays;
        while ($remaining > 0 || ! $this->isBusinessDay($day)) {
            $day = $day->subDay();
            if ($this->isBusinessDay($day) && $remaining > 0) {
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

    private function account(string $email, string $permission): User
    {
        $user = User::query()->where('email', $email)->where('is_active', true)->first();
        if (! $user instanceof User || ! $user->hasPermission($permission)) {
            throw new RuntimeException("Maintenance history requires active demo actor {$email} with {$permission}.");
        }

        return $user;
    }
}
