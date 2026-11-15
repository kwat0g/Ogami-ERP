<?php

declare(strict_types=1);

namespace Database\Seeders\Demo;

use App\Common\DemoData\DemoSeedContext;
use App\Common\DemoData\DemoSeeder;
use App\Common\Exceptions\BusinessRuleException;
use App\Modules\Auth\Models\User;
use App\Modules\Inventory\Enums\ItemType;
use App\Modules\Inventory\Models\Item;
use App\Modules\Inventory\Models\ItemCategory;
use App\Modules\Inventory\Services\ItemService;
use RuntimeException;

/** Seeds the service-backed spare part catalog without creating stock balances. */
final class MaintenanceSparePartsCatalogLayer extends DemoSeeder
{
    private const DESCRIPTION_PREFIX = 'Maintenance spare part for plant equipment.';

    /** @var array<string, int> */
    private const TARGET_COUNTS = ['small' => 20, 'demo' => 100, 'full' => 140];

    /**
     * Each family uses app-supported item fields. Costs and replenishment values are
     * illustrative and must be replaced with approved vendor and plant data before
     * operational use.
     *
     * @var list<array{name: string, equipment_class: string, unit: string, base_cost: string, cost_step: string, reorder: string, safety: string, moq: string, lead_days: int, critical: bool, variants: list<string>}>
     */
    private const FAMILIES = [
        [
            'name' => 'Barrel heater band, %s', 'equipment_class' => 'injection barrel', 'unit' => 'PCS',
            'base_cost' => '850.0000', 'cost_step' => '25.0000', 'reorder' => '6.000', 'safety' => '2.000', 'moq' => '2.000', 'lead_days' => 14, 'critical' => true,
            'variants' => ['38 mm', '42 mm', '45 mm', '50 mm', '55 mm', '60 mm', '65 mm', '70 mm', '75 mm', '80 mm', '85 mm', '90 mm', '95 mm', '100 mm'],
        ],
        [
            'name' => 'Type K thermocouple probe, %s', 'equipment_class' => 'injection barrel and mold temperature circuit', 'unit' => 'PCS',
            'base_cost' => '650.0000', 'cost_step' => '35.0000', 'reorder' => '8.000', 'safety' => '3.000', 'moq' => '2.000', 'lead_days' => 14, 'critical' => true,
            'variants' => ['100 mm grounded, 6 mm sheath', '150 mm grounded, 6 mm sheath', '200 mm grounded, 6 mm sheath', '250 mm grounded, 6 mm sheath', '300 mm grounded, 6 mm sheath', '400 mm grounded, 6 mm sheath', '500 mm grounded, 6 mm sheath', '100 mm ungrounded, 3 mm sheath', '150 mm ungrounded, 3 mm sheath', '200 mm ungrounded, 3 mm sheath', '250 mm ungrounded, 3 mm sheath', '300 mm ungrounded, 3 mm sheath', 'Junction-head, 150 mm probe', 'Junction-head, 300 mm probe'],
        ],
        [
            'name' => 'Hydraulic filter element, %s', 'equipment_class' => 'injection press hydraulic circuit', 'unit' => 'PCS',
            'base_cost' => '1200.0000', 'cost_step' => '90.0000', 'reorder' => '5.000', 'safety' => '2.000', 'moq' => '1.000', 'lead_days' => 21, 'critical' => true,
            'variants' => ['return, 10 micron, 1/2 in', 'return, 25 micron, 1/2 in', 'return, 10 micron, 3/4 in', 'return, 25 micron, 3/4 in', 'pressure, 5 micron, 1/2 in', 'pressure, 10 micron, 1/2 in', 'pressure, 5 micron, 3/4 in', 'pressure, 10 micron, 3/4 in', 'suction strainer, 80 mesh, 1 in', 'suction strainer, 100 mesh, 1 in', 'suction strainer, 80 mesh, 1-1/4 in', 'suction strainer, 100 mesh, 1-1/4 in', 'breather filter, 10 micron, 1/2 in', 'breather filter, 25 micron, 1/2 in'],
        ],
        [
            'name' => 'Injection nozzle tip, %s', 'equipment_class' => 'injection unit nozzle', 'unit' => 'PCS',
            'base_cost' => '2800.0000', 'cost_step' => '180.0000', 'reorder' => '2.000', 'safety' => '1.000', 'moq' => '1.000', 'lead_days' => 28, 'critical' => true,
            'variants' => ['2 mm orifice, 45 mm body', '2.5 mm orifice, 45 mm body', '3 mm orifice, 45 mm body', '3.5 mm orifice, 45 mm body', '4 mm orifice, 45 mm body', '5 mm orifice, 45 mm body', '6 mm orifice, 45 mm body', '2 mm orifice, 60 mm body', '2.5 mm orifice, 60 mm body', '3 mm orifice, 60 mm body', '3.5 mm orifice, 60 mm body', '4 mm orifice, 60 mm body', '5 mm orifice, 60 mm body', '6 mm orifice, 60 mm body'],
        ],
        [
            'name' => 'Proximity or safety sensor, %s', 'equipment_class' => 'press guarding and auxiliary interlock', 'unit' => 'PCS',
            'base_cost' => '950.0000', 'cost_step' => '85.0000', 'reorder' => '3.000', 'safety' => '1.000', 'moq' => '1.000', 'lead_days' => 21, 'critical' => true,
            'variants' => ['inductive M12, PNP normally open, 2 mm', 'inductive M12, NPN normally open, 2 mm', 'inductive M18, PNP normally open, 5 mm', 'inductive M18, NPN normally open, 5 mm', 'inductive M30, PNP normally open, 10 mm', 'inductive M30, NPN normally open, 10 mm', 'photoelectric diffuse, 100 mm range', 'photoelectric diffuse, 300 mm range', 'photoelectric retroreflective, 2 m range', 'photoelectric through-beam receiver, 5 m', 'roller-lever limit switch, standard travel', 'roller-plunger limit switch, short travel', 'safety-door magnetic interlock, 2 NC', 'light-curtain receiver, 300 mm protected height'],
        ],
        [
            'name' => 'Mold ejector pin, %s', 'equipment_class' => 'injection mold ejector plate', 'unit' => 'PCS',
            'base_cost' => '220.0000', 'cost_step' => '18.0000', 'reorder' => '20.000', 'safety' => '8.000', 'moq' => '10.000', 'lead_days' => 10, 'critical' => false,
            'variants' => ['3 mm × 100 mm', '3 mm × 150 mm', '4 mm × 100 mm', '4 mm × 150 mm', '5 mm × 100 mm', '5 mm × 150 mm', '6 mm × 100 mm', '6 mm × 150 mm', '8 mm × 100 mm', '8 mm × 150 mm', '10 mm × 100 mm', '10 mm × 150 mm', '12 mm × 150 mm', '12 mm × 200 mm'],
        ],
        [
            'name' => 'Hydraulic solenoid valve coil or spool, %s', 'equipment_class' => 'press hydraulic control manifold', 'unit' => 'PCS',
            'base_cost' => '1800.0000', 'cost_step' => '140.0000', 'reorder' => '3.000', 'safety' => '1.000', 'moq' => '1.000', 'lead_days' => 28, 'critical' => true,
            'variants' => ['24 VDC coil, DIN A connector', '24 VDC coil, DIN B connector', '110 VAC coil, DIN A connector', '220 VAC coil, DIN A connector', '220 VAC coil, DIN B connector', '4/2 spool, closed center', '4/2 spool, open center', '4/3 spool, closed center', '4/3 spool, open center', '4/3 spool, tandem center', 'pilot valve coil, 24 VDC', 'pilot valve coil, 220 VAC', 'proportional valve coil, 24 VDC', 'manual override valve stem'],
        ],
        [
            'name' => 'Mold O-ring or seal, %s', 'equipment_class' => 'mold cooling, ejector, and hydraulic seal points', 'unit' => 'PCS',
            'base_cost' => '45.0000', 'cost_step' => '8.0000', 'reorder' => '50.000', 'safety' => '20.000', 'moq' => '25.000', 'lead_days' => 7, 'critical' => false,
            'variants' => ['NBR, 10 × 2 mm', 'NBR, 12 × 2 mm', 'NBR, 16 × 2.5 mm', 'NBR, 20 × 2.5 mm', 'NBR, 25 × 3 mm', 'NBR, 30 × 3 mm', 'FKM, 10 × 2 mm', 'FKM, 12 × 2 mm', 'FKM, 16 × 2.5 mm', 'FKM, 20 × 2.5 mm', 'FKM, 25 × 3 mm', 'FKM, 30 × 3 mm', 'hydraulic rod seal, 25 mm bore', 'hydraulic rod seal, 32 mm bore'],
        ],
        [
            'name' => 'Mold cooling quick coupling, %s', 'equipment_class' => 'mold water manifold and cooling circuit', 'unit' => 'PCS',
            'base_cost' => '480.0000', 'cost_step' => '42.0000', 'reorder' => '12.000', 'safety' => '4.000', 'moq' => '4.000', 'lead_days' => 14, 'critical' => false,
            'variants' => ['straight, 1/8 in BSP, 6 mm hose', 'straight, 1/4 in BSP, 6 mm hose', 'straight, 1/4 in BSP, 8 mm hose', 'straight, 3/8 in BSP, 8 mm hose', 'straight, 3/8 in BSP, 10 mm hose', 'straight, 1/2 in BSP, 10 mm hose', '90 degree, 1/8 in BSP, 6 mm hose', '90 degree, 1/4 in BSP, 6 mm hose', '90 degree, 1/4 in BSP, 8 mm hose', '90 degree, 3/8 in BSP, 8 mm hose', '90 degree, 3/8 in BSP, 10 mm hose', '90 degree, 1/2 in BSP, 10 mm hose', 'plug, 1/4 in BSP, 8 mm hose', 'plug, 3/8 in BSP, 10 mm hose'],
        ],
        [
            'name' => 'Auxiliary equipment service part, %s', 'equipment_class' => 'hopper dryer, loader, chiller, granulator, and conveyor', 'unit' => 'PCS',
            'base_cost' => '700.0000', 'cost_step' => '65.0000', 'reorder' => '4.000', 'safety' => '1.000', 'moq' => '1.000', 'lead_days' => 14, 'critical' => false,
            'variants' => ['hopper dryer belt, 5 mm × 600 mm', 'hopper dryer belt, 8 mm × 800 mm', 'loader filter element, 10 micron', 'loader filter element, 25 micron', 'loader vacuum hose, 25 mm × 2 m', 'loader vacuum hose, 32 mm × 2 m', 'chiller pump seal kit, 1/2 in', 'chiller pump seal kit, 3/4 in', 'granulator knife, 100 mm reversible', 'granulator knife, 150 mm reversible', 'conveyor roller bearing, 6202-2RS', 'conveyor roller bearing, 6203-2RS', 'mold temperature controller heater, 3 kW', 'mold temperature controller pump seal, 1/2 in'],
        ],
    ];

