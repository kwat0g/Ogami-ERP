<?php

declare(strict_types=1);

namespace Database\Seeders\Demo;

use App\Common\DemoData\DemoSeedContext;
use App\Common\DemoData\DemoSeeder;
use App\Modules\Attendance\Models\Holiday;
use App\Modules\Auth\Models\User;
use App\Modules\CRM\Models\Product;
use App\Modules\HR\Models\Employee;
use App\Modules\MRP\Services\CapacityPlanningService;
use App\Modules\Production\Enums\WorkOrderStatus;
use App\Modules\Production\Models\ProductionSchedule;
use App\Modules\Production\Models\WorkOrder;
use App\Modules\Production\Services\StockProductionPlanningService;
use App\Modules\Production\Services\WorkOrderAssignmentService;
use App\Modules\Production\Services\WorkOrderService;
use Carbon\CarbonImmutable;
use RuntimeException;

/** Seeds current stock-production planning, scheduling, confirmation, and cancellation scenarios. */
final class ProductionHistoryLayer extends DemoSeeder
{
    private const PLANNED_KEY = '6fbeec8d-0000-5000-8000-000000000001';

    private const CONFIRMED_KEY = '6fbeec8d-0000-5000-8000-000000000002';

    private const CANCELLED_KEY = '6fbeec8d-0000-5000-8000-000000000003';

    public function __construct(
        private readonly StockProductionPlanningService $stockProduction,
        private readonly CapacityPlanningService $capacity,
        private readonly WorkOrderService $workOrders,
        private readonly WorkOrderAssignmentService $assignments,
    ) {}

    public function key(): string
    {
        return 'production-history';
    }

    /** @return list<string> */
    public function modules(): array
    {
        return ['MRP', 'Production', 'Inventory', 'Purchasing', 'Quality', 'Dashboard'];
    }

    /** @return list<string> */
    public function dependencies(): array
    {
        return ['opening-stock-balances', 'quality-inspection-specs', 'workforce-identities'];
    }

    protected function seedDemoData(DemoSeedContext $context): void
    {
        $asOf = $context->options->asOf->setTimezone('Asia/Manila')->startOfDay();
        $ppc = $this->account('ppc.head@accounts.ogami-demo.test', 'production.wo.create');
        $productionManager = $this->account('production.manager@accounts.ogami-demo.test', 'production.wo.assign');
        $product = Product::query()->where('part_number', 'FC-001')->where('is_active', true)->first();
        if (! $product instanceof Product) {
            throw new RuntimeException('Production history requires the active FC-001 fuse-box cover product.');
        }

        $cancelledAt = $this->businessDay($asOf, 24)->setTime(8, 0);
        $cancelled = $this->ensureStockWorkOrder(
            $context,
            $ppc,
            $product,
            self::CANCELLED_KEY,
            120,
            $cancelledAt,
            $cancelledAt->addDays(6),
        );
        if ($cancelled->status !== WorkOrderStatus::Cancelled) {
            $cancelAt = $this->businessDay($cancelledAt, 1)->setTime(11, 0);
            $context->at($cancelAt, fn () => $context->asUser($productionManager, fn () => $this->workOrders->cancel(
                $cancelled->fresh(),
                'Synthetic stock plan was withdrawn after the customer mix changed.',
            )));
        }

        $planningStart = $this->businessDay($asOf, -1)->setTime(8, 0);
        $planned = $this->ensureStockWorkOrder(
            $context,
            $ppc,
            $product,
            self::PLANNED_KEY,
            180,
            $planningStart,
            $planningStart->addDays(8),
            $asOf->setTime(8, 0),
        );

        $confirmedStart = $this->businessDay($asOf, -1)->setTime(8, 0);
        $confirmed = $this->ensureStockWorkOrder(
            $context,
            $ppc,
            $product,
            self::CONFIRMED_KEY,
            240,
            $confirmedStart,
            $confirmedStart->addDays(10),
            $asOf->setTime(8, 0),
        );

        $context->at($asOf->setTime(8, 0), function () use ($context, $planned, $confirmed, $ppc, $productionManager): void {
            $planned = $planned->fresh();
            $confirmed = $confirmed->fresh();

            $plannedSchedule = $this->ensureActiveSchedule($planned);
            $confirmedSchedule = $this->ensureActiveSchedule($confirmed);

            if ($confirmed->status === WorkOrderStatus::Planned) {
                $this->assignCrew($confirmed, $confirmedSchedule, $productionManager);
                $this->capacity->confirm([(int) $confirmedSchedule->id], (int) $ppc->id);
            }

            if ($planned->status !== WorkOrderStatus::Planned
                || $plannedSchedule->fresh()->status->value !== 'pending') {
                throw new RuntimeException('The live production-planning scenario must remain planned with a pending schedule.');
            }
            if ($confirmed->fresh()->status !== WorkOrderStatus::Confirmed) {
                throw new RuntimeException('The confirmed production scenario did not reach confirmed status through the capacity planner.');
            }

            $context->records()->remember('production-history.planned', $planned->fresh());
            $context->records()->remember('production-history.confirmed', $confirmed->fresh());
            $context->records()->remember('production-history.cancelled', WorkOrder::query()->where('stock_creation_request_key', self::CANCELLED_KEY)->firstOrFail());
        });
    }

