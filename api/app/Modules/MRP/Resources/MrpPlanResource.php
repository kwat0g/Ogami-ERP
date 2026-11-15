<?php

declare(strict_types=1);

namespace App\Modules\MRP\Resources;

use App\Common\Support\Money;
use App\Modules\MRP\Enums\MrpPlanStatus;
use App\Modules\MRP\Models\Bom;
use App\Modules\Production\Enums\WorkOrderStatus;
use App\Modules\Production\Models\WorkOrder;
use App\Modules\Purchasing\Enums\PurchaseOrderStatus;
use App\Modules\Purchasing\Enums\PurchaseRequestPriority;
use App\Modules\Purchasing\Enums\PurchaseRequestStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MrpPlanResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $rawDiagnostics = $this->diagnostics ?? [];
        $snapshotDiagnostics = $this->relationLoaded('workOrders')
            ? $this->stockMaterialSnapshotDiagnostics($rawDiagnostics)
            : [];
        $diagnostics = array_merge($rawDiagnostics, $snapshotDiagnostics);
        $costSummary = MrpPlanningResponseSerializer::costSummary($this->cost_summary);
        if ($this->relationLoaded('workOrders') && $this->isStockDerived()
            && ($costSummary === null || ! array_key_exists('material_cost', $costSummary))) {
            $costSummary = $this->stockCostSummary($snapshotDiagnostics);
        }

        return [
            'id' => $this->hash_id,
            'mrp_plan_no' => $this->mrp_plan_no,
            'source_type' => match (true) {
                $this->sales_order_id !== null => 'sales_order',
                $this->customer_production_request_id !== null => 'customer_production_request',
                $this->stock_production_work_order_id !== null => 'stock_production_work_order',
                default => null,
            },
            'sales_order' => $this->whenLoaded('salesOrder', fn () => $this->salesOrder ? [
                'id' => $this->salesOrder->hash_id,
                'so_number' => $this->salesOrder->so_number,
                'status' => $this->salesOrder->status->value,
                'customer' => $this->salesOrder->relationLoaded('customer') && $this->salesOrder->customer
                    ? ['id' => $this->salesOrder->customer->hash_id, 'name' => $this->salesOrder->customer->name]
                    : null,
            ] : null),
            'customer_production_request' => $this->whenLoaded('customerProductionRequest', fn () => $this->customerProductionRequest ? [
                'id' => $this->customerProductionRequest->hash_id,
                'request_number' => $this->customerProductionRequest->request_number ?? null,
                'status' => $this->customerProductionRequest->status?->value ?? (string) $this->customerProductionRequest->status,
                'requested_quantity' => (int) $this->customerProductionRequest->requested_quantity,
                'requested_need_by_date' => optional($this->customerProductionRequest->requested_need_by_date)->toDateString(),
                'customer' => $this->customerProductionRequest->relationLoaded('customer') && $this->customerProductionRequest->customer
                    ? ['id' => $this->customerProductionRequest->customer->hash_id, 'name' => $this->customerProductionRequest->customer->name]
                    : null,
            ] : null),
            'stock_production_work_order' => $this->whenLoaded('stockProductionWorkOrder', fn () => $this->stockProductionWorkOrder ? [
                'id' => $this->stockProductionWorkOrder->hash_id,
                'wo_number' => $this->stockProductionWorkOrder->wo_number,
                'product' => $this->stockProductionWorkOrder->relationLoaded('product') && $this->stockProductionWorkOrder->product
                    ? ['id' => $this->stockProductionWorkOrder->product->hash_id, 'name' => $this->stockProductionWorkOrder->product->name,
                        'part_number' => $this->stockProductionWorkOrder->product->part_number]
                    : null,
                'quantity_target' => (int) $this->stockProductionWorkOrder->quantity_target,
                'status' => $this->stockProductionWorkOrder->status?->value,
            ] : null),
            'version' => (int) $this->version,
            'status' => (string) $this->status?->value,
            'status_label' => MrpPlanStatus::tryFrom((string) $this->status?->value)?->label() ?? (string) $this->status?->value,
            'total_lines' => (int) $this->total_lines,
            'shortages_found' => (int) $this->shortages_found,
            'auto_pr_count' => (int) $this->auto_pr_count,
            'draft_wo_count' => (int) $this->draft_wo_count,
            'diagnostics' => MrpPlanningResponseSerializer::diagnostics($diagnostics),
            'cost_summary' => $costSummary,
            'generation' => MrpPlanningResponseSerializer::generationContext($this->generation_context),
            'generator' => $this->whenLoaded('generator', fn () => $this->generator ? [
                'id' => $this->generator->hash_id, 'name' => $this->generator->name,
            ] : null),
            'work_orders' => $this->whenLoaded('workOrders', fn () => $this->workOrders
                ->concat($this->relationLoaded('priorProgressedWorkOrders') ? $this->priorProgressedWorkOrders : [])
                ->map(fn ($w) => [
                    'id' => $w->hash_id, 'wo_number' => $w->wo_number,
                    'product_id' => $w->product_id === null ? null : app('hashids')->encode((int) $w->product_id),
                    'parent' => $w->relationLoaded('parent') && $w->parent ? [
                        'id' => $w->parent->hash_id,
                        'wo_number' => $w->parent->wo_number,
                    ] : null,
                    'quantity_target' => (int) $w->quantity_target,
                    'status' => (string) $w->status?->value,
                    'status_label' => WorkOrderStatus::tryFrom((string) $w->status?->value)?->label() ?? (string) $w->status?->value,
                    'planned_start' => optional($w->planned_start)->toIso8601String(),
                    'planned_end' => $w->planned_end?->toIso8601String(),
                    'preferred_production_date' => $w->preferred_production_date?->toDateString(),
                    'schedule' => $w->relationLoaded('currentProductionSchedule') && $w->currentProductionSchedule ? [
                        'scheduled_start' => $w->currentProductionSchedule->scheduled_start->toIso8601String(),
                        'scheduled_end' => $w->currentProductionSchedule->scheduled_end->toIso8601String(),
                    ] : null,
                ])
            ),
            'purchase_requests' => $this->whenLoaded('purchaseRequests', fn () => $this->purchaseRequests
                ->concat($this->relationLoaded('priorProgressedPurchaseRequests') ? $this->priorProgressedPurchaseRequests : [])
                ->map(function ($p): array {
                    $priority = $p->priority instanceof \BackedEnum ? $p->priority->value : (string) $p->priority;
                    $status = $p->status instanceof \BackedEnum ? $p->status->value : (string) $p->status;

                    return [
                        'id' => $p->hash_id, 'pr_number' => $p->pr_number,
                        'priority' => $priority, 'priority_label' => PurchaseRequestPriority::tryFrom($priority)?->label() ?? $priority, 'status' => $status,
                        'status_label' => PurchaseRequestStatus::tryFrom($status)?->label() ?? $status,
                        'is_auto_generated' => (bool) $p->is_auto_generated,
                        'date' => optional($p->date)->toDateString(),
                        'purchase_orders' => $p->relationLoaded('purchaseOrders') ? $p->purchaseOrders->map(function ($po): array {
                            $poStatus = $po->status instanceof \BackedEnum ? $po->status->value : (string) $po->status;

                            return [
                                'id' => $po->hash_id,
                                'po_number' => $po->po_number,
                                'status' => $poStatus,
                                'status_label' => PurchaseOrderStatus::tryFrom($poStatus)?->label() ?? $poStatus,
                            ];
                        }) : [],
                    ];
                })
            ),
            'generated_at' => optional($this->generated_at)->toIso8601String(),
            'rerun_outcome' => $this->resource->getAttribute('rerun_outcome'),
            'created_at' => optional($this->created_at)->toIso8601String(),
            'updated_at' => optional($this->updated_at)->toIso8601String(),
        ];
    }

    /** @param array<int, array<string, mixed>> $diagnostics @return list<array<string, mixed>> */
    private function stockMaterialSnapshotDiagnostics(array $diagnostics): array
    {
        if (! $this->isStockDerived() || collect($diagnostics)->contains('kind', 'stock_material_requirement')) {
            return [];
        }

        $workOrders = collect();
        if ($this->relationLoaded('stockProductionWorkOrder') && $this->stockProductionWorkOrder) {
            $workOrders->push($this->stockProductionWorkOrder);
        }
        if ($this->relationLoaded('workOrders')) {
            $workOrders = $workOrders->concat($this->workOrders);
        }
        $workOrders = $workOrders->filter(fn (WorkOrder $workOrder): bool => $workOrder->production_purpose === 'stock')
            ->unique('id')->values();
        if ($workOrders->isEmpty()) {
            return [];
        }
        foreach ($workOrders as $workOrder) {
            $workOrder->loadMissing('materials.item');
        }

        $plannedPurchases = collect($diagnostics)
            ->filter(fn (array $row): bool => ($row['kind'] ?? null) === 'material_shortage' && isset($row['item_id']))
            ->keyBy(fn (array $row): int => (int) $row['item_id']);
        $requirements = [];
        foreach ($workOrders as $workOrder) {
            foreach ($workOrder->materials as $material) {
                $itemId = (int) $material->item_id;
                $quantity = (string) $material->bom_quantity;
                $unitCost = (string) $material->standard_unit_cost;
                $requirements[$itemId] ??= [
                    'kind' => 'stock_material_requirement',
                    'type' => 'work_order_material_snapshot',
                    'item_id' => $itemId,
                    'item_code' => (string) ($material->item?->code ?? 'Unknown item'),
                    'item_name' => (string) ($material->item?->name ?? 'Material details unavailable'),
                    'unit' => (string) ($material->item?->unit_of_measure ?? ''),
                    'required_quantity' => '0.000',
                    'available_quantity' => null,
                    'shortage_quantity' => null,
                    'purchase_quantity' => isset($plannedPurchases[$itemId]) ? (string) $plannedPurchases[$itemId]['quantity'] : null,
                    'standard_unit_cost' => $unitCost,
                    'required_cost' => '0.00',
                    'shortage_cost' => null,
                    'work_order_numbers' => [],
                    'action' => isset($plannedPurchases[$itemId]) ? 'purchase_required' : 'availability_not_recorded',
                    '_unit_cost_total' => '0.00000000',
                ];

                $requirements[$itemId]['required_quantity'] = bcadd($requirements[$itemId]['required_quantity'], $quantity, 3);
                $requirements[$itemId]['required_cost'] = Money::add($requirements[$itemId]['required_cost'], (string) $material->standard_cost);
                $requirements[$itemId]['_unit_cost_total'] = bcadd(
                    $requirements[$itemId]['_unit_cost_total'],
                    bcmul($quantity, $unitCost, 8),
                    8,
                );
                $requirements[$itemId]['work_order_numbers'][] = (string) $workOrder->wo_number;
            }
        }

        foreach ($requirements as &$requirement) {
            if (bccomp($requirement['required_quantity'], '0', 3) > 0) {
                $requirement['standard_unit_cost'] = bcdiv($requirement['_unit_cost_total'], $requirement['required_quantity'], 4);
            }
            $requirement['work_order_numbers'] = array_values(array_unique($requirement['work_order_numbers']));
            unset($requirement['_unit_cost_total']);
        }
        unset($requirement);

        return array_values($requirements);
    }

    /** @param list<array<string, mixed>> $snapshotDiagnostics @return array<string, mixed>|null */
    private function stockCostSummary(array $snapshotDiagnostics): ?array
    {
        $workOrders = $this->relationLoaded('workOrders') ? $this->workOrders : collect();
        $root = ($this->relationLoaded('stockProductionWorkOrder') ? $this->stockProductionWorkOrder : null) ?? $workOrders->first(
            fn (WorkOrder $workOrder): bool => $workOrder->parent_wo_id === null,
        );
        if (! $root instanceof WorkOrder) {
            return null;
        }

        $bom = $root->bom_id ? Bom::withTrashed()->find($root->bom_id) : null;
        if ($bom) {
            $quantity = (string) $root->quantity_target;

            return [
                'material_cost' => Money::round2(bcmul($quantity, (string) ($bom->material_cost ?? '0.00'), 8)),
                'labor_cost' => Money::round2(bcmul($quantity, (string) ($bom->labor_cost ?? '0.00'), 8)),
                'machine_cost' => Money::round2(bcmul($quantity, (string) ($bom->machine_cost ?? '0.00'), 8)),
                'overhead_cost' => Money::round2(bcmul($quantity, (string) ($bom->overhead_cost ?? '0.00'), 8)),
                'planned_production_cost' => Money::round2(bcmul($quantity, (string) ($bom->total_cost ?? '0.00'), 8)),
            ];
        }

        if (count($snapshotDiagnostics) === 0 || $workOrders->count() > 1) {
            return null;
        }

        return [
            'material_cost' => Money::add(...array_map(
                static fn (array $row): string => (string) ($row['required_cost'] ?? '0.00'),
                $snapshotDiagnostics,
            )),
            'labor_cost' => null,
            'machine_cost' => null,
            'overhead_cost' => null,
            'planned_production_cost' => null,
        ];
    }

    private function isStockDerived(): bool
    {
        return $this->stock_production_work_order_id !== null || $this->customer_production_request_id !== null;
    }
}