    public function __construct(private readonly ItemService $items) {}

    public function key(): string
    {
        return 'maintenance-spare-parts';
    }

    /** @return list<string> */
    public function modules(): array
    {
        return ['Maintenance', 'Inventory'];
    }

    /** @return list<string> */
    public function dependencies(): array
    {
        return ['inventory-catalog', 'production-assets', 'workforce-identities'];
    }

    /** @return list<array{code: string, name: string, description: string, category_id: int, unit_of_measure: string, item_type: string, standard_cost: string, reorder_method: string, reorder_point: string, safety_stock: string, minimum_order_quantity: string, lead_time_days: int, is_critical: bool, is_active: bool}> */
    public function plannedDefinitions(string $scale, int $categoryId = 0): array
    {
        $targetCount = self::TARGET_COUNTS[$scale] ?? throw new RuntimeException("Unsupported spare parts catalog scale: {$scale}");
        $additionalCount = $targetCount - 6;
        $variants = [];

        // Round-robin the families so even small mode represents multiple asset types.
        $maxVariants = max(array_map(static fn (array $family): int => count($family['variants']), self::FAMILIES));
        for ($variantIndex = 0; $variantIndex < $maxVariants; $variantIndex++) {
            foreach (self::FAMILIES as $family) {
                if (! isset($family['variants'][$variantIndex])) {
                    continue;
                }
                $variants[] = [$family, $variantIndex, $family['variants'][$variantIndex]];
                if (count($variants) >= $additionalCount) {
                    break 2;
                }
            }
        }

        if (count($variants) < $additionalCount) {
            throw new RuntimeException('Spare parts family catalog does not contain enough unique variants for this scale.');
        }

        $definitions = [];
        foreach ($variants as $index => [$family, $variantIndex, $variant]) {
            $codeNumber = $index + 7;
            $cost = bcadd(
                $family['base_cost'],
                bcmul((string) ($variantIndex % 5), $family['cost_step'], 4),
                4,
            );
            $name = sprintf($family['name'], $variant);
            $definitions[] = [
                'code' => sprintf('SP-%03d', $codeNumber),
                'name' => $name,
                'description' => self::DESCRIPTION_PREFIX.' Illustrative catalog values only; verify installed make/model and approved supplier data before purchase. Intended equipment class: '.$family['equipment_class'].'. Variant: '.$variant.'.',
                'category_id' => $categoryId,
                'unit_of_measure' => $family['unit'],
                'item_type' => ItemType::SparePart->value,
                'standard_cost' => $cost,
                'reorder_method' => 'fixed_quantity',
                'reorder_point' => $family['reorder'],
                'safety_stock' => $family['safety'],
                'minimum_order_quantity' => $family['moq'],
                'lead_time_days' => $family['lead_days'],
                'is_critical' => $family['critical'],
                'is_active' => true,
            ];
        }

        return $definitions;
    }

