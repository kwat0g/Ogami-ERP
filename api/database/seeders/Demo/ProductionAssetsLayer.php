<?php

declare(strict_types=1);

namespace Database\Seeders\Demo;

use App\Common\DemoData\DemoSeedContext;
use App\Common\DemoData\DemoSeeder;
use App\Modules\Assets\Enums\AssetCategory;
use App\Modules\Assets\Enums\DepreciationMethod;
use App\Modules\Assets\Models\Asset;
use App\Modules\Assets\Services\AssetService;
use App\Modules\CRM\Models\Product;
use App\Modules\HR\Models\Department;
use App\Modules\MRP\Models\Machine;
use App\Modules\MRP\Models\Mold;
use App\Modules\MRP\Services\MachineService;
use App\Modules\MRP\Services\MoldService;
use Carbon\CarbonImmutable;
use RuntimeException;

final class ProductionAssetsLayer extends DemoSeeder
{
    /** @var list<array{name: string, tonnage: int}> */
    private const MACHINE_MODELS = [
        ['name' => 'Nissei FNX80', 'tonnage' => 80],
        ['name' => 'Toshiba EC100SX', 'tonnage' => 100],
        ['name' => 'Sumitomo SE130', 'tonnage' => 130],
        ['name' => 'Nissei NEX160', 'tonnage' => 160],
        ['name' => 'Nissei NEX180', 'tonnage' => 180],
        ['name' => 'Fanuc ROBOSHOT α-S200iA', 'tonnage' => 220],
        ['name' => 'JSW J280AD', 'tonnage' => 280],
        ['name' => 'Toshiba EC350SX', 'tonnage' => 350],
        ['name' => 'Toshiba EC450SX', 'tonnage' => 450],
        ['name' => 'Toshiba EC650SX', 'tonnage' => 650],
    ];

    /** @var list<string> */
    private const PRODUCT_PRIORITY = [
        'WB-001', 'PC-001', 'RC-001', 'BB-001', 'BU-001', 'CH-001', 'FC-001', 'MH-001',
        'HS-001', 'AD-001', 'TC-001', 'HR-001', 'WB-002', 'PC-002', 'RC-002', 'BC-001',
    ];

    /** @var list<array{code: string, name: string, cost: int, useful_life: int, location: string}> */
    private const AUXILIARY_EQUIPMENT = [
        ['code' => 'CH-01', 'name' => 'Central process water chiller, 80 RT', 'cost' => 4200000, 'useful_life' => 10, 'location' => 'Utility Room 1'],
        ['code' => 'AC-01', 'name' => 'Plant air compressor, 75 kW', 'cost' => 1850000, 'useful_life' => 10, 'location' => 'Compressor Room'],
        ['code' => 'AC-02', 'name' => 'Standby air compressor, 55 kW', 'cost' => 1450000, 'useful_life' => 10, 'location' => 'Compressor Room'],
        ['code' => 'DR-01', 'name' => 'Resin hopper dryer, 100 kg', 'cost' => 285000, 'useful_life' => 7, 'location' => 'Injection Hall / Bay 1'],
        ['code' => 'DR-02', 'name' => 'Resin hopper dryer, 150 kg', 'cost' => 340000, 'useful_life' => 7, 'location' => 'Injection Hall / Bay 4'],
        ['code' => 'LD-01', 'name' => 'Vacuum resin loader, central line A', 'cost' => 195000, 'useful_life' => 7, 'location' => 'Injection Hall / Bay 1'],
        ['code' => 'GR-01', 'name' => 'Sprue and runner granulator, 15 kW', 'cost' => 465000, 'useful_life' => 8, 'location' => 'Regrind Room'],
        ['code' => 'MTC-01', 'name' => 'Mold temperature controller, 2-zone', 'cost' => 185000, 'useful_life' => 7, 'location' => 'Injection Hall / Bay 3'],
        ['code' => 'ROB-01', 'name' => 'Three-axis take-out robot, 5 kg payload', 'cost' => 1250000, 'useful_life' => 8, 'location' => 'Injection Hall / Bay 7'],
        ['code' => 'CON-01', 'name' => 'Finished-part conveyor, line A', 'cost' => 275000, 'useful_life' => 7, 'location' => 'Injection Hall / Bay 7'],
        ['code' => 'CR-01', 'name' => 'Overhead mold-change crane, 5 tonne', 'cost' => 2100000, 'useful_life' => 12, 'location' => 'Injection Hall / Mold Change Bay'],
        ['code' => 'FL-01', 'name' => 'Electric forklift, 2.5 tonne', 'cost' => 1650000, 'useful_life' => 8, 'location' => 'Warehouse / Charging Bay'],
    ];

