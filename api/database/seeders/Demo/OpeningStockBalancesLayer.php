<?php

declare(strict_types=1);

namespace Database\Seeders\Demo;

use App\Common\DemoData\DemoSeedContext;
use App\Common\DemoData\DemoSeeder;
use App\Common\Exceptions\BusinessRuleException;
use App\Modules\Auth\Models\User;
use App\Modules\Inventory\Enums\ItemType;
use App\Modules\Inventory\Enums\StockMovementType;
use App\Modules\Inventory\Models\Item;
use App\Modules\Inventory\Models\StockLevel;
use App\Modules\Inventory\Models\StockMovement;
use App\Modules\Inventory\Models\WarehouseLocation;
use App\Modules\Inventory\Services\StockMovementService;
use App\Modules\Inventory\Support\StockMovementInput;
use Carbon\CarbonImmutable;
use RuntimeException;

/** Loads the opening inventory state through the canonical stock movement service. */
final class OpeningStockBalancesLayer extends DemoSeeder
{
    /** @var array<string, int> */
    private const SPARE_PART_COUNTS = ['small' => 20, 'demo' => 100, 'full' => 140];

    /** @var list<string> */
    private const NON_SPARE_CODES = [
        'RM-001', 'RM-002', 'RM-003', 'RM-004', 'RM-005', 'RM-006', 'RM-007', 'RM-008',
        'RM-009', 'RM-010', 'RM-011', 'RM-012', 'RM-050', 'RM-051', 'RM-052',
        'PKG-001', 'PKG-002', 'PKG-003', 'PKG-004',
        'CONS-001', 'CONS-002', 'CONS-003',
        'WB-001', 'PC-001', 'RC-001', 'BB-001', 'BU-001', 'CH-001', 'FC-001', 'MH-001',
        'HS-001', 'AD-001', 'TC-001', 'HR-001', 'WB-002', 'PC-002', 'RC-002', 'BC-001',
    ];

    private const LEGACY_OPENING_REMARKS_PREFIX = 'DEMO opening inventory';

    /** Mirrors the current base-unit discreteness rules in UomConversionService. */
    private const WHOLE_UNIT_CODES = ['PC', 'PCS', 'PIECE', 'PIECES', 'EA', 'EACH', 'UNIT', 'UNITS', 'SET', 'SETS'];

    public function __construct(private readonly StockMovementService $movements) {}

    public function key(): string
    {
        return 'opening-stock-balances';
    }

    /** @return list<string> */
    public function modules(): array
    {
        return ['Inventory', 'Purchasing'];
    }

    /** @return list<string> */
    public function dependencies(): array
    {
        return ['bom-routing-catalog', 'maintenance-spare-parts', 'workforce-identities'];
    }