    protected function seedDemoData(DemoSeedContext $context): void
    {
        $context->at($context->options->asOf, function () use ($context): void {
            $category = ItemCategory::query()
                ->where('name', 'Spare Parts')
                ->whereNull('parent_id')
                ->first();
            if ($category === null || $category->trashed() || $category->item_type !== ItemType::SparePart->value) {
                throw new RuntimeException('Maintenance spare parts require the active, typed Spare Parts category from inventory-catalog.');
            }

            $definitions = $this->plannedDefinitions($context->options->scale, (int) $category->id);
            $actor = $this->activeUserForRole('warehouse_staff');
            $context->asUser($actor, function () use ($context, $definitions): void {
                foreach ($definitions as $definition) {
                    $this->ensurePart($context, $definition);
                }
            });
        });
    }

    /** @param array{code: string, name: string, description: string, category_id: int, unit_of_measure: string, item_type: string, standard_cost: string, reorder_method: string, reorder_point: string, safety_stock: string, minimum_order_quantity: string, lead_time_days: int, is_critical: bool, is_active: bool} $definition */
    private function ensurePart(DemoSeedContext $context, array $definition): void
    {
        $item = Item::withTrashed()->where('code', $definition['code'])->first();
        if ($item === null) {
            $item = $this->items->create($definition);
        } elseif ($this->matchesRecognizedLegacyDefinition($item, $definition)) {
            // Earlier demo runs used this explicit synthetic description. Migrate
            // only rows whose full catalog contract still matches, through the
            // owning item service; any other drift remains a hard refusal.
            $item = $this->items->update($item, ['description' => $definition['description']]);
        } elseif (! $this->matchesDefinition($item, $definition)) {
            throw new BusinessRuleException("Spare part code {$definition['code']} exists with non-demo or changed catalog data; refusing to overwrite it.");
        } elseif ($item->trashed()) {
            $this->items->restore($item);
            $item->refresh();
        }

        $context->records()->remember('maintenance.spare-part.'.$definition['code'], $item);
    }

