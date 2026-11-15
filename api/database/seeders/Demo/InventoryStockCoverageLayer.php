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
use App\Modules\Inventory\Models\StockMovement;
use App\Modules\Inventory\Models\WarehouseLocation;
use App\Modules\Inventory\Services\StockMovementService;
use App\Modules\Inventory\Support\StockMovementInput;
use Carbon\CarbonImmutable;
use RuntimeException;

/** Adds a controlled, lot-tracked stock buffer for every active inventory item. */
final class InventoryStockCoverageLayer extends DemoSeeder
{
    /** @var array<string, string> */
    private const LOCATION_CODES = [
        'raw_material' => 'B-01',
        'packaging' => 'B-01',
        'finished_good' => 'C-01',
        'spare_part' => 'D-01',
    ];

    /** @var array<string, string> */
    private const MINIMUM_BUFFER = [
        'raw_material' => '100.000',
        'packaging' => '500.000',
        'finished_good' => '500.000',
        'spare_part' => '10.000',
    ];

    /** Whole-unit base UOMs in the current inventory conversion policy. */
    private const WHOLE_UNIT_CODES = ['PC', 'PCS', 'PIECE', 'PIECES', 'EA', 'EACH', 'UNIT', 'UNITS', 'SET', 'SETS'];

    public function __construct(private readonly StockMovementService $movements) {}

    public function key(): string
    {
        return 'inventory-stock-coverage';
    }

    /** @return list<string> */
    public function modules(): array
    {
        return ['Inventory', 'Production', 'Maintenance'];
    }

    /** @return list<string> */
    public function dependencies(): array
    {
        return ['order-to-cash-history'];
    }

    protected function seedDemoData(DemoSeedContext $context): void
    {
        $locations = [];
        foreach (array_unique(self::LOCATION_CODES) as $code) {
            $locations[$code] = WarehouseLocation::query()
                ->where('code', $code)
                ->where('is_active', true)
                ->first()
                ?? throw new RuntimeException("Inventory stock coverage requires active location {$code}.");
        }

        $actor = User::query()
            ->where('is_active', true)
            ->whereHas('role', static fn ($query) => $query->where('slug', 'warehouse_staff'))
            ->orderBy('id')
            ->first()
            ?? throw new RuntimeException('Inventory stock coverage requires an active warehouse staff actor.');
        $asOf = $context->options->asOf->setTimezone('Asia/Manila');
        $at = $asOf->subMonthsNoOverflow(18)->startOfDay();
        $openingDate = $at->toDateString();

        $context->at($at, function () use ($context, $locations, $actor, $at, $openingDate): void {
            Item::query()->active()->orderBy('id')->chunkById(50, function ($items) use ($context, $locations, $actor, $at, $openingDate): void {
                foreach ($items as $item) {
                    $type = $item->item_type instanceof ItemType ? $item->item_type->value : (string) $item->item_type;
                    $locationCode = self::LOCATION_CODES[$type]
                        ?? throw new RuntimeException("Inventory stock coverage does not support active item type {$type}.");
                    $location = $locations[$locationCode];
                    $unitCost = (string) $item->standard_cost;
                    if (bccomp($unitCost, '0', 4) <= 0) {
                        throw new BusinessRuleException("Active item {$item->code} needs a positive standard cost before stock can be added.");
                    }

                    $quantity = $this->plannedQuantity($item, $type);
                    $lot = 'OGI-STK-'.$this->safeCode($item->code).'-'.str_replace('-', '', $openingDate);
                    $expiryDate = null;
                    $this->ensureMovement($context, $item, $location, $actor, $quantity, $unitCost, $lot, $expiryDate, $at);
                }
            });
        });
    }

    private function plannedQuantity(Item $item, string $type): string
    {
        $reorder = (string) ($item->reorder_point ?? '0.000');
        $safety = (string) ($item->safety_stock ?? '0.000');
        $minimumOrder = (string) ($item->minimum_order_quantity ?? '0.000');
        $floor = self::MINIMUM_BUFFER[$type];
        $reorderBuffer = bcmul($reorder, $type === ItemType::FinishedGood->value ? '1.25' : '1.50', 3);
        $safetyBuffer = bcmul($safety, '2.00', 3);
        $quantity = $this->greaterOf($floor, $reorderBuffer, $safetyBuffer, $minimumOrder);

        if (in_array(strtoupper(trim((string) $item->unit_of_measure)), self::WHOLE_UNIT_CODES, true)) {
            $quantity = bcadd($quantity, '0.999', 0).'.000';
        }

        return $quantity;
    }

    private function ensureMovement(
        DemoSeedContext $context,
        Item $item,
        WarehouseLocation $location,
        User $actor,
        string $quantity,
        string $unitCost,
        string $lot,
        ?string $expiryDate,
        CarbonImmutable $at,
    ): void {
        $key = 'inventory-stock-coverage:'.$item->code;
        $remarks = 'Additional opening balance to maintain production and maintenance stock coverage.';
        $fingerprint = hash('sha256', json_encode([
            (int) $item->id,
            (int) $location->id,
            $quantity,
            bcadd($unitCost, '0', 4),
            $lot,
            $expiryDate,
            $at->toIso8601String(),
            $remarks,
        ], JSON_THROW_ON_ERROR));
        $existing = StockMovement::query()->where('idempotency_key', $key)->first();
        if ($existing !== null) {
            if ($existing->idempotency_fingerprint !== $fingerprint
                || $existing->movement_type !== StockMovementType::Opening
                || (int) $existing->item_id !== (int) $item->id
                || (int) $existing->to_location_id !== (int) $location->id
                || bccomp((string) $existing->quantity, $quantity, 3) !== 0
                || bccomp((string) $existing->unit_cost, $unitCost, 4) !== 0
                || $existing->lot_number !== $lot
                || $existing->expiry_date?->toDateString() !== $expiryDate
                || $existing->created_at?->setTimezone('Asia/Manila')->format('Y-m-d H:i:s') !== $at->format('Y-m-d H:i:s')) {
                throw new BusinessRuleException("Stock coverage movement {$key} already exists with different data; refusing to alter its ledger history.");
            }

            $context->records()->remember('inventory.stock-coverage.'.$item->code, $existing);

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
            lotNumber: $lot,
            expiryDate: $expiryDate,
            idempotencyKey: $key,
            idempotencyFingerprint: $fingerprint,
        ));
        $context->records()->remember('inventory.stock-coverage.'.$item->code, $movement);
    }

    private function greaterOf(string ...$values): string
    {
        $highest = '0.000';
        foreach ($values as $value) {
            if (bccomp($value, $highest, 3) > 0) {
                $highest = bcadd($value, '0', 3);
            }
        }

        return $highest;
    }

    private function safeCode(string $code): string
    {
        return trim((string) preg_replace('/[^A-Z0-9-]/', '', strtoupper($code)), '-');
    }
}