    protected function seedDemoData(DemoSeedContext $context): void
    {
        $spareCount = self::SPARE_PART_COUNTS[$context->options->scale]
            ?? throw new RuntimeException('Unsupported scale for opening stock balances.');
        $codes = self::NON_SPARE_CODES;
        for ($number = 1; $number <= $spareCount; $number++) {
            $codes[] = sprintf('SP-%03d', $number);
        }
        sort($codes);

        $foundCodes = Item::query()->whereIn('code', $codes)->orderBy('code')->pluck('code')->all();
        $missingCodes = array_values(array_diff($codes, $foundCodes));
        if ($missingCodes !== []) {
            throw new RuntimeException('Opening stock requires the completed item catalogs; missing codes: '.implode(', ', $missingCodes));
        }

        $locations = [
            ItemType::RawMaterial->value => $this->activeLocation('A-01'),
            ItemType::Packaging->value => $this->activeLocation('A-01'),
            ItemType::FinishedGood->value => $this->activeLocation('C-01'),
            ItemType::SparePart->value => $this->activeLocation('D-01'),
        ];
        $bufferLocations = [
            ItemType::RawMaterial->value => $this->activeLocation('B-01'),
            ItemType::Packaging->value => $this->activeLocation('B-01'),
            ItemType::FinishedGood->value => $this->activeLocation('C-01'),
        ];
        $actor = $this->activeUserForRole('warehouse_staff');
        $openingDate = $context->options->asOf->subMonthsNoOverflow(18)->startOfDay();
        $asOfDate = $context->options->asOf->toDateString();

        $context->at($openingDate, function () use ($context, $codes, $locations, $bufferLocations, $actor, $openingDate, $asOfDate): void {
            $index = 0;
            Item::query()->whereIn('code', $codes)->orderBy('code')->chunk(50, function ($items) use ($context, $locations, $actor, $openingDate, $asOfDate, &$index): void {
                foreach ($items as $item) {
                    $location = $locations[$item->item_type->value] ?? throw new RuntimeException("No opening-stock location is defined for item type {$item->item_type->value}.");
                    $quantity = $this->plannedQuantity($item, $index++);
                    if (bccomp($quantity, '0', 3) === 0) {
                        $this->assertNoPriorOpening($item, plannedZero: true);

                        continue;
                    }

                    if (! $this->hasDemoOpening($item) && $this->hasExistingInventoryState($item)) {
                        echo "Preserving existing inventory for {$item->code}; no demo opening movement added.\n";

                        continue;
                    }

                    $unitCost = (string) $item->standard_cost;
                    if (bccomp($unitCost, '0', 4) <= 0) {
                        throw new BusinessRuleException("Opening stock item {$item->code} needs a positive standard cost from the current item catalog.");
                    }

                    $lots = $this->plannedLots($item, $quantity, $asOfDate);
                    $context->withoutNotifications(function () use ($context, $actor, $item, $location, $unitCost, $lots, $openingDate, $asOfDate): void {
                        $context->asUser($actor, function () use ($context, $item, $location, $unitCost, $lots, $openingDate, $asOfDate, $actor): void {
                            foreach ($lots as $lot) {
                                $this->ensureOpeningMovement(
                                    $context,
                                    $item,
                                    $location,
                                    $lot['quantity'],
                                    $unitCost,
                                    $lot['lot_number'],
                                    $lot['expiry_date'],
                                    $openingDate,
                                    $asOfDate,
                                    $actor,
                                );
                            }
                        });
                    });
                }
            });

            $context->withoutNotifications(fn () => $context->asUser($actor, fn () => $this->ensureProductionBuffers(
                $context,
                $codes,
                $bufferLocations,
                $actor,
                $openingDate,
                $asOfDate,
            )));
        });
    }

    /** Add demo opening stock in the production/finished-goods bins through the stock ledger service. */
    private function ensureProductionBuffers(
        DemoSeedContext $context,
        array $codes,
        array $locations,
        User $actor,
        CarbonImmutable $openingDate,
        string $asOfDate,
    ): void {
        Item::query()
            ->whereIn('code', $codes)
            ->whereIn('item_type', [ItemType::RawMaterial->value, ItemType::Packaging->value, ItemType::FinishedGood->value])
            ->orderBy('code')
            ->chunkById(50, function ($items) use ($context, $locations, $actor, $openingDate, $asOfDate): void {
                foreach ($items as $item) {
                    $location = $locations[$item->item_type->value]
                        ?? throw new RuntimeException("No production-stock location is defined for item type {$item->item_type->value}.");
                    $unitCost = (string) $item->standard_cost;
                    if (bccomp($unitCost, '0', 4) <= 0) {
                        throw new BusinessRuleException("Production buffer item {$item->code} needs a positive standard cost.");
                    }

                    $this->ensureProductionBufferMovement(
                        $context,
                        $item,
                        $location,
                        $this->productionTarget($item),
                        $unitCost,
                        'OGI-PROD-BUFFER-'.$item->code,
                        $openingDate,
                        $asOfDate,
                        $actor,
                    );
                }
            });
    }

    private function productionTarget(Item $item): string
    {
        $reorderPoint = (string) ($item->reorder_point ?? '0.000');
        $safetyStock = (string) ($item->safety_stock ?? '0.000');
        $target = $item->item_type === ItemType::FinishedGood
            ? $this->greaterOf('500.000', bcmul($reorderPoint, '1.25', 3))
            : $this->greaterOf(bcmul($reorderPoint, '1.50', 3), bcmul($safetyStock, '2.00', 3));

        if (bccomp($target, '100.000', 3) < 0) {
            $target = '100.000';
        }

        return $this->wholeUnitQuantity($item, $target);
    }

