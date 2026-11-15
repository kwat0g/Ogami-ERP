<?php

declare(strict_types=1);

namespace Tests\Feature\Demo;

use App\Common\DemoData\DemoSeedContext;
use App\Common\DemoData\DemoSeedLayerRegistry;
use App\Common\DemoData\DemoSeedOptions;
use App\Common\DemoData\DemoSeedRunner;
use App\Modules\Inventory\Enums\ReservationStatus;
use App\Modules\Inventory\Models\MaterialReservation;
use App\Modules\Production\Enums\WorkOrderStatus;
use App\Modules\Production\Models\ProductionSchedule;
use App\Modules\Production\Models\WorkOrder;
use App\Modules\Production\Models\WorkOrderCrew;
use Carbon\CarbonImmutable;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\HolidaySeeder;
use Database\Seeders\LeaveTypeSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SettingsSeeder;
use Database\Seeders\ShiftSeeder;
use Database\Seeders\WorkflowSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ProductionHistoryLayerTest extends TestCase
{
    use RefreshDatabase;

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

    public function test_it_seeds_planned_pending_schedule_confirmed_and_cancelled_work_orders_through_services(): void
    {
        $options = new DemoSeedOptions(
            asOf: CarbonImmutable::parse('2026-12-01', 'Asia/Manila'),
            scale: 'small',
            seed: 20261201,
            only: 'production-history',
        );

        app(DemoSeedRunner::class)->run($options);

        $orders = WorkOrder::query()->whereIn('stock_creation_request_key', [
            '6fbeec8d-0000-5000-8000-000000000001',
            '6fbeec8d-0000-5000-8000-000000000002',
            '6fbeec8d-0000-5000-8000-000000000003',
        ])->get()->keyBy('stock_creation_request_key');

        $this->assertCount(3, $orders);
        $planned = $orders->get('6fbeec8d-0000-5000-8000-000000000001');
        $confirmed = $orders->get('6fbeec8d-0000-5000-8000-000000000002');
        $cancelled = $orders->get('6fbeec8d-0000-5000-8000-000000000003');
        $this->assertSame(WorkOrderStatus::Planned, $planned->status);
        $this->assertSame(WorkOrderStatus::Confirmed, $confirmed->status);
        $this->assertSame(WorkOrderStatus::Cancelled, $cancelled->status);
        $this->assertSame('2026-12-01', CarbonImmutable::parse($planned->created_at)->setTimezone('Asia/Manila')->toDateString());
        $this->assertSame('2026-12-01', CarbonImmutable::parse($confirmed->created_at)->setTimezone('Asia/Manila')->toDateString());
        $this->assertLessThan($cancelled->planned_start, $cancelled->created_at);
        $this->assertSame('pending', $planned->schedules()->where('reserves_capacity', true)->latest('id')->firstOrFail()->status->value);
        $this->assertSame('confirmed', $confirmed->schedules()->where('reserves_capacity', true)->latest('id')->firstOrFail()->status->value);
        $this->assertSame(1, $confirmed->crews()->whereNull('ended_at')->count());
        $this->assertGreaterThan(0, MaterialReservation::query()->where('work_order_id', $confirmed->id)
            ->where('status', ReservationStatus::Reserved->value)->count());

        $before = $this->fingerprint();
        $layers = app(DemoSeedLayerRegistry::class)->ordered($options->only);
        $layer = end($layers);
        $this->assertNotFalse($layer);
        $layer->run((new DemoSeedContext($options))->forLayer($layer->key()));
        $this->assertSame($before, $this->fingerprint(), 'Rerunning production history must not duplicate or drift work orders or schedules.');
    }

    /** @return array<string, array<int, array<string, mixed>>> */
    private function fingerprint(): array
    {
        $ids = WorkOrder::query()->whereIn('stock_creation_request_key', [
            '6fbeec8d-0000-5000-8000-000000000001',
            '6fbeec8d-0000-5000-8000-000000000002',
            '6fbeec8d-0000-5000-8000-000000000003',
        ])->select('id');

        return [
            'work_orders' => DB::table('work_orders')->whereIn('id', $ids)->orderBy('id')->get()->map(static fn ($row): array => (array) $row)->all(),
            'schedules' => ProductionSchedule::query()->whereIn('work_order_id', $ids)->orderBy('id')->get()->map(static fn ($row): array => $row->getAttributes())->all(),
            'crews' => WorkOrderCrew::query()->whereIn('work_order_id', $ids)->orderBy('id')->get()->map(static fn ($row): array => $row->getAttributes())->all(),
            'reservations' => MaterialReservation::query()->whereIn('work_order_id', $ids)->orderBy('id')->get()->map(static fn ($row): array => $row->getAttributes())->all(),
        ];
    }
}