    public function __construct(
        private readonly MachineService $machines,
        private readonly MoldService $molds,
        private readonly AssetService $assets,
    ) {}

    public function key(): string
    {
        return 'production-assets';
    }

    /** @return list<string> */
    public function modules(): array
    {
        return ['MRP', 'Assets', 'Maintenance'];
    }

    /** @return list<string> */
    public function dependencies(): array
    {
        return ['organization', 'finished-product-catalog'];
    }

    protected function seedDemoData(DemoSeedContext $context): void
    {
        $context->at($context->options->asOf, function () use ($context): void {
            $productionDepartmentId = $this->departmentId('PROD');
            $maintenanceDepartmentId = $this->departmentId('MAINT');
            $machinesByCode = $this->seedMachines($context, $productionDepartmentId);
            $productsByNumber = Product::query()
                ->whereIn('part_number', self::PRODUCT_PRIORITY)
                ->get(['id', 'part_number', 'name'])
                ->keyBy('part_number');
            $molds = $this->seedMolds($context, $productsByNumber, $machinesByCode, $maintenanceDepartmentId);
            $this->seedAuxiliaryEquipment($context, $maintenanceDepartmentId);

            foreach ($machinesByCode as $code => $machine) {
                $context->records()->remember('production.machine.'.$code, $machine);
            }
            foreach ($molds as $code => $mold) {
                $context->records()->remember('production.mold.'.$code, $mold);
            }
        });
    }

    private function departmentId(string $code): int
    {
        $department = Department::query()->where('code', $code)->first();
        if ($department === null) {
            throw new RuntimeException("Production asset seeding requires department {$code}; run the organization layer first.");
        }

        return (int) $department->getKey();
    }

    /** @return array<string, Machine> */
    private function seedMachines(DemoSeedContext $context, int $departmentId): array
    {
        $definitions = $this->machineDefinitions($context->options->scale);
        $machinesByCode = [];

        foreach (array_values($definitions) as $index => $definition) {
            $machine = Machine::withTrashed()->where('machine_code', $definition['machine_code'])->first();
            if ($machine === null) {
                $machine = $this->machines->create($definition);
            } elseif ($machine->trashed()) {
                $machine = $this->machines->restore($machine);
            }

            $asset = $this->assetForOperationalRecord(
                $machine,
                AssetCategory::Machine,
                'machine',
                $departmentId,
                $this->machineAssetData($context, $definition, $index, $departmentId),
            );
            $context->records()->remember('production.asset.machine.'.$definition['machine_code'], $asset);
            $machinesByCode[$definition['machine_code']] = $machine->fresh();
        }

        return $machinesByCode;
    }

    /** @param array<string, mixed> $definition
     * @return array<string, mixed>
     */
    private function machineAssetData(DemoSeedContext $context, array $definition, int $index, int $departmentId): array
    {
        $machineCode = $definition['machine_code'];
        $cost = 3_500_000 + ($definition['tonnage'] * 18_000);
        $location = 'Injection Hall / Bay '.(1 + ($index % 10));

        return [
            'name' => $machineCode.' — '.$definition['name'],
            'description' => "Operational asset for machine {$machineCode}. Acquisition value is a planning estimate.",
            'category' => AssetCategory::Machine->value,
            'department_id' => $departmentId,
            'acquisition_date' => $this->acquisitionDate($context, $index)->toDateString(),
            'acquisition_cost' => (string) $cost,
            'useful_life_years' => 10,
            'salvage_value' => (string) intdiv($cost, 10),
            'depreciation_method' => DepreciationMethod::StraightLine->value,
            'location' => $location,
        ];
    }

