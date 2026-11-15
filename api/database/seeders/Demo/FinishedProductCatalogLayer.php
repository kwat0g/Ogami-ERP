<?php

declare(strict_types=1);

namespace Database\Seeders\Demo;

use App\Common\DemoData\DemoSeedContext;
use App\Common\DemoData\DemoSeeder;
use App\Modules\CRM\Models\Product;
use App\Modules\CRM\Services\ProductService;
use App\Modules\Inventory\Services\FinishedGoodInventoryService;

final class FinishedProductCatalogLayer extends DemoSeeder
{
    /** @var list<array{part_number: string, name: string, description: string, standard_cost: string, material: string, dimensions: array{length: float, width: float, height: float}, finish: string}> */
    private const PRODUCTS = [
        ['part_number' => 'WB-001', 'name' => 'Wiper Bushing, Standard', 'description' => 'Molded low-friction bushing for windshield wiper linkage.', 'standard_cost' => '18.50', 'material' => 'POM copolymer', 'dimensions' => ['length' => 32.0, 'width' => 24.0, 'height' => 18.0], 'finish' => 'As-molded, natural'],
        ['part_number' => 'WB-002', 'name' => 'Wiper Bushing, Heavy Duty', 'description' => 'Reinforced-duty bushing for high-load wiper linkage applications.', 'standard_cost' => '24.50', 'material' => 'POM copolymer', 'dimensions' => ['length' => 38.0, 'width' => 28.0, 'height' => 20.0], 'finish' => 'As-molded, black'],
        ['part_number' => 'PC-001', 'name' => 'Pivot Cap Cover, Type A', 'description' => 'Protective molded cap for pivot and linkage assemblies.', 'standard_cost' => '28.00', 'material' => 'ABS injection resin', 'dimensions' => ['length' => 46.0, 'width' => 34.0, 'height' => 19.0], 'finish' => 'Fine texture, black'],
        ['part_number' => 'PC-002', 'name' => 'Pivot Cap Cover, Type B', 'description' => 'Alternate molded cap configuration for pivot assemblies.', 'standard_cost' => '28.50', 'material' => 'ABS injection resin', 'dimensions' => ['length' => 48.0, 'width' => 35.0, 'height' => 20.0], 'finish' => 'Fine texture, black'],
        ['part_number' => 'RC-001', 'name' => 'Relay Cover, Standard', 'description' => 'Electrical relay enclosure cover with snap-fit retention.', 'standard_cost' => '38.00', 'material' => 'ABS injection resin', 'dimensions' => ['length' => 62.0, 'width' => 48.0, 'height' => 22.0], 'finish' => 'As-molded, black'],
        ['part_number' => 'BB-001', 'name' => 'Wiper Motor Bobbin', 'description' => 'Molded bobbin component for a wiper motor assembly.', 'standard_cost' => '22.00', 'material' => 'POM copolymer', 'dimensions' => ['length' => 27.0, 'width' => 19.0, 'height' => 16.0], 'finish' => 'As-molded, natural'],
        ['part_number' => 'BU-001', 'name' => 'Windshield Wiper Bushing', 'description' => 'Replacement bushing for windshield wiper motion transfer.', 'standard_cost' => '21.00', 'material' => 'POM copolymer', 'dimensions' => ['length' => 34.0, 'width' => 25.0, 'height' => 18.0], 'finish' => 'As-molded, natural'],
        ['part_number' => 'RC-002', 'name' => 'Relay Cover, Large', 'description' => 'Large-format electrical relay cover with protected cable relief.', 'standard_cost' => '56.00', 'material' => 'ABS injection resin', 'dimensions' => ['length' => 86.0, 'width' => 63.0, 'height' => 28.0], 'finish' => 'As-molded, black'],
        ['part_number' => 'CH-001', 'name' => 'Connector Housing, 8-Way', 'description' => 'Eight-way electrical connector shell with insert retention features.', 'standard_cost' => '34.50', 'material' => 'PA66 GF30', 'dimensions' => ['length' => 42.0, 'width' => 31.0, 'height' => 24.0], 'finish' => 'As-molded, black'],
        ['part_number' => 'FC-001', 'name' => 'Fuse Box Cover, Compact', 'description' => 'Snap-fit protective lid for a compact fuse and relay enclosure.', 'standard_cost' => '78.00', 'material' => 'PP impact copolymer', 'dimensions' => ['length' => 126.0, 'width' => 92.0, 'height' => 34.0], 'finish' => 'Fine texture, black'],
        ['part_number' => 'MH-001', 'name' => 'Mirror Housing, Outer', 'description' => 'Molded outer mirror housing for a motorcycle assembly.', 'standard_cost' => '112.00', 'material' => 'ABS injection resin', 'dimensions' => ['length' => 184.0, 'width' => 128.0, 'height' => 82.0], 'finish' => 'Paint-ready, black'],
        ['part_number' => 'HS-001', 'name' => 'Handle Switch Housing, Upper', 'description' => 'Upper shell for a handlebar switch assembly with molded bosses.', 'standard_cost' => '88.00', 'material' => 'PA6 GF30', 'dimensions' => ['length' => 96.0, 'width' => 62.0, 'height' => 43.0], 'finish' => 'As-molded, black'],
        ['part_number' => 'AD-001', 'name' => 'Air Duct Connector, 45 Degree', 'description' => 'Molded elbow connector for a filtered air intake duct.', 'standard_cost' => '62.00', 'material' => 'PP impact copolymer', 'dimensions' => ['length' => 108.0, 'width' => 76.0, 'height' => 68.0], 'finish' => 'As-molded, black'],
        ['part_number' => 'TC-001', 'name' => 'Trim Retaining Clip, Push-In', 'description' => 'Push-in retention clip for interior trim installation.', 'standard_cost' => '4.25', 'material' => 'PA66 GF30', 'dimensions' => ['length' => 28.0, 'width' => 15.0, 'height' => 12.0], 'finish' => 'As-molded, natural'],
        ['part_number' => 'BC-001', 'name' => 'Battery Cover, Lower Shell', 'description' => 'Lower protective battery enclosure shell with cable pass-through.', 'standard_cost' => '145.00', 'material' => 'PP impact copolymer', 'dimensions' => ['length' => 214.0, 'width' => 142.0, 'height' => 62.0], 'finish' => 'As-molded, black'],
        ['part_number' => 'HR-001', 'name' => 'Harness Retainer, Double Clip', 'description' => 'Molded retainer for securing two wire harness branches.', 'standard_cost' => '8.50', 'material' => 'PA66 GF30', 'dimensions' => ['length' => 44.0, 'width' => 21.0, 'height' => 17.0], 'finish' => 'As-molded, natural'],
    ];

