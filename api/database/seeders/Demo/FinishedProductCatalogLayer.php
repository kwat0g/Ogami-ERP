<?php

declare(strict_types=1);

namespace Database\Seeders\Demo;

use App\Common\DemoData\DemoSeedContext;
use App\Common\DemoData\DemoSeeder;
use App\Modules\CRM\Models\Product;
use App\Modules\CRM\Models\ProductImage;
use App\Modules\CRM\Services\ProductService;
use App\Modules\Inventory\Services\FinishedGoodInventoryService;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

final class FinishedProductCatalogLayer extends DemoSeeder
{
    /** @var list<array{part_number: string, name: string, description: string, image: string, standard_cost: string, material: string, dimensions: array{length: float, width: float, height: float}, finish: string, poc_examples?: list<array{part_number: string, component: string, material: string, recorded_consumption_weight: string}>}> */
    private const PRODUCTS = [
        ['part_number' => 'WB-001', 'name' => 'Washer System', 'description' => 'Ogami product family: washer system. Includes a selected POC example for Joint Washer A2070-479-00.', 'image' => 'item1.jpg', 'standard_cost' => '18.50', 'material' => 'POM copolymer', 'dimensions' => ['length' => 32.0, 'width' => 24.0, 'height' => 18.0], 'finish' => 'As-molded, natural', 'poc_examples' => [['part_number' => 'A2070-479-00', 'component' => 'Joint Washer', 'material' => 'PP IDEMITSU J-762HP + BC4C 10MB (2.5%)', 'recorded_consumption_weight' => '0.95305']],],
        ['part_number' => 'WB-002', 'name' => 'Front Wiper System', 'description' => 'Ogami product family: front wiper system.', 'image' => 'item2.jpg', 'standard_cost' => '24.50', 'material' => 'POM copolymer', 'dimensions' => ['length' => 38.0, 'width' => 28.0, 'height' => 20.0], 'finish' => 'As-molded, black'],
        ['part_number' => 'PC-001', 'name' => 'Rear Wiper System', 'description' => 'Ogami product family: rear wiper system.', 'image' => 'item3.jpg', 'standard_cost' => '28.00', 'material' => 'ABS injection resin', 'dimensions' => ['length' => 46.0, 'width' => 34.0, 'height' => 19.0], 'finish' => 'Fine texture, black'],
        ['part_number' => 'PC-002', 'name' => 'Power Window Motor', 'description' => 'Ogami product family: power window motor. Includes selected POC upper and lower motor-cover examples.', 'image' => 'item4.jpg', 'standard_cost' => '28.50', 'material' => 'ABS injection resin', 'dimensions' => ['length' => 48.0, 'width' => 35.0, 'height' => 20.0], 'finish' => 'Fine texture, black', 'poc_examples' => [['part_number' => '34995-935-10-002', 'component' => 'WM04B101A Cover Motor Upper', 'material' => 'PP T120XHF (K)', 'recorded_consumption_weight' => '72.819'], ['part_number' => '34995-936-11-009', 'component' => 'WM04B101A Cover Motor Lower', 'material' => 'PP T120XHF (K)', 'recorded_consumption_weight' => '119.879']],],
        ['part_number' => 'RC-001', 'name' => 'Power Slide Door Drive System', 'description' => 'Ogami product family: power slide door drive system.', 'image' => 'item5.jpg', 'standard_cost' => '38.00', 'material' => 'ABS injection resin', 'dimensions' => ['length' => 62.0, 'width' => 48.0, 'height' => 22.0], 'finish' => 'As-molded, black'],
        ['part_number' => 'BB-001', 'name' => 'Starter Motor', 'description' => 'Ogami product family: starter motor.', 'image' => 'item6.jpg', 'standard_cost' => '22.00', 'material' => 'POM copolymer', 'dimensions' => ['length' => 27.0, 'width' => 19.0, 'height' => 16.0], 'finish' => 'As-molded, natural'],
        ['part_number' => 'BU-001', 'name' => 'Fan Motor', 'description' => 'Ogami product family: fan motor. Includes selected POC fan-case and fan component examples.', 'image' => 'item7.jpg', 'standard_cost' => '21.00', 'material' => 'POM copolymer', 'dimensions' => ['length' => 34.0, 'width' => 25.0, 'height' => 18.0], 'finish' => 'As-molded, natural', 'poc_examples' => [['part_number' => 'M41-2-2578-101-00-3', 'component' => 'Fan Case', 'material' => 'ABS PA-777D (FBP 0CA537 Black)', 'recorded_consumption_weight' => '61.0'], ['part_number' => 'M41-2-2568-109-00-3', 'component' => 'Fan', 'material' => 'PP IDEMITSU J-762HP + BC4C 10MB (2.5%)', 'recorded_consumption_weight' => '34.75']],],
        ['part_number' => 'RC-002', 'name' => 'Electric Oil Pump', 'description' => 'Ogami product family: electric oil pump.', 'image' => 'item8.jpg', 'standard_cost' => '56.00', 'material' => 'ABS injection resin', 'dimensions' => ['length' => 86.0, 'width' => 63.0, 'height' => 28.0], 'finish' => 'As-molded, black'],
        ['part_number' => 'CH-001', 'name' => 'Electric Power Steering', 'description' => 'Ogami product family: electric power steering.', 'image' => 'item9.jpg', 'standard_cost' => '34.50', 'material' => 'PA66 GF30', 'dimensions' => ['length' => 42.0, 'width' => 31.0, 'height' => 24.0], 'finish' => 'As-molded, black'],
        ['part_number' => 'FC-001', 'name' => 'Front Wiper System, Type B', 'description' => 'Ogami product family: front wiper system, alternate Type B configuration.', 'image' => 'item2.jpg', 'standard_cost' => '78.00', 'material' => 'PP impact copolymer', 'dimensions' => ['length' => 126.0, 'width' => 92.0, 'height' => 34.0], 'finish' => 'Fine texture, black'],
        ['part_number' => 'MH-001', 'name' => 'Door Mirror Side Turn Lamp', 'description' => 'Ogami product family: door mirror side turn lamp.', 'image' => 'item10.jpg', 'standard_cost' => '112.00', 'material' => 'ABS injection resin', 'dimensions' => ['length' => 184.0, 'width' => 128.0, 'height' => 82.0], 'finish' => 'Paint-ready, black'],
        ['part_number' => 'HS-001', 'name' => 'Electric Power Steering, Type B', 'description' => 'Ogami product family: electric power steering, alternate Type B configuration.', 'image' => 'item9.jpg', 'standard_cost' => '88.00', 'material' => 'PA6 GF30', 'dimensions' => ['length' => 96.0, 'width' => 62.0, 'height' => 43.0], 'finish' => 'As-molded, black'],
        ['part_number' => 'AD-001', 'name' => 'Fan Motor, Type B', 'description' => 'Ogami product family: fan motor, alternate Type B configuration.', 'image' => 'item7.jpg', 'standard_cost' => '62.00', 'material' => 'PP impact copolymer', 'dimensions' => ['length' => 108.0, 'width' => 76.0, 'height' => 68.0], 'finish' => 'As-molded, black'],
        ['part_number' => 'TC-001', 'name' => 'Two-Wheel AC Generator (ACG) Starter', 'description' => 'Ogami product family: two-wheel AC generator and ACG starter.', 'image' => 'item12.jpg', 'standard_cost' => '4.25', 'material' => 'PA66 GF30', 'dimensions' => ['length' => 28.0, 'width' => 15.0, 'height' => 12.0], 'finish' => 'As-molded, natural'],
        ['part_number' => 'BC-001', 'name' => 'Electric Oil Pump, Type B', 'description' => 'Ogami product family: electric oil pump, alternate Type B configuration.', 'image' => 'item8.jpg', 'standard_cost' => '145.00', 'material' => 'PP impact copolymer', 'dimensions' => ['length' => 214.0, 'width' => 142.0, 'height' => 62.0], 'finish' => 'As-molded, black'],
        ['part_number' => 'HR-001', 'name' => 'Harness Protector', 'description' => 'Ogami product family: harness protector. Includes selected POC Clip Wire Harness example 53904-405-00.', 'image' => 'item11.jpg', 'standard_cost' => '8.50', 'material' => 'PA66 GF30', 'dimensions' => ['length' => 44.0, 'width' => 21.0, 'height' => 17.0], 'finish' => 'As-molded, natural', 'poc_examples' => [['part_number' => '53904-405-00', 'component' => 'Clip Wire Harness (Old Mold)', 'material' => 'PA66 A3W NATURAL', 'recorded_consumption_weight' => '0.77']],],
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
                            'notes' => 'Illustrative specification only; not a released customer drawing.',
                        ],
                        'is_customizable' => false,
                    ]);
                } elseif ($product->trashed()) {
                    $product = $this->products->restore($product);
                }

                if ($product->name !== $definition['name'] || $product->description !== $definition['description']) {
                    $product = $this->products->update($product, [
                        'name' => $definition['name'],
                        'description' => $definition['description'],
                    ]);
                }

                $product = $this->ensureSyntheticSpecifications($product, $definition);
                $product = $this->ensureProductImage($product, $definition);

                $item = $this->finishedGoods->ensureItem($product);
                $context->records()->remember('product.'.$definition['part_number'], $product);
                $context->records()->remember('inventory.item.'.$definition['part_number'], $item);
            }
        });
    }

    /** @param array{part_number: string, name: string, description: string, image: string, standard_cost: string, material: string, dimensions: array{length: float, width: float, height: float}, finish: string, poc_examples?: list<array{part_number: string, component: string, material: string, recorded_consumption_weight: string}>} $definition */
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
            $specifications['notes'] = 'Illustrative specification only; not a released customer drawing.';
        }
        if (isset($definition['poc_examples'])) {
            $specifications['company_poc_examples'] = array_map(
                static fn (array $example): array => [
                    ...$example,
                    'weight_unit' => 'Not stated in source workbook',
                ],
                $definition['poc_examples'],
            );
        }

        if ($specifications === ($product->specifications ?? [])) {
            return $product;
        }

        return $this->products->update($product, ['specifications' => $specifications]);
    }

    /** @param array{part_number: string, name: string, description: string, image: string, standard_cost: string, material: string, dimensions: array{length: float, width: float, height: float}, finish: string, poc_examples?: list<array{part_number: string, component: string, material: string, recorded_consumption_weight: string}>} $definition */
    private function ensureProductImage(Product $product, array $definition): Product
    {
        $assetPath = database_path('seeders/Demo/assets/products/'.$definition['image']);
        if (! is_file($assetPath)) {
            throw new RuntimeException("Official Ogami product image {$definition['image']} is missing from the demo seeder assets.");
        }

        $filePath = 'product-images/ogami/'.$definition['image'];
        $disk = Storage::disk('local');
        if (! $disk->exists($filePath) || hash_file('sha256', $assetPath) !== hash('sha256', $disk->get($filePath))) {
            $bytes = file_get_contents($assetPath);
            if ($bytes === false || ! $disk->put($filePath, $bytes)) {
                throw new RuntimeException("Unable to copy official Ogami product image {$definition['image']} into application storage.");
            }
        }

        $productImage = ProductImage::query()->updateOrCreate(
            ['product_id' => $product->id, 'file_path' => $filePath],
            [
                'filename' => $definition['image'],
                'mime' => 'image/jpeg',
                'size' => $disk->size($filePath),
                'position' => 0,
                'alt_text' => $definition['name'].' — product image from Ogami Corporation',
                'is_primary' => true,
            ],
        );
        $product->images()->where('id', '<>', $productImage->id)->update(['is_primary' => false]);

        if ($product->image_path !== $filePath) {
            $product = $this->products->update($product, ['image_path' => $filePath]);
        }

        return $product;
    }
}
