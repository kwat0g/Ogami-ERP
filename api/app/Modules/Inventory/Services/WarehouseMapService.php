<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Services;

use App\Modules\Inventory\Models\StockMovement;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Inventory\Models\WarehouseLocation;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;

class WarehouseMapService
{
    public function __construct(private readonly StockLocationSummaryService $locationSummary) {}

    /**
     * Get full warehouse tree with bin occupancy data for the visual map.
     */
    public function map(): Collection
    {
        $warehouses = Warehouse::query()
            ->with([
                'zones' => fn ($q) => $q->orderBy('code'),
                'zones.locations' => fn ($q) => $q->orderBy('code')->with('stockLevels.item'),
                'zones.locations.zone.warehouse',
            ])
            ->orderBy('name')
            ->get();

        foreach ($warehouses as $warehouse) {
            foreach ($warehouse->zones as $zone) {
                $zoneOccupiedKg = '0.000000000';
                $zoneUntrackedLineCount = 0;

                foreach ($zone->locations as $location) {
                    $occupiedKg = $this->locationSummary->occupiedKg($location->stockLevels);
                    $untrackedLineCount = $this->locationSummary->untrackedCapacityLineCount($location->stockLevels);
                    $location->setAttribute(
                        'inventory_summary',
                        $this->locationSummary->summarize($location->stockLevels),
                    );
                    $location->setAttribute('capacity_used_kg', $occupiedKg);
                    $location->setAttribute('capacity_untracked_line_count', $untrackedLineCount);
                    $location->setAttribute(
                        'capacity_untracked_items',
                        $this->locationSummary->untrackedCapacityItems($location->stockLevels),
                    );
                    $zoneOccupiedKg = bcadd($zoneOccupiedKg, $occupiedKg, 9);
                    $zoneUntrackedLineCount += $untrackedLineCount;
                }

                $zone->setAttribute('capacity_used_kg', $zoneOccupiedKg);
                $zone->setAttribute('capacity_untracked_line_count', $zoneUntrackedLineCount);
            }
        }

        return $warehouses;
    }

    /**
     * Get bin occupancy summary for a specific location.
     */
    public function binDetail(int $locationId): ?array
    {
        $loc = WarehouseLocation::with(['zone.warehouse', 'stockLevels.item'])->find($locationId);
        if (! $loc) {
            return null;
        }

        // Stock levels are authoritative; current_* on warehouse_locations is
        // a legacy projection and is deliberately not read.
        $stockLevels = $loc->stockLevels;
        $summary = $this->locationSummary->summarize($stockLevels);

        // Get last movement to/from this location
        $lastMovement = StockMovement::query()
            ->where(function ($q) use ($locationId) {
                $q->where('from_location_id', $locationId)
                    ->orWhere('to_location_id', $locationId);
            })
            ->latest()
            ->first();

        return [
            'location' => [
                'id' => $loc->hash_id,
                'code' => $loc->code,
                'full_code' => $loc->full_code,
                'rack' => $loc->rack,
                'bin' => $loc->bin,
                'is_active' => $loc->is_active,
                'is_blocked' => $loc->is_blocked,
                'blocked_reason' => $loc->blocked_reason,
                'capacity_kg' => $loc->capacity_kg,
                'capacity_status' => $loc->capacity_status,
                'capacity_used_kg' => $this->locationSummary->occupiedKg($stockLevels),
                'capacity_untracked_line_count' => $this->locationSummary->untrackedCapacityLineCount($stockLevels),
                'current_item' => $summary['current_item'] ? [
                    'id' => $summary['current_item']->hash_id,
                    'code' => $summary['current_item']->code,
                    'name' => $summary['current_item']->name,
                    'unit_of_measure' => $summary['current_item']->unit_of_measure,
                ] : null,
                'current_quantity' => $summary['current_quantity'],
                'current_lot_number' => $summary['current_lot_number'],
                'current_expiry_date' => $summary['current_expiry_date'],
                'zone' => [
                    'id' => $loc->zone?->hash_id,
                    'code' => $loc->zone?->code,
                    'name' => $loc->zone?->name,
                    'zone_type' => $loc->zone?->zone_type?->value,
                    'warehouse' => $loc->zone?->warehouse ? [
                        'id' => $loc->zone->warehouse->hash_id,
                        'code' => $loc->zone->warehouse->code,
                        'name' => $loc->zone->warehouse->name,
                    ] : null,
                ],
            ],
            'stock_levels' => $stockLevels->map(fn ($sl) => [
                'item_id' => $sl->item?->hash_id,
                'item_code' => $sl->item?->code,
                'item_name' => $sl->item?->name,
                'quantity' => $sl->quantity,
                'reserved' => $sl->reserved_quantity,
                'available' => $sl->available,
                'unit_of_measure' => $sl->item?->unit_of_measure,
                'weight_kg_per_unit' => $sl->item?->weight_kg_per_unit,
                'usable_quantity' => $this->locationSummary->availability(collect([$sl]))['usable_quantity'],
                'unit_cost' => $sl->weighted_avg_cost,
                'total_value' => $sl->total_value,
            ]),
            'last_movement' => $lastMovement ? [
                'id' => $lastMovement->hash_id,
                'movement_type' => $lastMovement->movement_type->value,
                'movement_type_label' => Str::headline($lastMovement->movement_type->value),
                'item_code' => $lastMovement->item?->code,
                'quantity' => $lastMovement->quantity,
                'direction' => $lastMovement->to_location_id === $locationId ? 'in' : 'out',
                'created_at' => $lastMovement->created_at?->toISOString(),
            ] : null,
        ];
    }
}