    /** @param array<string, Machine> $machinesByCode */
    private function seedMolds(
        DemoSeedContext $context,
        $productsByNumber,
        array $machinesByCode,
        int $departmentId,
    ): array {
        $targetCount = match ($context->options->scale) {
            // Small keeps one mold per finished good so BOM/routing E2E
            // baselines can exercise every cataloged product.
            'small' => 16,
            'demo' => 60,
            'full' => 96,
        };
        $definitions = $this->moldDefinitions($targetCount);
        $moldsByCode = [];

        foreach ($definitions as $index => $definition) {
            $product = $productsByNumber->get($definition['part_number']);
            if ($product === null) {
                throw new RuntimeException("Finished product {$definition['part_number']} is required for mold {$definition['mold_code']}.");
            }

            $mold = Mold::withTrashed()->where('mold_code', $definition['mold_code'])->first();
            if ($mold === null) {
                $mold = $this->molds->create([
                    'mold_code' => $definition['mold_code'],
                    'name' => $definition['name'],
                    'product_id' => $product->id,
                    'cavity_count' => $definition['cavity_count'],
                    'cycle_time_seconds' => $definition['cycle_time_seconds'],
                    'output_rate_per_hour' => $definition['output_rate_per_hour'],
                    'setup_time_minutes' => $definition['setup_time_minutes'],
                    'current_shot_count' => $definition['current_shot_count'],
                    'max_shots_before_maintenance' => $definition['max_shots_before_maintenance'],
                    'lifetime_total_shots' => $definition['lifetime_total_shots'],
                    'lifetime_max_shots' => $definition['lifetime_max_shots'],
                    'maintenance_frequency_shots' => $definition['maintenance_frequency_shots'],
                    'status' => $definition['status'],
                    'location' => $definition['location'],
                    'acquisition_cost' => (string) $definition['asset_cost'],
                    'estimated_replacement_cost' => (string) round($definition['asset_cost'] * 1.4),
                    'commissioned_at' => $this->acquisitionDate($context, $index + 2)->toDateString(),
                    'drawing_number' => 'OGI-DRW-'.$definition['mold_code'].'-R0',
                    'storage_location' => 'TOOL-CRIB-A-'.str_pad((string) (($index % 24) + 1), 2, '0', STR_PAD_LEFT),
                ]);
            } elseif ($mold->trashed()) {
                $mold = $this->molds->restore($mold);
            }

            $asset = $this->assetForOperationalRecord(
                $mold,
                AssetCategory::Mold,
                'mold',
                $departmentId,
                [
                    'name' => $definition['mold_code'].' — '.$definition['name'],
                    'description' => "Operational asset for mold {$definition['mold_code']}. Acquisition value is a planning estimate.",
                    'category' => AssetCategory::Mold->value,
                    'department_id' => $departmentId,
                    'acquisition_date' => $this->acquisitionDate($context, $index + 2)->toDateString(),
                    'acquisition_cost' => (string) $definition['asset_cost'],
                    'useful_life_years' => 7,
                    'salvage_value' => (string) intdiv($definition['asset_cost'], 10),
                    'depreciation_method' => DepreciationMethod::StraightLine->value,
                    'location' => $definition['storage_location'],
                ],
            );
            $context->records()->remember('production.asset.mold.'.$definition['mold_code'], $asset);

            $compatibleIds = $this->compatibleMachineIds($definition, $machinesByCode);
            $currentIds = $mold->compatibleMachines()->orderBy('machines.id')->pluck('machines.id')->map(static fn ($id): int => (int) $id)->all();
            sort($compatibleIds);
            if ($currentIds !== $compatibleIds) {
                $mold = $this->molds->syncCompatibility($mold, $compatibleIds);
            }

            $moldsByCode[$definition['mold_code']] = $mold->fresh();
        }

        return $moldsByCode;
    }

