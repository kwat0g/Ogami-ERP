<?php

declare(strict_types=1);

namespace Tests\Feature\Demo;

use App\Common\DemoData\DemoSeedOptions;
use App\Common\DemoData\DemoSeedRunner;
use App\Modules\Inventory\Models\MaterialHandover;
use App\Modules\Inventory\Models\StockMovement;
use App\Modules\Maintenance\Enums\MaintenanceWorkOrderStatus;
use App\Modules\Maintenance\Models\MaintenanceWorkOrder;
use App\Modules\Production\Enums\MachineDowntimeCategory;
use App\Modules\Production\Models\ProductionSchedule;
use Carbon\CarbonImmutable;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\Demo\MaintenanceHistoryLayer;
use Database\Seeders\HolidaySeeder;
use Database\Seeders\LeaveTypeSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SettingsSeeder;
use Database\Seeders\ShiftSeeder;
use Database\Seeders\WorkflowSeeder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MaintenanceHistoryLayerTest extends TestCase
{
    use RefreshDatabase;

    private const MARKER = 'DEMO-MAINT-HIST';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([
            RolePermissionSeeder::class,
            SettingsSeeder::class,
            ChartOfAccountsSeeder::class,
            ShiftSeeder::class,
            LeaveTypeSeeder::class,
            HolidaySeeder::class,
            WorkflowSeeder::class,
        ]);

        $connectionName = (string) config('database.default');
        $databaseHost = (string) config("database.connections.{$connectionName}.host");
        config([
            'demo.credentials.password' => 'Demo-Only-Pass-2026!',
            'demo.seeding.enabled' => true,
            'demo.seeding.database_allowlist' => ['ogami_test'],
            'demo.seeding.allowed_hosts' => [$databaseHost],
        ]);
    }

    public function test_it_seeds_all_maintenance_work_order_states_and_replays_without_drift(): void
    {
        $this->assertTrue(class_exists(MaintenanceHistoryLayer::class), 'The maintenance history layer must exist before the workflow can be seeded.');

        $options = new DemoSeedOptions(
            asOf: CarbonImmutable::parse('2026-11-16', 'Asia/Manila'),
            scale: 'small',
            seed: 20261116,
            only: 'maintenance-history',
        );

        app(DemoSeedRunner::class)->run($options);

        $orders = $this->demoOrders();
        $this->assertCount(5, $orders);

        $byStatus = $orders->keyBy(fn (MaintenanceWorkOrder $order): string => $order->status->value);
        $this->assertEqualsCanonicalizing(MaintenanceWorkOrderStatus::values(), $byStatus->keys()->all());

        $open = $byStatus->get(MaintenanceWorkOrderStatus::Open->value);
        $this->assertNotNull($open->schedule_id);
        $this->assertTrue($open->schedule->next_due_at->lte(CarbonImmutable::parse('2026-11-16', 'Asia/Manila')));

        $assigned = $orders->first(fn (MaintenanceWorkOrder $order): bool => $order->status === MaintenanceWorkOrderStatus::Assigned);
        $this->assertNotNull($assigned->assigned_to);

        $inProgress = $orders->first(fn (MaintenanceWorkOrder $order): bool => $order->status === MaintenanceWorkOrderStatus::InProgress);
        $this->assertNull($inProgress->repair_submitted_at);
        $this->assertSame(1, $inProgress->partRequests()->where('status', 'requested')->count());
        $this->assertFalse(ProductionSchedule::query()
            ->where('machine_id', $inProgress->maintainable_id)
            ->where('reserves_capacity', true)
            ->whereIn('status', ['pending', 'confirmed', 'executed'])
            ->where('scheduled_end', '>', $inProgress->started_at)
            ->exists(), 'The live breakdown must not interrupt a seeded production capacity reservation.');
        $this->assertSame(MachineDowntimeCategory::Breakdown,
            $inProgress->machineDowntimes()->whereNull('end_time')->firstOrFail()->category);

        $completed = $byStatus->get(MaintenanceWorkOrderStatus::Completed->value);
        $this->assertNotNull($completed->started_at);
        $this->assertNotNull($completed->completed_at);
        $this->assertGreaterThan(0, $completed->logs()->where('result', 'passed')->count());
        $this->assertSame(MachineDowntimeCategory::Breakdown,
            $completed->machineDowntimes()->whereNotNull('end_time')->firstOrFail()->category);
        $usage = $completed->spareParts()->firstOrFail();
        $this->assertSame('maintenance_work_order', $usage->stockMovement->reference_type);
        $this->assertSame('handed_over', $completed->partRequests()->firstOrFail()->status);
        $this->assertSame(1, MaterialHandover::query()->where('stock_movement_id', $usage->stock_movement_id)->count());
        $this->assertSame(1, StockMovement::query()->where('reference_type', 'maintenance_work_order')
            ->where('reference_id', $completed->id)->where('movement_type', 'material_issue')->count());

        $cancelled = $byStatus->get(MaintenanceWorkOrderStatus::Cancelled->value);
        $this->assertGreaterThan(0, $cancelled->logs()->where('description', 'like', 'Cancelled:%')->count());

        foreach ($orders->whereIn('status', [MaintenanceWorkOrderStatus::Open, MaintenanceWorkOrderStatus::Assigned, MaintenanceWorkOrderStatus::InProgress]) as $live) {
            $this->assertLessThanOrEqual('2026-11-16 23:59:59', $live->created_at->setTimezone('Asia/Manila')->format('Y-m-d H:i:s'));
        }
        $this->assertLessThanOrEqual('2026-11-16 23:59:59', $completed->completed_at->setTimezone('Asia/Manila')->format('Y-m-d H:i:s'));

        $before = $this->fingerprint($orders);
        app(DemoSeedRunner::class)->run($options);
        $this->assertSame($before, $this->fingerprint($this->demoOrders()));
    }

    /** @return Collection<int, MaintenanceWorkOrder> */
    private function demoOrders(): Collection
    {
        return MaintenanceWorkOrder::query()
            ->with(['schedule', 'logs', 'machineDowntimes', 'spareParts.stockMovement', 'partRequests'])
            ->where(function ($query): void {
                $query->where('description', 'like', '%'.self::MARKER.'%')
                    ->orWhereHas('schedule', fn ($schedule) => $schedule->where('description', 'like', 'Calendar PM:%'));
            })
            ->orderBy('id')
            ->get();
    }

    /** @param Collection<int, MaintenanceWorkOrder> $orders
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function fingerprint(Collection $orders): array
    {
        $orderIds = $orders->modelKeys();
        $movementIds = StockMovement::query()->where('reference_type', 'maintenance_work_order')
            ->whereIn('reference_id', $orderIds)->orderBy('id')->pluck('id');

        return [
            'orders' => DB::table('maintenance_work_orders')->whereIn('id', $orderIds)->orderBy('id')->get()->map(static fn ($row): array => (array) $row)->all(),
            'logs' => DB::table('maintenance_logs')->whereIn('work_order_id', $orderIds)->orderBy('id')->get()->map(static fn ($row): array => (array) $row)->all(),
            'downtime' => DB::table('machine_downtimes')->whereIn('maintenance_order_id', $orderIds)->orderBy('id')->get()->map(static fn ($row): array => (array) $row)->all(),
            'requests' => DB::table('maintenance_part_requests')->whereIn('work_order_id', $orderIds)->orderBy('id')->get()->map(static fn ($row): array => (array) $row)->all(),
            'usage' => DB::table('spare_part_usage')->whereIn('work_order_id', $orderIds)->orderBy('id')->get()->map(static fn ($row): array => (array) $row)->all(),
            'movements' => DB::table('stock_movements')->whereIn('id', $movementIds)->orderBy('id')->get()->map(static fn ($row): array => (array) $row)->all(),
            'handovers' => DB::table('material_handovers')->whereIn('stock_movement_id', $movementIds)->orderBy('id')->get()->map(static fn ($row): array => (array) $row)->all(),
        ];
    }
}
