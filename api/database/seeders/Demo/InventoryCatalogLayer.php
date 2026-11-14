<?php

declare(strict_types=1);

namespace Database\Seeders\Demo;

use App\Common\DemoData\DemoSeedContext;
use App\Common\DemoData\DemoSeeder;
use App\Modules\Inventory\Models\Item;
use App\Modules\Inventory\Models\ItemCategory;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Inventory\Models\WarehouseLocation;
use App\Modules\Inventory\Models\WarehouseZone;
use App\Modules\Inventory\Services\ItemCategoryService;
use App\Modules\Inventory\Services\ItemService;
use App\Modules\Inventory\Services\WarehouseService;
use Database\Seeders\UomSeeder;
use RuntimeException;

final class InventoryCatalogLayer extends DemoSeeder
{
    /** @var list<array{code: string, name: string, type: string, locations: int}> */
    private const ZONES = [
        ['code' => 'A', 'name' => 'Raw Materials', 'type' => 'raw_materials', 'locations' => 20],
        ['code' => 'B', 'name' => 'Production Staging', 'type' => 'staging', 'locations' => 8],
        ['code' => 'C', 'name' => 'Finished Goods', 'type' => 'finished_goods', 'locations' => 30],
        ['code' => 'D', 'name' => 'Maintenance Spare Parts', 'type' => 'spare_parts', 'locations' => 10],
        ['code' => 'Q', 'name' => 'Quality Quarantine', 'type' => 'quarantine', 'locations' => 4],
        ['code' => 'S', 'name' => 'Scrap Hold', 'type' => 'scrap', 'locations' => 4],
    ];