    /** @param array<string, mixed> $definition
     * @return list<int>
     */
    private function compatibleMachineIds(array $definition, array $machinesByCode): array
    {
        [$minimum, $maximum] = $definition['tonnage_band'];
        $ids = [];
        foreach ($machinesByCode as $machine) {
            if ($machine->tonnage !== null && $machine->tonnage >= $minimum && $machine->tonnage <= $maximum) {
                $ids[] = (int) $machine->getKey();
            }
        }
        if ($ids === []) {
            throw new RuntimeException("No compatible machine is seeded for mold {$definition['mold_code']} ({$minimum}-{$maximum} tonne band).");
        }

        sort($ids);

        return $ids;
    }

    /** @return array<string, Machine> */
    private function machineDefinitions(string $scale): array
    {
        $count = 36;
        $lines = [];
        for ($index = 0; $index < $count; $index++) {
            $model = self::MACHINE_MODELS[$index % count(self::MACHINE_MODELS)];
            $lines[] = [
                'machine_code' => sprintf('IMM-%02d', $index + 1),
                'name' => $model['name'],
                'tonnage' => $model['tonnage'],
                'machine_type' => 'injection_molder',
                'operators_required' => $model['tonnage'] >= 280 ? 2.0 : 1.0,
                'available_hours_per_day' => 16.0,
                'status' => 'idle',
                'running_hours_total' => number_format(1800 * (1 + ($index % 8)), 2, '.', ''),
                'running_hours_updated_at' => now(),
            ];
        }

        if ($scale === 'small') {
            $lines = array_map(static function (int $sourceIndex, int $targetIndex) use ($lines): array {
                $definition = $lines[$sourceIndex];
                $definition['machine_code'] = sprintf('IMM-%02d', $targetIndex + 1);

                return $definition;
            }, [1, 6, 8, 9], [0, 1, 2, 3]);
        } elseif ($scale === 'demo') {
            $lines = array_slice($lines, 0, 30);
        }

        return array_column($lines, null, 'machine_code');
    }

    /** @return list<array<string, mixed>> */
    private function moldDefinitions(int $count): array
    {
        $definitions = [];
        $familySequences = [];
        $productIndex = 0;
        $nextShotCount = 0;

        for ($index = 0; $index < $count; $index++) {
            $partNumber = self::PRODUCT_PRIORITY[$productIndex % count(self::PRODUCT_PRIORITY)];
            $productIndex++;
            $family = substr($partNumber, 0, 2);
            $familySequences[$family] = ($familySequences[$family] ?? 0) + 1;
            $familySequence = $familySequences[$family];
            $moldCode = 'M-'.$family.'-'.str_pad((string) $familySequence, 3, '0', STR_PAD_LEFT);
            $partClass = $this->partClass($partNumber);
            [$cavities, $cycleSeconds, $band, $classCost] = match ($partClass) {
                'small' => [4 + ($familySequence % 2), 20 + ($familySequence % 5), [80, 180], 0],
                'medium' => [2 + ($familySequence % 3), 30 + ($familySequence % 7), [130, 350], 180_000],
                'large' => [1 + ($familySequence % 2), 38 + ($familySequence % 8), [220, 650], 360_000],
            };
            $maxShots = $cycleSeconds <= 25 ? 100_000 : ($cycleSeconds <= 36 ? 80_000 : 60_000);
            $isNearingLimitScenario = $moldCode === 'M-WB-001';
            $currentShots = $isNearingLimitScenario
                ? (int) ($maxShots * 0.85)
                : 1000 + (($nextShotCount * 7919) % (int) ($maxShots * 0.45));
            $nextShotCount++;
            $lifetimeShots = $currentShots + (int) ($maxShots * (1 + ($familySequence % 3)));
            $assetCost = 600_000 + ($cavities * 150_000) + $classCost;
            $name = "{$partNumber} {$cavities}-cavity precision steel mold {$familySequence}";

            $definitions[] = [
                'part_number' => $partNumber,
                'mold_code' => $moldCode,
                'name' => $name,
                'cavity_count' => $cavities,
                'cycle_time_seconds' => $cycleSeconds,
                'output_rate_per_hour' => (int) floor(3600 / $cycleSeconds * $cavities),
                'setup_time_minutes' => $partClass === 'large' ? 120 : 90,
                'current_shot_count' => $currentShots,
                'max_shots_before_maintenance' => $maxShots,
                'lifetime_total_shots' => $lifetimeShots,
                'lifetime_max_shots' => $maxShots * 5,
                'maintenance_frequency_shots' => $maxShots,
                'status' => 'available',
                'location' => 'Tooling Crib · Bay '.chr(65 + ($index % 4)),
                'asset_cost' => $assetCost,
                'storage_location' => 'TOOL-CRIB-A-'.str_pad((string) (($index % 24) + 1), 2, '0', STR_PAD_LEFT),
                'tonnage_band' => $band,
            ];
        }

        return $definitions;
    }