    private function ensureProductionBufferMovement(
        DemoSeedContext $context,
        Item $item,
        WarehouseLocation $location,
        string $quantity,
        string $unitCost,
        string $lotNumber,
        CarbonImmutable $openingDate,
        string $asOfDate,
        User $actor,
    ): void {
        $key = 'demo-production-buffer:'.$item->code;
        $remarks = 'Production buffer at the 18-month history start; planned opening stock for production and fulfillment.';
        $legacyLotNumber = str_replace('OGI-PROD-BUFFER-', 'DEMO-PROD-BUFFER-', $lotNumber);
        $legacyRemarks = 'DEMO production buffer at the 18-month history start; synthetic opening stock for manual production and fulfillment demonstrations.';
        $olderLegacyRemarks = 'production buffer at the 18-month history start; synthetic opening stock for manual production and fulfillment demonstrations.';
        $fingerprint = hash('sha256', json_encode([
            $item->id,
            $location->id,
            $quantity,
            bcadd($unitCost, '0', 4),
            $lotNumber,
            null,
            $openingDate->toIso8601String(),
            $asOfDate,
            $remarks,
        ], JSON_THROW_ON_ERROR));
        $legacyFingerprint = hash('sha256', json_encode([
            $item->id,
            $location->id,
            $quantity,
            bcadd($unitCost, '0', 4),
            $legacyLotNumber,
            null,
            $openingDate->toIso8601String(),
            $asOfDate,
            $legacyRemarks,
        ], JSON_THROW_ON_ERROR));
        $existing = StockMovement::query()->where('idempotency_key', $key)->first();
        if ($existing !== null) {
            $currentDataMatches = $existing->idempotency_fingerprint === $fingerprint
                && $existing->lot_number === $lotNumber
                && $existing->remarks === $remarks;
            $legacyDataMatches = $existing->idempotency_fingerprint === $legacyFingerprint
                && in_array($existing->lot_number, [$legacyLotNumber, $lotNumber], true)
                && in_array($existing->remarks, [$legacyRemarks, $olderLegacyRemarks, $remarks], true);
            if (! ($currentDataMatches || $legacyDataMatches)
                || $existing->movement_type !== StockMovementType::Opening
                || (int) $existing->item_id !== (int) $item->id
                || (int) $existing->to_location_id !== (int) $location->id
                || bccomp((string) $existing->quantity, $quantity, 3) !== 0
                || bccomp((string) $existing->unit_cost, $unitCost, 4) !== 0
                || $existing->created_at?->toDateString() !== $openingDate->toDateString()) {
                throw new BusinessRuleException("Production buffer movement {$key} already exists with different data; refusing to alter its ledger history.");
            }

            $context->records()->remember('inventory.production-buffer.'.$item->code, $existing);

            return;
        }

        $movement = $this->movements->move(new StockMovementInput(
            type: StockMovementType::Opening,
            itemId: (int) $item->id,
            quantity: $quantity,
            toLocationId: (int) $location->id,
            unitCost: $unitCost,
            referenceType: 'opening',
            remarks: $remarks,
            createdBy: (int) $actor->id,
            lotNumber: $lotNumber,
            expiryDate: null,
            idempotencyKey: $key,
            idempotencyFingerprint: $fingerprint,
        ));
        $context->records()->remember('inventory.production-buffer.'.$item->code, $movement);
    }