    /** @var list<array{code: string, name: string, description: string, category: string, uom: string, cost: string, reorder: string, safety: string, moq: string, lead: int, critical: bool}> */
    private const ITEMS = [
        ['code' => 'RM-001', 'name' => 'ABS injection resin, natural', 'description' => 'General-purpose ABS resin for interior trim and connector housings.', 'category' => 'Resins', 'uom' => 'KG', 'cost' => '120.0000', 'reorder' => '500.000', 'safety' => '200.000', 'moq' => '25.000', 'lead' => 14, 'critical' => false],
        ['code' => 'RM-002', 'name' => 'PP impact copolymer resin, natural', 'description' => 'Impact-modified polypropylene resin for covers and duct components.', 'category' => 'Resins', 'uom' => 'KG', 'cost' => '95.0000', 'reorder' => '500.000', 'safety' => '200.000', 'moq' => '25.000', 'lead' => 14, 'critical' => false],
        ['code' => 'RM-003', 'name' => 'PA6 resin, natural', 'description' => 'Injection-grade polyamide 6 for clips and structural brackets.', 'category' => 'Resins', 'uom' => 'KG', 'cost' => '150.0000', 'reorder' => '300.000', 'safety' => '120.000', 'moq' => '25.000', 'lead' => 21, 'critical' => true],
        ['code' => 'RM-004', 'name' => 'POM copolymer resin, natural', 'description' => 'Low-friction acetal resin for bushings and moving components.', 'category' => 'Resins', 'uom' => 'KG', 'cost' => '180.0000', 'reorder' => '300.000', 'safety' => '100.000', 'moq' => '25.000', 'lead' => 21, 'critical' => false],
        ['code' => 'RM-005', 'name' => 'PC injection resin, clear', 'description' => 'Polycarbonate resin for transparent indicator and lamp components.', 'category' => 'Resins', 'uom' => 'KG', 'cost' => '220.0000', 'reorder' => '200.000', 'safety' => '80.000', 'moq' => '25.000', 'lead' => 21, 'critical' => false],
        ['code' => 'RM-006', 'name' => 'PBT GF30 resin, black', 'description' => '30 percent glass-filled PBT resin for electrical housings.', 'category' => 'Resins', 'uom' => 'KG', 'cost' => '190.0000', 'reorder' => '250.000', 'safety' => '100.000', 'moq' => '25.000', 'lead' => 21, 'critical' => false],
        ['code' => 'RM-007', 'name' => 'HDPE injection resin, natural', 'description' => 'High-density polyethylene for caps and protective covers.', 'category' => 'Resins', 'uom' => 'KG', 'cost' => '75.0000', 'reorder' => '250.000', 'safety' => '100.000', 'moq' => '25.000', 'lead' => 14, 'critical' => false],
        ['code' => 'RM-008', 'name' => 'PA66 GF30 resin, black', 'description' => '30 percent glass-filled nylon 66 for heat-resistant brackets.', 'category' => 'Resins', 'uom' => 'KG', 'cost' => '240.0000', 'reorder' => '200.000', 'safety' => '80.000', 'moq' => '25.000', 'lead' => 28, 'critical' => true],
        ['code' => 'RM-010', 'name' => 'Black masterbatch, carbon black', 'description' => 'Concentrated black colorant for injection molding compounds.', 'category' => 'Colorants', 'uom' => 'KG', 'cost' => '250.0000', 'reorder' => '50.000', 'safety' => '20.000', 'moq' => '5.000', 'lead' => 7, 'critical' => false],
        ['code' => 'RM-011', 'name' => 'White masterbatch, titanium dioxide', 'description' => 'White color concentrate for molded covers and caps.', 'category' => 'Colorants', 'uom' => 'KG', 'cost' => '280.0000', 'reorder' => '50.000', 'safety' => '20.000', 'moq' => '5.000', 'lead' => 7, 'critical' => false],
        ['code' => 'RM-012', 'name' => 'Gray masterbatch, neutral gray', 'description' => 'Neutral gray color concentrate for interior trim components.', 'category' => 'Colorants', 'uom' => 'KG', 'cost' => '260.0000', 'reorder' => '40.000', 'safety' => '15.000', 'moq' => '5.000', 'lead' => 7, 'critical' => false],
        ['code' => 'RM-050', 'name' => 'Brass insert, small, M4', 'description' => 'Threaded insert for molded connector and cover assemblies.', 'category' => 'Metal Inserts', 'uom' => 'PCS', 'cost' => '5.5000', 'reorder' => '5000.000', 'safety' => '2000.000', 'moq' => '1000.000', 'lead' => 21, 'critical' => false],
        ['code' => 'RM-051', 'name' => 'Steel insert, large, M6', 'description' => 'Zinc-plated steel insert used in load-bearing plastic brackets.', 'category' => 'Metal Inserts', 'uom' => 'PCS', 'cost' => '8.0000', 'reorder' => '4000.000', 'safety' => '1500.000', 'moq' => '1000.000', 'lead' => 21, 'critical' => false],
        ['code' => 'RM-052', 'name' => 'Steel core insert, bobbin type', 'description' => 'Precision metal core insert for electrical connector components.', 'category' => 'Metal Inserts', 'uom' => 'PCS', 'cost' => '12.0000', 'reorder' => '3000.000', 'safety' => '1000.000', 'moq' => '500.000', 'lead' => 28, 'critical' => true],
        ['code' => 'PKG-001', 'name' => 'PE protective bag, standard', 'description' => 'Protective bag for finished molded parts during internal handling.', 'category' => 'Packaging', 'uom' => 'PCS', 'cost' => '0.5000', 'reorder' => '2000.000', 'safety' => '500.000', 'moq' => '500.000', 'lead' => 5, 'critical' => false],
        ['code' => 'PKG-002', 'name' => 'Shipping carton, 50-piece pack', 'description' => 'Double-wall carton used for customer shipments of molded parts.', 'category' => 'Packaging', 'uom' => 'PCS', 'cost' => '15.0000', 'reorder' => '1000.000', 'safety' => '300.000', 'moq' => '100.000', 'lead' => 5, 'critical' => false],
        ['code' => 'PKG-003', 'name' => 'Traceability label roll', 'description' => 'Thermal transfer labels for lot, part number, and revision identification.', 'category' => 'Packaging', 'uom' => 'ROLL', 'cost' => '125.0000', 'reorder' => '30.000', 'safety' => '10.000', 'moq' => '5.000', 'lead' => 7, 'critical' => true],
        ['code' => 'PKG-004', 'name' => 'Reusable plastic pallet', 'description' => 'Returnable pallet for protected finished-goods transport.', 'category' => 'Packaging', 'uom' => 'PALLET', 'cost' => '180.0000', 'reorder' => '40.000', 'safety' => '12.000', 'moq' => '10.000', 'lead' => 14, 'critical' => false],
        ['code' => 'CONS-001', 'name' => 'Mold release agent', 'description' => 'Approved release agent for tooling setup and maintenance work.', 'category' => 'Process Consumables', 'uom' => 'L', 'cost' => '320.0000', 'reorder' => '20.000', 'safety' => '8.000', 'moq' => '4.000', 'lead' => 7, 'critical' => false],
        ['code' => 'CONS-002', 'name' => 'Injection molding purge compound', 'description' => 'Purge compound for resin and color changeover cleaning.', 'category' => 'Process Consumables', 'uom' => 'KG', 'cost' => '190.0000', 'reorder' => '25.000', 'safety' => '10.000', 'moq' => '5.000', 'lead' => 7, 'critical' => false],
        ['code' => 'CONS-003', 'name' => 'Mold cleaning fluid', 'description' => 'Cleaning fluid for routine tooling upkeep and residue removal.', 'category' => 'Process Consumables', 'uom' => 'L', 'cost' => '260.0000', 'reorder' => '12.000', 'safety' => '5.000', 'moq' => '4.000', 'lead' => 7, 'critical' => false],
        ['code' => 'SP-001', 'name' => 'Barrel heater band, 45 mm', 'description' => 'Replacement heater band for injection barrel temperature zones.', 'category' => 'Spare Parts', 'uom' => 'PCS', 'cost' => '850.0000', 'reorder' => '6.000', 'safety' => '2.000', 'moq' => '2.000', 'lead' => 14, 'critical' => true],
        ['code' => 'SP-002', 'name' => 'Type K thermocouple probe', 'description' => 'Temperature probe for injection barrel and mold monitoring.', 'category' => 'Spare Parts', 'uom' => 'PCS', 'cost' => '650.0000', 'reorder' => '8.000', 'safety' => '3.000', 'moq' => '2.000', 'lead' => 14, 'critical' => true],
        ['code' => 'SP-003', 'name' => 'Hydraulic return-line filter', 'description' => 'Replacement return filter for hydraulic injection molding equipment.', 'category' => 'Spare Parts', 'uom' => 'PCS', 'cost' => '1200.0000', 'reorder' => '5.000', 'safety' => '2.000', 'moq' => '1.000', 'lead' => 14, 'critical' => true],
        ['code' => 'SP-004', 'name' => 'Injection nozzle tip, 3 mm orifice', 'description' => 'Wear replacement nozzle tip for compatible injection units.', 'category' => 'Spare Parts', 'uom' => 'PCS', 'cost' => '5800.0000', 'reorder' => '2.000', 'safety' => '1.000', 'moq' => '1.000', 'lead' => 28, 'critical' => true],
        ['code' => 'SP-005', 'name' => 'Hopper dryer drive belt', 'description' => 'Replacement drive belt for resin hopper dryer auxiliary equipment.', 'category' => 'Spare Parts', 'uom' => 'PCS', 'cost' => '700.0000', 'reorder' => '4.000', 'safety' => '1.000', 'moq' => '1.000', 'lead' => 14, 'critical' => false],
        ['code' => 'SP-006', 'name' => 'Photoelectric safety sensor', 'description' => 'Machine guarding sensor for compatible press and conveyor applications.', 'category' => 'Spare Parts', 'uom' => 'PCS', 'cost' => '950.0000', 'reorder' => '3.000', 'safety' => '1.000', 'moq' => '1.000', 'lead' => 21, 'critical' => true],
    ];

