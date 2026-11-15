<?php

declare(strict_types=1);

namespace Tests\Feature\Demo;

use App\Common\DemoData\DemoSeedOptions;
use App\Common\DemoData\DemoSeedRunner;
use App\Modules\Auth\Models\User;
use App\Modules\Inventory\Models\Item;
use Carbon\CarbonImmutable;
use Database\Seeders\Demo\MaintenanceSparePartsCatalogLayer;
use Database\Seeders\HolidaySeeder;
use Database\Seeders\LeaveTypeSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SettingsSeeder;
use Database\Seeders\ShiftSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MaintenanceSparePartsCatalogLayerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([
            RolePermissionSeeder::class,
            SettingsSeeder::class,
            ShiftSeeder::class,
            LeaveTypeSeeder::class,
            HolidaySeeder::class,
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

    public function test_it_seeds_a_scaled_spare_parts_catalog_through_inventory_services_idempotently(): void
    {
        $asOf = '2026-11-15';
        $options = new DemoSeedOptions(
            asOf: CarbonImmutable::parse($asOf, 'Asia/Manila'),
            scale: 'small',
            seed: 20261115,
            only: 'maintenance-spare-parts',
        );
        $runner = app(DemoSeedRunner::class);

        $runner->run($options);

        $parts = Item::query()->with('category')->where('item_type', 'spare_part')->orderBy('code')->get();
        $this->assertCount(20, $parts);
        $this->assertSame('SP-001', $parts->first()?->code);
        $this->assertSame('SP-020', $parts->last()?->code);
        $this->assertSame('Spare Parts', $parts->firstWhere('code', 'SP-020')?->category?->name);
        $this->assertTrue($parts->every(fn (Item $item): bool => $item->is_active
            && $item->standard_cost !== null
            && $item->reorder_point !== null
            && $item->safety_stock !== null
            && $item->lead_time_days > 0
            && $item->created_at->toDateString() === $asOf));
        $this->assertSame('Barrel heater band, 38 mm', $parts->firstWhere('code', 'SP-007')?->name);
        $this->assertSame('PCS', $parts->firstWhere('code', 'SP-007')?->unit_of_measure);

        $layer = app(MaintenanceSparePartsCatalogLayer::class);
        foreach (['small' => 20, 'demo' => 100, 'full' => 140] as $scale => $expectedTotal) {
            $this->assertCount($expectedTotal - 6, $layer->plannedDefinitions($scale));
        }

        $actor = User::query()->where('email', 'warehouse.staff@accounts.ogami-demo.test')->firstOrFail();
        $newPartIds = $parts->filter(fn (Item $item): bool => (int) substr($item->code, 3) > 6)->modelKeys();
        $createdAudit = DB::table('audit_logs')
            ->where('model_type', (new Item)->getMorphClass())
            ->whereIn('model_id', $newPartIds)
            ->where('action', 'created')
            ->get();
        $this->assertCount(14, $createdAudit);
        $this->assertTrue($createdAudit->every(fn ($row): bool => (int) $row->user_id === (int) $actor->id
            && $row->actor_type === 'user'
            && CarbonImmutable::parse($row->created_at)->toDateString() === $asOf));

        $before = [
            DB::table('items')->whereIn('id', $parts->modelKeys())->orderBy('id')->get()->toArray(),
            DB::table('audit_logs')->where('model_type', (new Item)->getMorphClass())->orderBy('id')->get()->toArray(),
        ];
        $runner->run($options);
        $after = [
            DB::table('items')->whereIn('id', $parts->modelKeys())->orderBy('id')->get()->toArray(),
            DB::table('audit_logs')->where('model_type', (new Item)->getMorphClass())->orderBy('id')->get()->toArray(),
        ];

        $this->assertEquals($before, $after);
    }
}
