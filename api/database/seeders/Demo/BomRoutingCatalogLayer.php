<?php

declare(strict_types=1);

namespace Database\Seeders\Demo;

use App\Common\DemoData\DemoSeedContext;
use App\Common\DemoData\DemoSeeder;
use App\Modules\CRM\Models\Product;
use App\Modules\Inventory\Models\Item;
use App\Modules\MRP\Enums\MachineStatus;
use App\Modules\MRP\Models\Bom;
use App\Modules\MRP\Models\Machine;
use App\Modules\MRP\Models\Mold;
use App\Modules\MRP\Services\BomService;
use App\Modules\Production\Models\ProductRouting;
use App\Modules\Production\Services\ProductionRoutingService;
use Illuminate\Support\Collection;
use RuntimeException;

final class BomRoutingCatalogLayer extends DemoSeeder
{
    private const COST_BATCH_SIZE = '5000';

    private const ROUTING_NOTES = 'Synthetic demonstration route; confirm process times, machine rates, labor rates, and overhead with plant engineering and Finance before production use.';

    /** @var array<string, array{resin: string, quantity: string, black: bool, insert?: string}> */
    private const RECIPES = [
        'WB-001' => ['resin' => 'RM-004', 'quantity' => '0.0120', 'black' => false],
        'WB-002' => ['resin' => 'RM-004', 'quantity' => '0.0195', 'black' => true],
        'PC-001' => ['resin' => 'RM-001', 'quantity' => '0.0180', 'black' => true],
        'PC-002' => ['resin' => 'RM-001', 'quantity' => '0.0190', 'black' => true],
        'RC-001' => ['resin' => 'RM-001', 'quantity' => '0.0280', 'black' => true],
        'RC-002' => ['resin' => 'RM-001', 'quantity' => '0.0410', 'black' => true],
        'BB-001' => ['resin' => 'RM-004', 'quantity' => '0.0080', 'black' => false],
        'BU-001' => ['resin' => 'RM-004', 'quantity' => '0.0140', 'black' => false],
        'CH-001' => ['resin' => 'RM-008', 'quantity' => '0.0210', 'black' => true, 'insert' => '1.0000'],
        'FC-001' => ['resin' => 'RM-002', 'quantity' => '0.0430', 'black' => true],
        'MH-001' => ['resin' => 'RM-001', 'quantity' => '0.1050', 'black' => true],
        'HS-001' => ['resin' => 'RM-009', 'quantity' => '0.0520', 'black' => true],
        'AD-001' => ['resin' => 'RM-002', 'quantity' => '0.0480', 'black' => true],
        'TC-001' => ['resin' => 'RM-008', 'quantity' => '0.0025', 'black' => false],
        'BC-001' => ['resin' => 'RM-002', 'quantity' => '0.1650', 'black' => true],
        'HR-001' => ['resin' => 'RM-008', 'quantity' => '0.0065', 'black' => false],
    ];

    /** @var list<string> */
    private const COMMON_COMPONENTS = ['RM-010', 'RM-050', 'PKG-001'];

    public function __construct(
        private readonly BomService $boms,
        private readonly ProductionRoutingService $routings,
    ) {}

    public function key(): string
    {
        return 'bom-routing-catalog';
    }

    /** @return list<string> */
    public function modules(): array
    {
        return ['MRP', 'Production', 'Inventory'];
    }

    /** @return list<string> */
    public function dependencies(): array
    {
        return ['production-assets'];
    }

    protected function seedDemoData(DemoSeedContext $context): void
    {
        $context->at($context->options->asOf, function () use ($context): void {
            $products = Product::query()
                ->whereIn('part_number', array_keys(self::RECIPES))
                ->orderBy('part_number')
                ->get();
            if ($products->count() !== count(self::RECIPES)) {
                throw new RuntimeException('The finished-product catalog is missing one or more products required by the BOM/routing catalog.');
            }

            $componentCodes = array_values(array_unique(array_merge(
                array_column(self::RECIPES, 'resin'),
                self::COMMON_COMPONENTS,
            )));
            $items = Item::query()->whereIn('code', $componentCodes)->get()->keyBy('code');

            foreach ($products as $product) {
                $recipe = self::RECIPES[$product->part_number] ?? null;
                if ($recipe === null) {
                    throw new RuntimeException("No synthetic BOM recipe is defined for {$product->part_number}.");
                }

                $mold = Mold::query()->where('product_id', $product->id)->orderBy('mold_code')->first();
                if ($mold === null) {
                    throw new RuntimeException("No mold is registered for product {$product->part_number}.");
                }
                $machine = $this->routingMachine($product, $mold);
                if (! $machine instanceof Machine) {
                    throw new RuntimeException("No usable compatible machine is registered for mold {$mold->mold_code}.");
                }

                $operations = $this->routingOperations($mold, $machine, $this->partClass($product->part_number));
                $routing = $this->ensureRouting($product, $operations);
                $itemRows = $this->bomRows($recipe, $items);
                $bom = $this->ensureBom($product, $itemRows);

                $context->records()->remember('production.routing.'.$product->part_number, $routing);
                $context->records()->remember('mrp.bom.'.$product->part_number, $bom);
            }
        });
    }

