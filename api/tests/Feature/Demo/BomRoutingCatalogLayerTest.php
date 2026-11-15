<?php

declare(strict_types=1);

namespace Tests\Feature\Demo;

use App\Common\DemoData\DemoSeedOptions;
use App\Common\DemoData\DemoSeedRunner;
use App\Modules\CRM\Models\Product;
use App\Modules\Inventory\Models\Item;
use App\Modules\MRP\Enums\MachineStatus;
use App\Modules\MRP\Models\Bom;
use App\Modules\MRP\Models\BomItem;
use App\Modules\MRP\Models\Machine;
use App\Modules\MRP\Services\MachineService;
use App\Modules\Production\Models\ProductRouting;
use App\Modules\Production\Models\RoutingOperation;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BomRoutingCatalogLayerTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_costed_boms_and_validated_routings_idempotently(): void
    {
        $options = new DemoSeedOptions(
            asOf: CarbonImmutable::parse('2026-11-14', 'Asia/Manila'),
            scale: 'small',
            seed: 20261114,
            only: 'bom-routing-catalog',
        );

        $runner = app(DemoSeedRunner::class);
        $runner->run($options);

        $this->assertSame(16, Product::query()->count());
        $this->assertSame(44, Item::query()->count());
        $this->assertSame(16, Bom::query()->where('is_active', true)->count());
        $this->assertSame(44, BomItem::query()->count());
        $this->assertSame(16, ProductRouting::query()->where('is_active', true)->count());
        $this->assertSame(48, RoutingOperation::query()->count());
        $this->assertSame(1, Bom::query()->where('product_id', Product::query()->where('part_number', 'HS-001')->value('id'))->where('is_active', true)->count());

        foreach (Product::query()->orderBy('part_number')->get() as $product) {
            $bom = Bom::query()->where('product_id', $product->id)->where('is_active', true)->with('items.item')->firstOrFail();
            $routing = ProductRouting::query()
                ->where('product_id', $product->id)
                ->where('is_active', true)
                ->with(['operations.machine', 'operations.mold.compatibleMachines'])
                ->firstOrFail();

            $this->assertSame('2026-11-14', $bom->created_at->toDateString());
            $this->assertSame('2026-11-14', $bom->costed_at->toDateString());
            $this->assertSame('standard_cost+routing', $bom->cost_basis);
            $this->assertGreaterThan(0, (float) $bom->total_cost);
            $this->assertSame([], $bom->cost_warnings);
            $this->assertSame('2026-11-14', $routing->created_at->toDateString());
            $this->assertCount(3, $routing->operations);
            $this->assertSame([1, 2, 3], $routing->operations->pluck('sequence')->all());

            foreach ($bom->items as $line) {
                $this->assertSame(strtoupper($line->item->unit_of_measure), strtoupper($line->unit));
                $this->assertGreaterThan(0, bccomp((string) $line->extended_cost, '0', 2));
                $this->assertSame('standard_cost', $line->cost_source);
            }

            $injection = $routing->operations->firstWhere('sequence', 1);
            $this->assertNotNull($injection->machine_id);
            $this->assertNotNull($injection->mold_id);
            $this->assertSame($product->id, $injection->mold->product_id);
            $this->assertTrue($injection->mold->compatibleMachines->contains('id', $injection->machine_id));
            $this->assertSame(Machine::query()->whereKey($injection->machine_id)->value('machine_code'), $injection->machine->machine_code);
        }

        $boms = Bom::query()->orderBy('product_id')->pluck('id')->all();
        $routings = ProductRouting::query()->orderBy('product_id')->pluck('id')->all();

        $chProduct = Product::query()->where('part_number', 'CH-001')->firstOrFail();
        $chMachineId = ProductRouting::query()->where('product_id', $chProduct->id)->where('is_active', true)
            ->firstOrFail()->operations()->where('sequence', 1)->value('machine_id');
        $machine = Machine::query()->findOrFail($chMachineId);
        app(MachineService::class)->transitionStatus($machine, MachineStatus::Maintenance, 'Synthetic maintenance replay scenario.');

        $runner->run($options);

        $this->assertSame(16, Bom::query()->count());
        $this->assertSame(44, BomItem::query()->count());
        $this->assertSame(16, ProductRouting::query()->count());
        $this->assertSame(48, RoutingOperation::query()->count());
        $this->assertSame($boms, Bom::query()->orderBy('product_id')->pluck('id')->all());
        $this->assertSame($routings, ProductRouting::query()->orderBy('product_id')->pluck('id')->all());
    }
}