    public function __construct(
        private readonly ItemCategoryService $categories,
        private readonly ItemService $items,
        private readonly WarehouseService $warehouses,
        private readonly UomSeeder $uoms,
    ) {}

    public function key(): string
    {
        return 'inventory-catalog';
    }

    /** @return list<string> */
    public function modules(): array
    {
        return ['Inventory'];
    }

    /** @return list<string> */
    public function dependencies(): array
    {
        return [];
    }

    protected function seedDemoData(DemoSeedContext $context): void
    {
        $context->at($context->options->asOf, function () use ($context): void {
            // UOMs are static reference lookups; the app has an idempotent
            // UomSeeder but no UomService.
            $this->uoms->run();
            $categoryIds = $this->seedCategories($context);
            $this->seedWarehouse($context);
            $this->seedItems($context, $categoryIds);
        });
    }

    /** @return array<string, int> */
    private function seedCategories(DemoSeedContext $context): array
    {
        $definitions = [
            ['key' => 'raw-materials', 'name' => 'Raw Materials', 'type' => 'raw_material', 'parent' => null],
            ['key' => 'packaging', 'name' => 'Packaging', 'type' => 'packaging', 'parent' => null],
            ['key' => 'finished-goods', 'name' => 'Finished Goods', 'type' => 'finished_good', 'parent' => null],
            ['key' => 'spare-parts', 'name' => 'Spare Parts', 'type' => 'spare_part', 'parent' => null],
            ['key' => 'resins', 'name' => 'Resins', 'type' => null, 'parent' => 'raw-materials'],
            ['key' => 'colorants', 'name' => 'Colorants', 'type' => null, 'parent' => 'raw-materials'],
            ['key' => 'metal-inserts', 'name' => 'Metal Inserts', 'type' => null, 'parent' => 'raw-materials'],
            ['key' => 'process-consumables', 'name' => 'Process Consumables', 'type' => null, 'parent' => 'raw-materials'],
        ];
        $ids = [];

        foreach ($definitions as $definition) {
            $parentId = $definition['parent'] === null ? null : $ids[$definition['parent']];
            $category = ItemCategory::withTrashed()
                ->where('name', $definition['name'])
                ->where('parent_id', $parentId)
                ->first();

            if ($category === null) {
                $payload = ['name' => $definition['name'], 'parent_id' => $parentId];
                if ($definition['type'] !== null) {
                    $payload['item_type'] = $definition['type'];
                }
                $category = $this->categories->create($payload);
            } elseif ($category->trashed()) {
                throw new RuntimeException("Inventory category {$definition['name']} is archived and has no restore action in the current application.");
            }

            $ids[$definition['key']] = (int) $category->getKey();
            $context->records()->remember('inventory.category.'.$definition['key'], $category);
        }

        return $ids;
    }