    private function routingMachine(Product $product, Mold $mold): ?Machine
    {
        $existing = ProductRouting::query()
            ->where('product_id', $product->id)
            ->where('is_active', true)
            ->with('operations.machine')
            ->first();
        $injection = $existing?->operations->firstWhere('sequence', 1);
        $existingMachine = $injection?->machine;

        if ($injection !== null
            && (int) $injection->mold_id === (int) $mold->id
            && $existingMachine instanceof Machine
            && $mold->compatibleMachines()->whereKey($existingMachine->getKey())->exists()) {
            // A released process route remains valid while its machine is down
            // for maintenance. Reuse that route on replay instead of treating
            // temporary equipment availability as a catalog change.
            return $existingMachine;
        }

        return $mold->compatibleMachines()
            ->whereIn('machines.status', [MachineStatus::Idle->value, MachineStatus::Running->value])
            ->orderBy('machines.machine_code')
            ->first();
    }

    /** @param array<string, mixed> $recipe
     * @param  Collection<string, Item>  $items
     * @return list<array{item_id: int, quantity_per_unit: string, unit: string, waste_factor: string, sort_order: int}>
     */
    private function bomRows(array $recipe, $items): array
    {
        $rows = [];
        $this->appendBomLine($rows, $items, $recipe['resin'], $recipe['quantity'], '2.50');

        if ($recipe['black']) {
            $colorantQuantity = max(0.0001, round((float) $recipe['quantity'] * 0.02, 4));
            $this->appendBomLine($rows, $items, 'RM-010', number_format($colorantQuantity, 4, '.', ''), '1.00');
        }

        if (isset($recipe['insert'])) {
            $this->appendBomLine($rows, $items, 'RM-050', $recipe['insert'], '0.00');
        }

        $this->appendBomLine($rows, $items, 'PKG-001', '0.0200', '0.00');

        return $rows;
    }

    /**
     * @param  list<array{item_id: int, quantity_per_unit: string, unit: string, waste_factor: string, sort_order: int}>  $rows
     * @param  Collection<string, Item>  $items
     */
    private function appendBomLine(array &$rows, $items, string $itemCode, string $quantity, string $waste): void
    {
        $item = $items->get($itemCode);
        if (! $item instanceof Item || ! $item->is_active) {
            throw new RuntimeException("Active inventory component {$itemCode} is required by the BOM catalog.");
        }

        $rows[] = [
            'item_id' => (int) $item->getKey(),
            'quantity_per_unit' => $quantity,
            'unit' => (string) $item->unit_of_measure,
            'waste_factor' => $waste,
            'sort_order' => count($rows),
        ];
    }

    /** @return list<array<string, int|string|bool|null>> */
    private function routingOperations(Mold $mold, Machine $machine, string $partClass): array
    {
        $perPartSeconds = (float) $mold->cycle_time_seconds / max(1, (int) $mold->cavity_count);
        $moldingCycleMinutes = number_format(max(0.01, round($perPartSeconds / 60, 2)), 2, '.', '');
        $machineRate = number_format(450 + ((int) $machine->tonnage * 1.5), 4, '.', '');
        [$trimCycle, $packCycle] = match ($partClass) {
            'small' => ['0.03', '0.02'],
            'medium' => ['0.05', '0.03'],
            'large' => ['0.08', '0.05'],
        };

        return [
            [
                'sequence' => 1,
                'operation_name' => 'Injection molding',
                'work_center' => 'Injection Hall',
                'machine_id' => (int) $machine->getKey(),
                'mold_id' => (int) $mold->getKey(),
                'setup_time_minutes' => (string) $mold->setup_time_minutes,
                'cycle_time_minutes' => $moldingCycleMinutes,
                'labor_rate_per_hour' => '160.0000',
                'machine_rate_per_hour' => $machineRate,
                'overhead_rate_per_hour' => '250.0000',
                'description' => 'Synthetic per-piece cycle based on mold cycle time and cavity count.',
                'qc_required' => true,
            ],
            [
                'sequence' => 2,
                'operation_name' => 'Gate trimming and edge finishing',
                'work_center' => 'Finishing Cell',
                'machine_id' => null,
                'mold_id' => null,
                'setup_time_minutes' => '10.00',
                'cycle_time_minutes' => $trimCycle,
                'labor_rate_per_hour' => '140.0000',
                'machine_rate_per_hour' => '0.0000',
                'overhead_rate_per_hour' => '100.0000',
                'description' => 'Remove gate vestige and check visible edges before final inspection.',
                'qc_required' => false,
            ],
            [
                'sequence' => 3,
                'operation_name' => 'Final inspection and pack',
                'work_center' => 'Final Inspection & Packing',
                'machine_id' => null,
                'mold_id' => null,
                'setup_time_minutes' => '5.00',
                'cycle_time_minutes' => $packCycle,
                'labor_rate_per_hour' => '140.0000',
                'machine_rate_per_hour' => '0.0000',
                'overhead_rate_per_hour' => '120.0000',
                'description' => 'Perform final visual checks, place parts in protective bags, and identify the lot.',
                'qc_required' => true,
            ],
        ];
    }

