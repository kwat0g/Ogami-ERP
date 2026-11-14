<?php

declare(strict_types=1);

namespace Tests\Feature\Demo;

use App\Common\DemoData\DemoSeedOptions;
use App\Common\DemoData\DemoSeedRunner;
use App\Modules\Inventory\Models\Item;
use App\Modules\Inventory\Models\ItemCategory;
use App\Modules\Inventory\Models\StockLevel;
use App\Modules\Inventory\Models\Uom;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Inventory\Models\WarehouseLocation;
use App\Modules\Inventory\Models\WarehouseZone;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryCatalogLayerTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_seeds_inventory_master_data_idempotently_through_services(): void
    {
        $options = new DemoSeedOptions(
            asOf: CarbonImmutable::parse('2026-11-14', 'Asia/Manila'),
            scale: 'small',
            seed: 20261114,
            only: 'inventory-catalog',
        );

        $runner = app(DemoSeedRunner::class);
        $runner->run($options);

        $this->assertSame(8, Uom::query()->count());
        $this->assertSame(8, ItemCategory::query()->count());
        $this->assertSame(27, Item::query()->count());
        $this->assertSame(1, Warehouse::query()->count());
        $this->assertSame(6, WarehouseZone::query()->count());
        $this->assertSame(76, WarehouseLocation::query()->count());
        $this->assertSame(0, Item::query()->where('is_active', false)->count());
        $this->assertSame(0, StockLevel::query()->count());

        $resin = Item::query()->where('code', 'RM-001')->firstOrFail();
        $this->assertSame('KG', $resin->unit_of_measure);
        $this->assertSame('raw_material', $resin->item_type->value);
        $this->assertSame('2026-11-14', $resin->created_at->toDateString());

        $firstCounts = [
            Uom::query()->count(),
            ItemCategory::query()->count(),
            Item::query()->count(),
            Warehouse::query()->count(),
            WarehouseZone::query()->count(),
            WarehouseLocation::query()->count(),
        ];

        $runner->run($options);

        $this->assertSame($firstCounts, [
            Uom::query()->count(),
            ItemCategory::query()->count(),
            Item::query()->count(),
            Warehouse::query()->count(),
            WarehouseZone::query()->count(),
            WarehouseLocation::query()->count(),
        ]);
    }
}
