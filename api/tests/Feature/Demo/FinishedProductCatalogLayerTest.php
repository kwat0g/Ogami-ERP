<?php

declare(strict_types=1);

namespace Tests\Feature\Demo;

use App\Common\DemoData\DemoSeedOptions;
use App\Common\DemoData\DemoSeedRunner;
use App\Modules\CRM\Models\Product;
use App\Modules\Inventory\Enums\ItemType;
use App\Modules\Inventory\Models\Item;
use App\Modules\MRP\Models\Bom;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinishedProductCatalogLayerTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_finished_products_and_inventory_mappings_idempotently(): void
    {
        $options = new DemoSeedOptions(
            asOf: CarbonImmutable::parse('2026-11-14', 'Asia/Manila'),
            scale: 'small',
            seed: 20261114,
            only: 'finished-product-catalog',
        );

        $runner = app(DemoSeedRunner::class);
        $runner->run($options);

        $this->assertSame(16, Product::query()->count());
        $this->assertSame(44, Item::query()->count());
        $this->assertSame(0, Bom::query()->count());

        $product = Product::query()->where('part_number', 'WB-001')->firstOrFail();
        $item = Item::query()->where('code', $product->part_number)->firstOrFail();
        $this->assertSame('2026-11-14', $product->created_at->toDateString());
        $this->assertSame('2026-11-14', $item->created_at->toDateString());
        $this->assertSame(ItemType::FinishedGood, $item->item_type);
        $this->assertSame($product->unit_of_measure, $item->unit_of_measure);
        $this->assertSame(0, bccomp($product->standard_cost, $item->standard_cost, 2));

        $partNumbers = Product::query()->orderBy('part_number')->pluck('part_number')->all();

        $runner->run($options);

        $this->assertSame(16, Product::query()->count());
        $this->assertSame(44, Item::query()->count());
        $this->assertSame($partNumbers, Product::query()->orderBy('part_number')->pluck('part_number')->all());
    }
}
