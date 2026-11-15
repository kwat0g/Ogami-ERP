<?php

declare(strict_types=1);

namespace Tests\Feature\Demo;

use App\Common\DemoData\DemoSeedContext;
use App\Common\DemoData\DemoSeedOptions;
use App\Common\Support\DemoSeedingGuard;
use App\Modules\Auth\Models\User;
use App\Modules\Inventory\Enums\ItemType;
use App\Modules\Inventory\Enums\StockMovementType;
use App\Modules\Inventory\Models\Item;
use App\Modules\Inventory\Models\StockLevel;
use App\Modules\Inventory\Models\StockMovement;
use App\Modules\Inventory\Models\WarehouseLocation;
use Carbon\CarbonImmutable;
use Database\Seeders\Demo\InventoryStockCoverageLayer;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class InventoryStockCoverageLayerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RolePermissionSeeder::class, SettingsSeeder::class]);

        $connection = (string) config('database.default');
        config([
            'demo.seeding.enabled' => true,
            'demo.seeding.database_allowlist' => [(string) config("database.connections.{$connection}.database")],
            'demo.seeding.allowed_hosts' => [(string) config("database.connections.{$connection}.host")],
        ]);
    }

    public function test_it_adds_lot_tracked_stock_to_every_active_item_through_the_inventory_service_idempotently(): void
    {
        $actor = User::factory()->withRole('warehouse_staff')->create();
        $locations = collect(['B-01', 'C-01', 'D-01'])
            ->mapWithKeys(fn (string $code): array => [$code => WarehouseLocation::factory()->create(['code' => $code])]);

        $items = collect([
            [ItemType::RawMaterial, 'RM-001', 'kg', '80.000', '40.000', '25.000', '10.0000'],
            [ItemType::Packaging, 'PKG-001', 'pcs', '500.000', '200.000', '250.000', '0.5000'],
            [ItemType::FinishedGood, 'FG-001', 'pcs', '400.000', '100.000', '50.000', '4.0000'],
            [ItemType::SparePart, 'SP-001', 'pcs', '6.000', '2.000', '4.000', '100.0000'],
        ])->map(fn (array $data): Item => Item::factory()->create([
            'item_type' => $data[0]->value,
            'code' => $data[1],
            'unit_of_measure' => $data[2],
            'reorder_point' => $data[3],
            'safety_stock' => $data[4],
            'minimum_order_quantity' => $data[5],
            'standard_cost' => $data[6],
        ]));

        $context = new DemoSeedContext(new DemoSeedOptions(
            asOf: CarbonImmutable::parse('2026-11-15', 'Asia/Manila'),
            scale: 'demo',
            seed: 20261115,
            only: 'inventory-stock-coverage',
        ));
        $layer = app(InventoryStockCoverageLayer::class);
        $layer->run($context);

        $openingDate = CarbonImmutable::parse('2026-11-15', 'Asia/Manila')->subMonthsNoOverflow(18)->toDateString();
        $movements = StockMovement::query()->where('idempotency_key', 'like', 'inventory-stock-coverage:%')->orderBy('item_id')->get();
        $this->assertCount($items->count(), $movements);
        $this->assertTrue($movements->every(fn (StockMovement $movement): bool => $movement->movement_type === StockMovementType::Opening
            && $movement->lot_number !== null
            && bccomp((string) $movement->quantity, '0', 3) > 0
            && $movement->reference_type === 'opening'
            && $movement->created_at->toDateString() === $openingDate));

        foreach ($items as $item) {
            $locationCode = match ($item->item_type) {
                ItemType::RawMaterial, ItemType::Packaging => 'B-01',
                ItemType::FinishedGood => 'C-01',
                ItemType::SparePart => 'D-01',
            };
            $level = StockLevel::query()->where('item_id', $item->id)->where('location_id', $locations[$locationCode]->id)->firstOrFail();
            $this->assertGreaterThan(0, (float) $level->quantity);
            $this->assertSame($actor->id, $movements->firstWhere('item_id', $item->id)->created_by);
        }

        $before = [
            'movements' => DB::table('stock_movements')->orderBy('id')->get()->toArray(),
            'levels' => DB::table('stock_levels')->orderBy('id')->get()->toArray(),
        ];
        $layer->run($context);
        $after = [
            'movements' => DB::table('stock_movements')->orderBy('id')->get()->toArray(),
            'levels' => DB::table('stock_levels')->orderBy('id')->get()->toArray(),
        ];
        $this->assertEquals($before, $after);
    }
}