    private function seedWarehouse(DemoSeedContext $context): void
    {
        $warehouse = Warehouse::withTrashed()->where('code', 'MW')->first();
        $warehouseData = [
            'name' => 'Main Warehouse',
            'code' => 'MW',
            'address' => 'FCIE Special Economic Zone, Dasmariñas, Cavite',
            'is_active' => true,
        ];

        if ($warehouse === null) {
            $warehouse = $this->warehouses->createWarehouse($warehouseData);
        } elseif ($warehouse->trashed()) {
            $warehouse = $this->warehouses->restoreWarehouse($warehouse);
        } elseif ($warehouse->only(array_keys($warehouseData)) !== $warehouseData) {
            $this->warehouses->updateWarehouse($warehouse, $warehouseData);
            $warehouse->refresh();
        }

        $context->records()->remember('inventory.warehouse.MW', $warehouse);

        foreach (self::ZONES as $definition) {
            $zone = WarehouseZone::withTrashed()
                ->where('warehouse_id', $warehouse->id)
                ->where('code', $definition['code'])
                ->first();
            $zoneData = [
                'warehouse_id' => (int) $warehouse->id,
                'name' => $definition['name'],
                'code' => $definition['code'],
                'zone_type' => $definition['type'],
            ];

            if ($zone === null) {
                $zone = $this->warehouses->createZone($zoneData);
            } elseif ($zone->trashed()) {
                $zone = $this->warehouses->restoreZone($zone);
            } else {
                $changes = [];
                if ($zone->name !== $zoneData['name']) {
                    $changes['name'] = $zoneData['name'];
                }
                if ($zone->zone_type->value !== $zoneData['zone_type']) {
                    $changes['zone_type'] = $zoneData['zone_type'];
                }
                if ($changes !== []) {
                    $zone = $this->warehouses->updateZone($zone, $changes);
                }
            }

            $context->records()->remember('inventory.zone.'.$definition['code'], $zone);

            for ($index = 1; $index <= $definition['locations']; $index++) {
                $code = sprintf('%s-%02d', $definition['code'], $index);
                $location = WarehouseLocation::withTrashed()->where('code', $code)->first();
                $rack = sprintf('R%02d', (int) ceil($index / 4));
                $bin = sprintf('B%02d', (($index - 1) % 4) + 1);
                $locationData = [
                    'zone_id' => (int) $zone->id,
                    'code' => $code,
                    'rack' => $rack,
                    'bin' => $bin,
                    'is_active' => true,
                ];

                if ($location === null) {
                    $location = $this->warehouses->createLocation($locationData);
                } elseif ($location->trashed()) {
                    $location = $this->warehouses->restoreLocation($location);
                } else {
                    $changes = [];
                    foreach (['zone_id', 'rack', 'bin'] as $field) {
                        if ((string) $location->{$field} !== (string) $locationData[$field]) {
                            $changes[$field] = $locationData[$field];
                        }
                    }
                    if (! $location->is_active) {
                        $changes['is_active'] = true;
                    }
                    if ($changes !== []) {
                        $location = $this->warehouses->updateLocation($location, $changes);
                    }
                }

                $context->records()->remember('inventory.location.'.$definition['code'].'.'.$code, $location);
            }
        }
    }