    private function plannedQuantity(Item $item, int $index): string
    {
        if (in_array($item->code, ['RM-003', 'PKG-004', 'SP-020', 'BC-001'], true)) {
            return '0.000';
        }

        if ($item->code === 'RM-005') {
            return bcmul((string) $item->reorder_point, '1.25', 3);
        }

        if ($item->item_type === ItemType::FinishedGood) {
            $units = ['120.000', '240.000', '450.000', '700.000', '980.000', '1600.000'];

            return $units[$index % count($units)];
        }

        $reorderPoint = (string) $item->reorder_point;
        $safetyStock = (string) $item->safety_stock;
        if (bccomp($reorderPoint, '0', 3) <= 0) {
            throw new BusinessRuleException("Opening stock item {$item->code} has no positive reorder point in the current item catalog.");
        }

        $quantity = match ($index % 12) {
            0 => '0.000',
            1 => bccomp($safetyStock, '0', 3) > 0 ? bcmul($safetyStock, '0.50', 3) : '0.000',
            2 => bccomp($safetyStock, '0', 3) > 0 ? $safetyStock : bcmul($reorderPoint, '0.40', 3),
            3 => $this->greaterOf(bcmul($safetyStock, '1.20', 3), bcmul($reorderPoint, '0.65', 3)),
            4 => bcmul($reorderPoint, '0.85', 3),
            5 => bcmul($reorderPoint, '1.10', 3),
            6 => bcmul($reorderPoint, '1.50', 3),
            7 => bcmul($reorderPoint, '2.00', 3),
            8 => bcmul($reorderPoint, '5.00', 3),
            9 => bcmul($reorderPoint, '1.80', 3),
            10 => bcmul($reorderPoint, '3.00', 3),
            default => bcmul($reorderPoint, '1.25', 3),
        };

        return $this->wholeUnitQuantity($item, $quantity);
    }

    /** @return list<array{quantity: string, lot_number: string, expiry_date: ?string}> */
    private function plannedLots(Item $item, string $quantity, string $asOfDate): array
    {
        if ($item->code === 'RM-005') {
            $nearExpiryQuantity = bcmul($quantity, '0.10', 3);

            return [
                [
                    'quantity' => $nearExpiryQuantity,
                    'lot_number' => 'OGI-LOT-RM-005-NEAR',
                    'expiry_date' => CarbonImmutable::parse($asOfDate, 'Asia/Manila')->addDays(14)->toDateString(),
                ],
                [
                    'quantity' => bcsub($quantity, $nearExpiryQuantity, 3),
                    'lot_number' => 'OGI-LOT-RM-005-REGULAR',
                    'expiry_date' => CarbonImmutable::parse($asOfDate, 'Asia/Manila')->addDays(180)->toDateString(),
                ],
            ];
        }

        return [[
            'quantity' => $quantity,
            'lot_number' => 'OGI-LOT-'.$item->code.'-OPEN',
            'expiry_date' => null,
        ]];
    }