    public function __construct(
        private readonly ProductService $products,
        private readonly FinishedGoodInventoryService $finishedGoods,
    ) {}

    public function key(): string
    {
        return 'finished-product-catalog';
    }

    /** @return list<string> */
    public function modules(): array
    {
        return ['CRM', 'Inventory'];
    }

    /** @return list<string> */
    public function dependencies(): array
    {
        return ['inventory-catalog'];
    }

    protected function seedDemoData(DemoSeedContext $context): void
    {
        $context->at($context->options->asOf, function () use ($context): void {
            foreach (self::PRODUCTS as $definition) {
                $product = Product::withTrashed()
                    ->where('part_number', $definition['part_number'])
                    ->first();

                if ($product === null) {
                    $product = $this->products->create([
                        'part_number' => $definition['part_number'],
                        'name' => $definition['name'],
                        'description' => $definition['description'],
                        'unit_of_measure' => 'PCS',
                        'standard_cost' => $definition['standard_cost'],
                        'is_active' => true,
                        'include_forecast_in_mrp' => true,
                        'specifications' => [
                            'dimensions' => [
                                ...$definition['dimensions'],
                                'unit' => 'mm',
                            ],
                            'material' => $definition['material'],
                            'finish' => $definition['finish'],
                            'tolerances' => 'Illustrative nominal tolerance: ±0.20 mm.',
                            'notes' => 'Synthetic demo specification; not a released customer drawing.',
                        ],
                        'is_customizable' => false,
                    ]);
                } elseif ($product->trashed()) {
                    $product = $this->products->restore($product);
                }

                $product = $this->ensureSyntheticSpecifications($product, $definition);

                $item = $this->finishedGoods->ensureItem($product);
                $context->records()->remember('product.'.$definition['part_number'], $product);
                $context->records()->remember('inventory.item.'.$definition['part_number'], $item);
            }
        });
    }

    /** @param array{part_number: string, name: string, description: string, standard_cost: string, material: string, dimensions: array{length: float, width: float, height: float}, finish: string} $definition */
    private function ensureSyntheticSpecifications(Product $product, array $definition): Product
    {
        $specifications = is_array($product->specifications) ? $product->specifications : [];
        $dimensions = is_array($specifications['dimensions'] ?? null) ? $specifications['dimensions'] : [];
        $unit = in_array($dimensions['unit'] ?? null, ['mm', 'cm', 'in'], true)
            ? $dimensions['unit']
            : 'mm';
        $millimetresPerUnit = match ($unit) {
            'cm' => 10.0,
            'in' => 25.4,
            default => 1.0,
        };

        foreach ($definition['dimensions'] as $axis => $millimetres) {
            if (! isset($dimensions[$axis]) || ! is_numeric($dimensions[$axis]) || (float) $dimensions[$axis] <= 0) {
                $dimensions[$axis] = round($millimetres / $millimetresPerUnit, 4);
            }
        }
        $dimensions['unit'] = $unit;
        $specifications['dimensions'] = $dimensions;

        if (! is_string($specifications['material'] ?? null) || trim($specifications['material']) === '') {
            $specifications['material'] = $definition['material'];
        }
        if (! is_string($specifications['finish'] ?? null) || trim($specifications['finish']) === '') {
            $specifications['finish'] = $definition['finish'];
        }
        if (! preg_match('/±\s*(\d+(?:\.\d+)?)/u', (string) ($specifications['tolerances'] ?? ''))) {
            $tolerance = match ($unit) {
                'cm' => '0.02 cm',
                'in' => '0.008 in',
                default => '0.20 mm',
            };
            $specifications['tolerances'] = "Illustrative nominal tolerance: ±{$tolerance}.";
        }
        if (! is_string($specifications['notes'] ?? null) || trim($specifications['notes']) === '') {
            $specifications['notes'] = 'Synthetic demo specification; not a released customer drawing.';
        }

        if ($specifications === ($product->specifications ?? [])) {
            return $product;
        }

        return $this->products->update($product, ['specifications' => $specifications]);
    }
}