    /** @param array<string, int> $categoryIds */
    private function seedItems(DemoSeedContext $context, array $categoryIds): void
    {
        $categoryKeys = [
            'Resins' => 'resins',
            'Colorants' => 'colorants',
            'Metal Inserts' => 'metal-inserts',
            'Process Consumables' => 'process-consumables',
            'Packaging' => 'packaging',
            'Spare Parts' => 'spare-parts',
        ];

        foreach (self::ITEMS as $definition) {
            $item = Item::withTrashed()->where('code', $definition['code'])->first();
            if ($item === null) {
                $item = $this->items->create([
                    'code' => $definition['code'],
                    'name' => $definition['name'],
                    'description' => $definition['description'],
                    'category_id' => $categoryIds[$categoryKeys[$definition['category']]],
                    'unit_of_measure' => $definition['uom'],
                    'standard_cost' => $definition['cost'],
                    'reorder_method' => 'fixed_quantity',
                    'reorder_point' => $definition['reorder'],
                    'safety_stock' => $definition['safety'],
                    'minimum_order_quantity' => $definition['moq'],
                    'lead_time_days' => $definition['lead'],
                    'is_critical' => $definition['critical'],
                    'is_active' => true,
                ]);
            } elseif ($item->trashed()) {
                $this->items->restore($item);
                $item->refresh();
            }

            $context->records()->remember('inventory.item.'.$definition['code'], $item);
        }
    }
}