    private function ensureOpeningMovement(
        DemoSeedContext $context,
        Item $item,
        WarehouseLocation $location,
        string $quantity,
        string $unitCost,
        string $lotNumber,
        ?string $expiryDate,
        CarbonImmutable $openingDate,
        string $asOfDate,
        User $actor,
    ): void {
        $legacyLotNumber = str_replace('OGI-LOT-', 'DEMO-LOT-', $lotNumber);
        $key = 'demo-opening:'.$item->code.':'.$legacyLotNumber;
        $remarks = 'Opening inventory at the 18-month history start; planned quantity and cost basis.';
        $legacyRemarks = self::LEGACY_OPENING_REMARKS_PREFIX.' at the 18-month history start; synthetic demo quantity and cost basis.';
        $olderLegacyRemarks = 'opening inventory at the 18-month history start; synthetic quantity and cost basis.';
        $fingerprint = hash('sha256', json_encode([
            $item->id,
            $location->id,
            $quantity,
            bcadd($unitCost, '0', 4),
            $lotNumber,
            $expiryDate,
            $openingDate->toIso8601String(),
            $asOfDate,
            $remarks,
        ], JSON_THROW_ON_ERROR));
        $legacyFingerprint = hash('sha256', json_encode([
            $item->id,
            $location->id,
            $quantity,
            bcadd($unitCost, '0', 4),
            $legacyLotNumber,
            $expiryDate,
            $openingDate->toIso8601String(),
            $asOfDate,
            $legacyRemarks,
        ], JSON_THROW_ON_ERROR));
        $existing = StockMovement::query()->where('idempotency_key', $key)->first();
        if ($existing !== null) {
            $currentDataMatches = $existing->idempotency_fingerprint === $fingerprint
                && $existing->lot_number === $lotNumber
                && $existing->remarks === $remarks;
            $legacyDataMatches = $existing->idempotency_fingerprint === $legacyFingerprint
                && in_array($existing->lot_number, [$legacyLotNumber, $lotNumber], true)
                && in_array($existing->remarks, [$legacyRemarks, $olderLegacyRemarks, $remarks], true);
            if (! ($currentDataMatches || $legacyDataMatches)
                || $existing->movement_type !== StockMovementType::Opening
                || (int) $existing->item_id !== (int) $item->id
                || (int) $existing->to_location_id !== (int) $location->id
                || bccomp((string) $existing->quantity, $quantity, 3) !== 0
                || bccomp((string) $existing->unit_cost, $unitCost, 4) !== 0
                || $existing->expiry_date?->toDateString() !== $expiryDate
                || $existing->created_at?->toDateString() !== $openingDate->toDateString()) {
                throw new BusinessRuleException("Opening movement {$key} already exists with different data; refusing to alter its ledger history.");
            }

            $context->records()->remember('inventory.opening.'.$key, $existing);

            return;
        }

        $this->assertNoPriorOpening($item);
        $movement = $this->movements->move(new StockMovementInput(
            type: StockMovementType::Opening,
            itemId: (int) $item->id,
            quantity: $quantity,
            toLocationId: (int) $location->id,
            unitCost: $unitCost,
            referenceType: 'opening',
            remarks: $remarks,
            createdBy: (int) $actor->id,
            lotNumber: $lotNumber,
            expiryDate: $expiryDate,
            idempotencyKey: $key,
            idempotencyFingerprint: $fingerprint,
        ));
        $context->records()->remember('inventory.opening.'.$key, $movement);
    }

    private function assertNoPriorOpening(Item $item, bool $plannedZero = false): void
    {
        if ($plannedZero) {
            if ($this->hasDemoOpening($item)) {
                throw new BusinessRuleException("Opening stock plan for {$item->code} changed after its demo opening movement was created.");
            }

            return;
        }

        if ($this->hasDemoOpening($item)) {
            return;
        }

        if ($this->hasExistingInventoryState($item)) {
            throw new BusinessRuleException("Item {$item->code} already has stock or movement history without this demo opening key; refusing to add opening quantity on top.");
        }
    }

    private function hasDemoOpening(Item $item): bool
    {
        return StockMovement::query()
            ->where('item_id', $item->id)
            ->where('idempotency_key', 'like', 'demo-opening:'.$item->code.':%')
            ->exists();
    }

    private function hasExistingInventoryState(Item $item): bool
    {
        $hasOnHand = StockLevel::query()
            ->where('item_id', $item->id)
            ->where(fn ($query) => $query->where('quantity', '<>', '0')->orWhere('reserved_quantity', '<>', '0'))
            ->exists();
        $hasHistory = StockMovement::query()->where('item_id', $item->id)->exists();

        return $hasOnHand || $hasHistory;
    }

    private function activeLocation(string $code): WarehouseLocation
    {
        return WarehouseLocation::query()
            ->where('code', $code)
            ->where('is_active', true)
            ->first()
            ?? throw new RuntimeException("Opening stock requires active warehouse location {$code}.");
    }

    private function activeUserForRole(string $roleSlug): User
    {
        return User::query()
            ->where('is_active', true)
            ->whereHas('role', fn ($query) => $query->where('slug', $roleSlug))
            ->orderBy('id')
            ->first()
            ?? throw new RuntimeException("Opening stock requires an active {$roleSlug} actor.");
    }

    private function greaterOf(string $first, string $second): string
    {
        return bccomp($first, $second, 3) >= 0 ? $first : $second;
    }

    private function wholeUnitQuantity(Item $item, string $quantity): string
    {
        if (! in_array(strtoupper(trim($item->unit_of_measure)), self::WHOLE_UNIT_CODES, true)
            || bccomp($quantity, '0', 3) === 0) {
            return $quantity;
        }

        return bcadd($quantity, '0.5', 0).'.000';
    }
}