    /** @param array{code: string, name: string, description: string, category_id: int, unit_of_measure: string, item_type: string, standard_cost: string, reorder_method: string, reorder_point: string, safety_stock: string, minimum_order_quantity: string, lead_time_days: int, is_critical: bool, is_active: bool} $definition */
    private function matchesDefinition(Item $item, array $definition): bool
    {
        return $item->description === $definition['description']
            && $this->matchesDefinitionExceptDescription($item, $definition);
    }

    /** @param array{code: string, name: string, description: string, category_id: int, unit_of_measure: string, item_type: string, standard_cost: string, reorder_method: string, reorder_point: string, safety_stock: string, minimum_order_quantity: string, lead_time_days: int, is_critical: bool, is_active: bool} $definition */
    private function matchesRecognizedLegacyDefinition(Item $item, array $definition): bool
    {
        return str_starts_with((string) $item->description, 'Synthetic maintenance spare part.')
            && $this->matchesDefinitionExceptDescription($item, $definition);
    }

    /** @param array{code: string, name: string, description: string, category_id: int, unit_of_measure: string, item_type: string, standard_cost: string, reorder_method: string, reorder_point: string, safety_stock: string, minimum_order_quantity: string, lead_time_days: int, is_critical: bool, is_active: bool} $definition */
    private function matchesDefinitionExceptDescription(Item $item, array $definition): bool
    {
        return $item->name === $definition['name']
            && (int) $item->category_id === $definition['category_id']
            && $item->item_type->value === $definition['item_type']
            && $item->unit_of_measure === $definition['unit_of_measure']
            && bccomp((string) $item->standard_cost, $definition['standard_cost'], 4) === 0
            && $item->reorder_method->value === $definition['reorder_method']
            && bccomp((string) $item->reorder_point, $definition['reorder_point'], 3) === 0
            && bccomp((string) $item->safety_stock, $definition['safety_stock'], 3) === 0
            && bccomp((string) $item->minimum_order_quantity, $definition['minimum_order_quantity'], 3) === 0
            && $item->lead_time_days === $definition['lead_time_days']
            && $item->is_critical === $definition['is_critical']
            && $item->is_active === $definition['is_active'];
    }

    private function activeUserForRole(string $roleSlug): User
    {
        return User::query()
            ->where('is_active', true)
            ->whereHas('role', fn ($query) => $query->where('slug', $roleSlug))
            ->orderBy('id')
            ->first()
            ?? throw new RuntimeException("Demo spare parts catalog requires an active {$roleSlug} actor.");
    }
}