    private function partClass(string $partNumber): string
    {
        return match (substr($partNumber, 0, 2)) {
            'FC', 'MH', 'AD', 'BC' => 'large',
            'PC', 'RC', 'CH', 'HS' => 'medium',
            default => 'small',
        };
    }

    private function acquisitionDate(DemoSeedContext $context, int $index): CarbonImmutable
    {
        return $context->options->asOf
            ->subYears(1 + ($index % 8))
            ->subMonths(($index * 5) % 12)
            ->startOfDay();
    }

    /** @param array<string, mixed> $data */
    private function assetForOperationalRecord(
        Machine|Mold $record,
        AssetCategory $category,
        string $targetType,
        int $departmentId,
        array $data,
    ): Asset {
        $asset = $record->asset_id === null
            ? null
            : Asset::withTrashed()->find($record->asset_id);

        if ($asset === null) {
            $asset = Asset::withTrashed()
                ->where('description', $data['description'])
                ->first();
        }

        if ($asset === null) {
            $asset = $this->assets->create([
                ...$data,
                'department_id' => $departmentId,
            ]);
        } elseif ($asset->trashed()) {
            $this->assets->restore($asset);
            $asset->refresh();
        }

        if ($asset->category !== $category) {
            throw new RuntimeException("Operational asset {$asset->asset_code} has the wrong category for {$targetType}.");
        }

        if ($record->asset_id === null) {
            $this->assets->associate($asset, [
                'target_type' => $targetType,
                'target_id' => (int) $record->getKey(),
            ]);
        } elseif ((int) $record->asset_id !== (int) $asset->getKey()) {
            throw new RuntimeException("Operational asset association changed while seeding {$targetType}.");
        }

        return $asset->fresh();
    }

    private function seedAuxiliaryEquipment(DemoSeedContext $context, int $departmentId): void
    {
        $count = match ($context->options->scale) {
            'small' => 3,
            'demo' => count(self::AUXILIARY_EQUIPMENT),
            'full' => count(self::AUXILIARY_EQUIPMENT),
        };

        foreach (array_slice(self::AUXILIARY_EQUIPMENT, 0, $count) as $index => $equipment) {
            $description = "Auxiliary equipment asset {$equipment['code']}. Acquisition value is a planning estimate.";
            $asset = Asset::withTrashed()->where('description', $description)->first();

            if ($asset === null) {
                $cost = $equipment['cost'];
                $asset = $this->assets->create([
                    'name' => $equipment['code'].' — '.$equipment['name'],
                    'description' => $description,
                    'category' => AssetCategory::Equipment->value,
                    'department_id' => $departmentId,
                    'acquisition_date' => $this->acquisitionDate($context, $index + 11)->toDateString(),
                    'acquisition_cost' => (string) $cost,
                    'useful_life_years' => $equipment['useful_life'],
                    'salvage_value' => (string) intdiv($cost, 10),
                    'depreciation_method' => DepreciationMethod::StraightLine->value,
                    'location' => $equipment['location'],
                ]);
            } elseif ($asset->trashed()) {
                $this->assets->restore($asset);
                $asset->refresh();
            }

            $context->records()->remember('production.asset.auxiliary.'.$equipment['code'], $asset);
        }
    }
}
