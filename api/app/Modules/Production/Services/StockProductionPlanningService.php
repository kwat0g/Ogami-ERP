<?php

declare(strict_types=1);

namespace App\Modules\Production\Services;

use App\Common\Exceptions\BusinessRuleException;
use App\Common\Services\ChainBroadcaster;
use App\Common\Services\DocumentSequenceService;
use App\Modules\Auth\Models\User;
use App\Modules\CRM\Models\Product;
use App\Modules\CRM\Models\CustomerProductionRequest;
use App\Modules\Inventory\Models\Item;
use App\Modules\Inventory\Models\StockLevel;
use App\Modules\Inventory\Services\StockLocationSummaryService;
use App\Common\Support\Money;
use App\Modules\MRP\Models\Bom;
use App\Modules\MRP\Services\BomService;
use App\Modules\MRP\Enums\MrpPlanStatus;
use App\Modules\MRP\Models\MrpPlan;
use App\Modules\Production\Models\WorkOrder;
use App\Modules\Production\Enums\WorkOrderStatus;
use App\Modules\Production\Support\StockProductionLimits;
use App\Modules\Purchasing\Enums\PurchaseRequestStatus;
use App\Modules\Purchasing\Models\PurchaseRequest;
use App\Modules\Purchasing\Services\OpenSupplyService;
use App\Modules\Purchasing\Services\PurchaseRequestService;
use App\Modules\Quality\Services\InspectionSpecService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class StockProductionPlanningService
{
    public function productReadiness(Product $product): array
    {
        $blockers = [];
        $mapping = Item::where('code', $product->part_number)->where('is_active', true)->where('item_type', 'finished_good')->get();
        if (! $product->is_active || $mapping->count() !== 1 || strtolower((string) $mapping->first()?->unit_of_measure) !== 'pcs' || strtolower((string) $product->unit_of_measure) !== 'pcs') {
            $blockers[] = 'Configure one active finished-good inventory item in pieces for this product.';
        }
        $boms = app(BomService::class);
        $bom = $boms->activeForProduct($product->id);
        if (! $bom) {
            $blockers[] = 'Configure an active BOM before planning stock production.';
        } else {
            try {
                $boms->assertComponentIntegrity($bom);
            } catch (BusinessRuleException $error) {
                $blockers[] = $error->getMessage();
            }
        }
        try {
            $product->loadMissing('inspectionSpec.items', 'inspectionSpec.currentRevision');
            app(InspectionSpecService::class)->assertInspectable($product->inspectionSpec);
        } catch (BusinessRuleException $error) {
            $blockers[] = $error->getMessage();
        }

        return ['eligible' => $blockers === [], 'blockers' => $blockers];
    }

    public function create(array $data, User $actor): WorkOrder
    {
        $key = strtolower((string) ($data['request_key'] ?? ''));
        if (! Str::isUuid($key) || ! empty($data['sales_order_id']) || ! empty($data['sales_order_item_id'])
            || ! preg_match('/^[1-9]\d*$/D', (string) ($data['quantity_target'] ?? ''))) {
            throw new BusinessRuleException('Stock production needs a UUID request key and positive whole-piece quantity without a Sales Order.');
        }
        $canonical = array_intersect_key($data, array_flip([
            'product_id', 'quantity_target', 'planned_start', 'planned_end', 'preferred_production_date', 'minimum_workers', 'maximum_workers',
        ])) + ['actor_id' => $actor->id];
        ksort($canonical);
        $fingerprint = hash('sha256', json_encode($canonical, JSON_THROW_ON_ERROR));

        return DB::transaction(function () use ($data, $actor, $key, $fingerprint): WorkOrder {
            // Serialize all stock planning/allocation before taking product, QC and stock locks.
            DB::statement('SELECT pg_advisory_xact_lock(20261009, 1)');
            $prior = WorkOrder::withTrashed()->where('stock_creation_request_key', $key)->first();
            if ($prior) {
                if ($prior->trashed() || ! hash_equals((string) $prior->stock_creation_fingerprint, $fingerprint)) {
                    throw new BusinessRuleException('This stock creation key already belongs to another payload or archived work order.');
                }
                $this->ensureMrpPlan($prior, $actor);
                $this->reconcile($prior, $actor);

                return app(WorkOrderService::class)->show($prior->fresh());
            }
            if ((int) $data['quantity_target'] > StockProductionLimits::MAX_QUANTITY) {
                throw new BusinessRuleException('Stock work orders are limited to 5,000 pieces.');
            }
            if (Carbon::parse($data['planned_end'])->lte(now())
                || (int) ($data['minimum_workers'] ?? 1) < 1 || (int) ($data['maximum_workers'] ?? 4) > 4
                || (int) ($data['maximum_workers'] ?? 4) < (int) ($data['minimum_workers'] ?? 1)) {
                throw new BusinessRuleException('Stock deadline must be future and workers between 1 and 4.');
            }
            $product = Product::query()->lockForUpdate()->findOrFail($data['product_id']);
            $readiness = $this->productReadiness($product);
            if (! $readiness['eligible']) {
                throw new BusinessRuleException(implode(' ', $readiness['blockers']));
            }
            $boms = app(BomService::class);
            $boms->assertComponentIntegrity($boms->ensureFreshForPlanning($boms->activeForProduct($product->id)));
            $root = app(WorkOrderService::class)->createDraft(array_replace($data, [
                'production_purpose' => 'stock', 'stock_creation_request_key' => $key,
                'stock_creation_fingerprint' => $fingerprint, 'created_by' => $actor->id,
            ]));
            $this->ensureMrpPlan($root, $actor);
            $this->reconcile($root, $actor);

            return app(WorkOrderService::class)->show($root->fresh());
        }, 3);
    }

    public function createForCustomerRequest(array $data, User $actor, CustomerProductionRequest $request): WorkOrder
    {
        $key = strtolower((string) ($request->planning_request_key ?? ''));
        if (! Str::isUuid($key) || $request->status->value !== 'planning'
            || (int) $data['product_id'] !== (int) $request->product_id
            || (int) $data['quantity_target'] !== (int) $request->requested_quantity) {
            throw new BusinessRuleException('Customer production planning must match an active request and its exact product quantity.');
        }

        return DB::transaction(function () use ($data, $actor, $request, $key): WorkOrder {
            DB::statement('SELECT pg_advisory_xact_lock(20261009, 1)');
            $request = CustomerProductionRequest::query()->lockForUpdate()->findOrFail($request->id);
            $activePlan = MrpPlan::query()
                ->where('customer_production_request_id', $request->id)
                ->where('status', MrpPlanStatus::Active->value)
                ->lockForUpdate()->first();
            if ($activePlan) {
                $root = WorkOrder::query()->where('mrp_plan_id', $activePlan->id)
                    ->whereNull('parent_wo_id')->lockForUpdate()->first();
                if (! $root) {
                    throw new BusinessRuleException('The active request plan has no root work order. Review its planning history before retrying.');
                }
                $this->reconcile($root, $actor);

                return app(WorkOrderService::class)->show($root->fresh());
            }
            if ((int) $request->requested_quantity > StockProductionLimits::MAX_QUANTITY
                || Carbon::parse($data['planned_end'])->lte(now())
                || (int) ($data['minimum_workers'] ?? 1) < 1 || (int) ($data['maximum_workers'] ?? 4) > 4
                || (int) ($data['maximum_workers'] ?? 4) < (int) ($data['minimum_workers'] ?? 1)) {
                throw new BusinessRuleException('Request quantity, deadline, or worker range cannot be planned. Review the request with the customer.');
            }

            $product = Product::query()->lockForUpdate()->findOrFail($request->product_id);
            $readiness = $this->productReadiness($product);
            if (! $readiness['eligible']) {
                throw new BusinessRuleException(implode(' ', $readiness['blockers']));
            }
            $boms = app(BomService::class);
            $boms->assertComponentIntegrity($boms->ensureFreshForPlanning($boms->activeForProduct($product->id)));

            $fingerprint = hash('sha256', json_encode([
                'customer_production_request_id' => $request->id,
                'product_id' => $product->id,
                'quantity_target' => $request->requested_quantity,
                'planned_end' => Carbon::parse($data['planned_end'])->toIso8601String(),
            ], JSON_THROW_ON_ERROR));
            $root = app(WorkOrderService::class)->createDraft(array_replace($data, [
                'production_purpose' => 'stock',
                'stock_creation_request_key' => $key,
                'stock_creation_fingerprint' => $fingerprint,
                'created_by' => $actor->id,
            ]));
            $plan = MrpPlan::create([
                'mrp_plan_no' => app(DocumentSequenceService::class)->generate('mrp_plan'),
                'customer_production_request_id' => $request->id,
                'version' => 1,
                'status' => MrpPlanStatus::Active->value,
                'generated_by' => $actor->id,
                'total_lines' => 0,
                'shortages_found' => 0,
                'auto_pr_count' => 0,
                'draft_wo_count' => 0,
                'diagnostics' => [],
                'cost_summary' => [],
                'generation_context' => [
                    'source' => 'customer_production_request',
                    'trigger' => 'request_planning',
                    'reason' => 'customer_production_request',
                    'actor_type' => 'user',
                    'source_id' => $request->id,
                    'actor_id' => $actor->id,
                ],
                'generated_at' => now(),
            ]);
            $root->forceFill(['mrp_plan_id' => $plan->id])->save();
            $this->reconcile($root->fresh(), $actor);

            return app(WorkOrderService::class)->show($root->fresh());
        }, 3);
    }

    public function reconcile(WorkOrder $root, User $actor): array
    {
        return DB::transaction(function () use ($root, $actor): array {
            DB::statement('SELECT pg_advisory_xact_lock(20261009, 1)');
            $root = WorkOrder::query()->lockForUpdate()->findOrFail($root->id);
            if ($root->production_purpose !== 'stock' || $root->sales_order_id || $root->status->value !== 'planned'
                || $root->currentProductionSchedule()->exists() || $root->materials()->where('actual_quantity_issued', '>', 0)->exists()) {
                return [];
            }
            $this->ensureMrpPlan($root, $actor);
            $available = [];
            $productionPlan = app(BomService::class)->productionPlan($root->product_id, (float) $root->quantity_target,
                function (int $productId, int $itemId, float $gross) use (&$available): float {
                    $available[$itemId] ??= max(0, (float) app(StockLocationSummaryService::class)->availability(
                        StockLevel::where('item_id', $itemId)->with('location.zone.warehouse')->get())['usable_quantity'] - (float) Item::findOrFail($itemId)->safety_stock);
                    $take = min($gross, $available[$itemId]);
                    $available[$itemId] -= $take;

                    return $gross - $take;
                });
            app(WorkOrderService::class)->reconcilePlannedMaterialPlan($root);
            $this->children($productionPlan['subassemblies'], $root, $actor);
            $shortages = [];
            $materialRequirements = [];
            foreach ($productionPlan['materials'] as $row) {
                $item = Item::findOrFail($row['item_id']);
                $supply = app(OpenSupplyService::class)->stockPlanningSupply($item->id, $root->id);
                $available = bccomp($supply['available_to_root'], '0', 3) < 0
                    ? '0.000'
                    : bcadd((string) $supply['available_to_root'], '0', 3);
                $shortage = bcsub((string) $row['gross_quantity'], $available, 3);
                if (bccomp($shortage, '0', 3) < 0) {
                    $shortage = '0.000';
                }
                $purchaseQuantity = $shortage;
                if (bccomp($purchaseQuantity, '0', 3) > 0
                    && bccomp((string) $item->minimum_order_quantity, '0', 3) > 0) {
                    $multiple = bcdiv($purchaseQuantity, (string) $item->minimum_order_quantity, 8);
                    $purchaseQuantity = bcmul((string) ceil((float) $multiple), (string) $item->minimum_order_quantity, 3);
                }

                $standardUnitCost = (string) $item->standard_cost;
                $materialRequirements[] = [
                    'kind' => 'stock_material_requirement',
                    'type' => 'stock_material_requirement',
                    'item_id' => (int) $item->id,
                    'item_code' => (string) $item->code,
                    'item_name' => (string) $item->name,
                    'unit' => (string) $item->unit_of_measure,
                    'required_quantity' => (string) $row['gross_quantity'],
                    'available_quantity' => $available,
                    'shortage_quantity' => $shortage,
                    'purchase_quantity' => $purchaseQuantity,
                    'standard_unit_cost' => $standardUnitCost,
                    'required_cost' => Money::round2(bcmul((string) $row['gross_quantity'], $standardUnitCost, 8)),
                    'shortage_cost' => Money::round2(bcmul($shortage, $standardUnitCost, 8)),
                    'action' => bccomp($shortage, '0', 3) > 0 ? 'purchase_required' : 'covered',
                ];

                $needed = $purchaseQuantity;
                if (bccomp($needed, '0', 3) > 0) {
                    $shortages[] = [$item, $needed];
                }
            }
            $request = PurchaseRequest::where('stock_production_work_order_id', $root->id)->where('status', '!=', 'cancelled')->lockForUpdate()->first();
            $requestWasCreated = false;
            if ($shortages !== [] && ! $request) {
                $request = PurchaseRequest::create([
                    'pr_number' => app(DocumentSequenceService::class)->generate('pr'),
                    'requested_by' => $actor->id, 'department_id' => DB::table('departments')->where('code', 'PPC')->value('id'),
                    'mrp_plan_id' => $root->mrp_plan_id,
                    'date' => today(), 'required_delivery_date' => $root->planned_end->toDateString(),
                    'reason' => "Materials for stock production {$root->wo_number}", 'priority' => 'normal',
                    'is_auto_generated' => true, 'stock_production_work_order_id' => $root->id,
                ]);
                $request->forceFill(['status' => 'draft'])->save();
                $requestWasCreated = true;
            }
            if ($request && ($request->status?->value ?? 'draft') === 'draft' && ! $request->purchaseOrders()->exists() && ! $request->rfqs()->exists()) {
                if ((int) $request->mrp_plan_id !== (int) $root->mrp_plan_id) {
                    $request->forceFill(['mrp_plan_id' => $root->mrp_plan_id])->save();
                }
                if ($shortages === []) {
                    app(PurchaseRequestService::class)->cancel($request);
                    $this->syncMrpPlan($root, $productionPlan, $materialRequirements, [], null);

                    return ['material_shortages' => 0];
                }
                $request->items()->delete();
                foreach ($shortages as [$item, $quantity]) {
                    $request->items()->create([
                        'item_id' => $item->id, 'description' => $item->name, 'quantity' => $quantity,
                        'unit' => $item->unit_of_measure, 'estimated_unit_price' => $item->standard_cost ?? '0',
                        'purpose' => "Stock work order {$root->wo_number}",
                    ]);
                }
            }

            if ($shortages !== [] && $request && ($request->status->value !== 'draft' || $request->purchaseOrders()->exists() || $request->rfqs()->exists())) {
                throw new BusinessRuleException("Procurement must review additional material requirements against {$request->pr_number} before replanning.");
            }
            if ($request && $shortages !== []) {
                app(ChainBroadcaster::class)->broadcastFor(
                    $request->fresh(),
                    PurchaseRequestStatus::Draft->value,
                    $actor,
                    action: $requestWasCreated ? 'created' : 'updated',
                );
            }

            $this->syncMrpPlan($root, $productionPlan, $materialRequirements, $shortages, $request);

            return ['material_shortages' => count($shortages)];
        }, 3);
    }

    private function ensureMrpPlan(WorkOrder $root, User $actor): MrpPlan
    {
        $root = WorkOrder::query()->lockForUpdate()->findOrFail($root->id);
        if ($root->production_purpose !== 'stock' || $root->parent_wo_id !== null || $root->sales_order_id !== null) {
            throw new BusinessRuleException('Only an internal root stock work order can own a stock production MRP plan.');
        }

        if ($root->mrp_plan_id !== null) {
            $linked = MrpPlan::query()->lockForUpdate()->find($root->mrp_plan_id);
            if ($linked && $linked->status === MrpPlanStatus::Active
                && ($linked->stock_production_work_order_id === $root->id || $linked->customer_production_request_id !== null)) {
                return $linked;
            }
            if ($linked && $linked->customer_production_request_id !== null) {
                throw new BusinessRuleException('The customer request MRP plan is no longer active. Review the request before replanning.');
            }
        }

        $plan = MrpPlan::query()->where('stock_production_work_order_id', $root->id)
            ->where('status', MrpPlanStatus::Active->value)->lockForUpdate()->first();

        if (! $plan) {
            $plan = MrpPlan::create([
                'mrp_plan_no' => app(DocumentSequenceService::class)->generate('mrp_plan'),
                'stock_production_work_order_id' => $root->id,
                'version' => 1,
                'status' => MrpPlanStatus::Active->value,
                'generated_by' => $actor->id,
                'total_lines' => 0,
                'shortages_found' => 0,
                'auto_pr_count' => 0,
                'draft_wo_count' => 0,
                'diagnostics' => [],
                'cost_summary' => [],
                'generation_context' => [
                    'source' => 'stock_production_work_order',
                    'trigger' => 'produce_for_stock',
                    'reason' => 'internal_stock_production',
                    'actor_type' => 'user',
                    'source_id' => $root->id,
                    'actor_id' => $actor->id,
                ],
                'generated_at' => now(),
            ]);
        }

        if ((int) $root->mrp_plan_id !== (int) $plan->id) {
            $root->forceFill(['mrp_plan_id' => $plan->id])->save();
        }

        return $plan;
    }

    /** @param array{materials: array, subassemblies: array} $productionPlan */
    private function syncMrpPlan(WorkOrder $root, array $productionPlan, array $materialRequirements, array $shortages, ?PurchaseRequest $request): void
    {
        $plan = $root->mrp_plan_id !== null
            ? MrpPlan::query()->whereKey($root->mrp_plan_id)->where('status', MrpPlanStatus::Active->value)->lockForUpdate()->first()
            : null;
        if (! $plan) {
            return;
        }

        $diagnostics = [[
            'kind' => 'stock_production_plan',
            'type' => 'exact_make_quantity',
            'work_order_id' => (int) $root->id,
            'quantity_target' => (int) $root->quantity_target,
            'material_line_count' => count($productionPlan['materials']),
            'subassembly_count' => count($productionPlan['subassemblies']),
        ]];
        array_push($diagnostics, ...$materialRequirements);
        foreach ($shortages as [$item, $quantity]) {
            $diagnostics[] = [
                'kind' => 'material_shortage',
                'type' => 'stock_material_shortage',
                'item_id' => (int) $item->id,
                'item_code' => $item->code,
                'item_name' => $item->name,
                'quantity' => (string) $quantity,
            ];
        }

        $plan->forceFill([
            'total_lines' => count($productionPlan['materials']),
            'shortages_found' => count($shortages),
            'auto_pr_count' => $request && $request->status?->value !== 'cancelled' ? 1 : 0,
            'draft_wo_count' => WorkOrder::query()->where('mrp_plan_id', $plan->id)
                ->where('status', WorkOrderStatus::Planned->value)->count(),
            'diagnostics' => $diagnostics,
            'cost_summary' => $this->costSummary($root),
            'generated_at' => now(),
        ])->save();
    }

    /** @return array{material_cost:string, labor_cost:string, machine_cost:string, overhead_cost:string, planned_production_cost:string} */
    private function costSummary(WorkOrder $root): array
    {
        $bom = Bom::query()->where('product_id', $root->product_id)->where('is_active', true)->first();
        if ($bom === null) {
            return [];
        }

        $bom = app(BomService::class)->ensureFreshForPlanning($bom);
        $quantity = (string) $root->quantity_target;

        return [
            'material_cost' => Money::round2(bcmul($quantity, (string) ($bom->material_cost ?? '0.00'), 8)),
            'labor_cost' => Money::round2(bcmul($quantity, (string) ($bom->labor_cost ?? '0.00'), 8)),
            'machine_cost' => Money::round2(bcmul($quantity, (string) ($bom->machine_cost ?? '0.00'), 8)),
            'overhead_cost' => Money::round2(bcmul($quantity, (string) ($bom->overhead_cost ?? '0.00'), 8)),
            'planned_production_cost' => Money::round2(bcmul($quantity, (string) ($bom->total_cost ?? '0.00'), 8)),
        ];
    }

    private function children(array $nodes, WorkOrder $parent, User $actor): void
    {
        $needed = collect($nodes)->filter(fn ($node) => bccomp($node['quantity'], '0', 3) > 0);
        foreach ($parent->children()->where('status', '!=', 'cancelled')->whereNotIn('product_id', $needed->pluck('product_id'))->lockForUpdate()->get() as $obsolete) {
            if ($obsolete->status->value === 'completed' || $obsolete->status->value === 'closed') {
                continue;
            }
            $this->retireUntouched($obsolete);
        }
        foreach ($needed as $node) {
            $child = WorkOrder::where('parent_wo_id', $parent->id)->where('product_id', $node['product_id'])->where('status', '!=', 'cancelled')->lockForUpdate()->first();
            $target = (int) ceil((float) $node['quantity']);
            if (! $child) {
                $child = app(WorkOrderService::class)->createDraft([
                    'product_id' => $node['product_id'], 'quantity_target' => $target,
                    'planned_start' => $parent->planned_start, 'planned_end' => $parent->planned_end,
                    'parent_wo_id' => $parent->id, 'production_purpose' => 'stock',
                    'mrp_plan_id' => $parent->mrp_plan_id, 'created_by' => $actor->id,
                ]);
            }
            if ($child->status->value === 'planned' && ! $child->currentProductionSchedule()->exists()
                && ! $child->materials()->where('actual_quantity_issued', '>', 0)->exists()) {
                $child->forceFill(['quantity_target' => $target, 'mrp_plan_id' => $parent->mrp_plan_id])->save();
                app(WorkOrderService::class)->reconcilePlannedMaterialPlan($child);
                $this->children($node['children'], $child, $actor);
            } elseif ((int) $child->quantity_target !== $target) {
                throw new BusinessRuleException('Started subassembly work prevents this stock-plan change. Complete or review its commitment first.');
            }
        }
    }

    private function retireUntouched(WorkOrder $child): void
    {
        if ($child->status->value !== 'planned' || $child->currentProductionSchedule()->exists()
            || $child->outputs()->exists() || $child->materials()->where('actual_quantity_issued', '>', 0)->exists()) {
            throw new BusinessRuleException('Committed subassembly work prevents this stock-plan change.');
        }
        foreach ($child->children()->lockForUpdate()->get() as $descendant) {
            $this->retireUntouched($descendant);
        }
        app(WorkOrderService::class)->cancel($child, 'Untouched subassembly no longer required by its stock production plan.');
    }

    public function cancelCustomerRequestPlan(CustomerProductionRequest $request, string $reason): void
    {
        DB::statement('SELECT pg_advisory_xact_lock(20261009, 1)');
        $plan = MrpPlan::query()->where('customer_production_request_id', $request->id)
            ->where('status', MrpPlanStatus::Active->value)->lockForUpdate()->first();
        if (! $plan) return;
        $root = WorkOrder::query()->where('mrp_plan_id', $plan->id)->whereNull('parent_wo_id')->lockForUpdate()->first();
        if ($root && $root->status !== WorkOrderStatus::Cancelled) {
            app(WorkOrderService::class)->cancel($root, $reason);
        }
        $plan->forceFill(['status' => MrpPlanStatus::Cancelled->value])->save();
    }
}