    /** @param list<array<string, int|string|bool|null>> $operations */
    private function ensureRouting(Product $product, array $operations): ProductRouting
    {
        $routing = ProductRouting::query()
            ->where('product_id', $product->id)
            ->where('is_active', true)
            ->with('operations')
            ->first();

        if ($routing !== null && $this->routingMatches($routing, $operations)) {
            return $routing;
        }

        return $this->routings->create([
            'product_id' => (int) $product->getKey(),
            'notes' => self::ROUTING_NOTES,
            'operations' => $operations,
        ]);
    }

    /** @param list<array<string, int|string|bool|null>> $expected */
    private function routingMatches(ProductRouting $routing, array $expected): bool
    {
        if (! $routing->is_active || $routing->notes !== self::ROUTING_NOTES || $routing->operations->count() !== count($expected)) {
            return false;
        }

        $decimalFields = [
            'setup_time_minutes' => 2,
            'cycle_time_minutes' => 2,
            'labor_rate_per_hour' => 4,
            'machine_rate_per_hour' => 4,
            'overhead_rate_per_hour' => 4,
        ];

        foreach ($expected as $index => $operation) {
            $current = $routing->operations[$index];
            foreach (['sequence', 'operation_name', 'work_center', 'description'] as $field) {
                if ($current->{$field} !== $operation[$field]) {
                    return false;
                }
            }
            foreach (['machine_id', 'mold_id'] as $field) {
                if ($operation[$field] === null ? $current->{$field} !== null : (int) $current->{$field} !== (int) $operation[$field]) {
                    return false;
                }
            }
            foreach ($decimalFields as $field => $scale) {
                if (bccomp((string) $current->{$field}, (string) $operation[$field], $scale) !== 0) {
                    return false;
                }
            }
            if ((bool) $current->qc_required !== (bool) $operation['qc_required']) {
                return false;
            }
        }

        return true;
    }

    /** @param list<array{item_id: int, quantity_per_unit: string, unit: string, waste_factor: string, sort_order: int}> $itemRows */
    private function ensureBom(Product $product, array $itemRows): Bom
    {
        $bom = $this->boms->activeForProduct((int) $product->getKey());
        if ($bom !== null && $this->bomMatches($bom, $itemRows)) {
            return $this->boms->ensureFreshForPlanning($bom);
        }

        return $this->boms->create((int) $product->getKey(), $itemRows, self::COST_BATCH_SIZE);
    }

    /** @param list<array{item_id: int, quantity_per_unit: string, unit: string, waste_factor: string, sort_order: int}> $expected */
    private function bomMatches(Bom $bom, array $expected): bool
    {
        if (! $bom->is_active || bccomp((string) $bom->cost_batch_size, self::COST_BATCH_SIZE, 3) !== 0 || $bom->items->count() !== count($expected)) {
            return false;
        }

        foreach ($expected as $index => $row) {
            $current = $bom->items[$index];
            if ((int) $current->item_id !== $row['item_id']
                || strcasecmp((string) $current->unit, $row['unit']) !== 0
                || (int) $current->sort_order !== $row['sort_order']
                || bccomp((string) $current->quantity_per_unit, $row['quantity_per_unit'], 4) !== 0
                || bccomp((string) $current->waste_factor, $row['waste_factor'], 2) !== 0) {
                return false;
            }
        }

        return true;
    }

    private function partClass(string $partNumber): string
    {
        return match (substr($partNumber, 0, 2)) {
            'FC', 'MH', 'AD', 'BC' => 'large',
            'PC', 'RC', 'CH', 'HS' => 'medium',
            default => 'small',
        };
    }
}