    private function ensureStockWorkOrder(
        DemoSeedContext $context,
        User $planner,
        Product $product,
        string $requestKey,
        int $quantity,
        CarbonImmutable $plannedStart,
        CarbonImmutable $plannedEnd,
        ?CarbonImmutable $createdAt = null,
    ): WorkOrder {
        $existing = WorkOrder::query()->where('stock_creation_request_key', $requestKey)->first();
        if ($existing instanceof WorkOrder) {
            return $existing;
        }

        $at = $createdAt ?? $this->businessDay($plannedStart, 1)->setTime(9, 0);

        return $context->withoutNotifications(fn () => $context->at($at, fn () => $context->asUser($planner, fn () => $this->stockProduction->create([
            'request_key' => $requestKey,
            'product_id' => (int) $product->id,
            'quantity_target' => $quantity,
            'planned_start' => $plannedStart->toDateTimeString(),
            'planned_end' => $plannedEnd->toDateTimeString(),
            'preferred_production_date' => $plannedStart->toDateString(),
            'minimum_workers' => 1,
            'maximum_workers' => 2,
        ], $planner))));
    }

    private function ensureActiveSchedule(WorkOrder $workOrder): ProductionSchedule
    {
        $existing = $workOrder->schedules()->whereIn('status', ['pending', 'confirmed', 'executed'])
            ->where('reserves_capacity', true)->latest('id')->first();
        if ($existing instanceof ProductionSchedule) {
            return $existing;
        }
        if ($workOrder->status !== WorkOrderStatus::Planned) {
            throw new RuntimeException("Work order {$workOrder->wo_number} is {$workOrder->status->value} without an active capacity reservation.");
        }

        $result = $this->capacity->run([(int) $workOrder->id]);
        if ($result['scheduled'] === []) {
            $reasons = collect($result['conflicts'])->flatMap(fn (array $conflict): array => $conflict['reasons'])->unique()->implode(' ');
            throw new RuntimeException("The capacity planner could not schedule {$workOrder->wo_number}: {$reasons}");
        }

        return ProductionSchedule::query()->where('work_order_id', $workOrder->id)
            ->where('status', 'pending')->where('reserves_capacity', true)->latest('id')->firstOrFail();
    }

    private function assignCrew(WorkOrder $workOrder, ProductionSchedule $slot, User $actor): void
    {
        if ($this->assignments->currentCrew($workOrder)) {
            return;
        }
        $worker = $this->assignments->eligibleWorkers($workOrder, $slot->scheduled_start, $slot->scheduled_end)->first();
        if (! $worker instanceof Employee) {
            throw new RuntimeException("The capacity planner found no eligible production worker for {$workOrder->wo_number}.");
        }

        $this->assignments->assign($workOrder, [
            'employee_ids' => [(int) $worker->id],
            'recorder_employee_id' => (int) $worker->id,
            'minimum_workers' => 1,
            'maximum_workers' => 2,
            'expected_crew_id' => null,
            'reason' => 'Synthetic demo crew for the scheduled FC-001 stock-production run.',
        ], $actor);
    }

    private function account(string $email, string $permission): User
    {
        $user = User::query()->where('email', $email)->where('is_active', true)->first();
        if (! $user instanceof User || ! $user->hasPermission($permission)) {
            throw new RuntimeException("Production history requires active demo actor {$email} with {$permission}.");
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
