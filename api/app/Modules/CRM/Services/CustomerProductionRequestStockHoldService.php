<?php

declare(strict_types=1);

namespace App\Modules\CRM\Services;

use App\Modules\CRM\Enums\CustomerProductionRequestStatus as Status;
use App\Modules\CRM\Models\CustomerProductionRequest;
use App\Modules\CRM\Models\CustomerProductionRequestStockHold;
use App\Modules\CRM\Models\SalesOrderItem;
use App\Modules\CRM\Models\SalesOrderStockAllocation;
use App\Modules\Inventory\Services\StockMovementService;
use App\Modules\MRP\Enums\MrpPlanStatus;
use App\Modules\MRP\Models\MrpPlan;
use App\Modules\Quality\Models\Inspection;
use App\Modules\Production\Models\WorkOrder;
use App\Modules\Quality\Events\InspectionPassed;
use App\Modules\Quality\Events\OutgoingQuantityReleased;
use Illuminate\Support\Facades\DB;

final class CustomerProductionRequestStockHoldService
{
    public function tryReadyForWorkOrder(WorkOrder $workOrder): bool
    {
        $plan = $workOrder->mrpPlan()->first();
        $requestId = $plan?->customer_production_request_id;
        return $requestId ? $this->tryReady($requestId) : false;
    }

    public function handleQualityRelease(InspectionPassed|OutgoingQuantityReleased $event): void
    {
        $inspection = Inspection::query()->with('workOrderOutput.workOrder')->find($event->inspection->id);
        if (! $inspection || $inspection->stage?->value !== 'outgoing' || ! $inspection->workOrderOutput?->workOrder) {
            return;
        }
        $this->tryReadyForWorkOrder($inspection->workOrderOutput->workOrder);
    }

    public function tryReady(int $requestId): bool
    {
        return DB::transaction(function () use ($requestId): bool {
            DB::statement('SELECT pg_advisory_xact_lock(20261009, 1)');
            $request = CustomerProductionRequest::query()->with('product.inspectionSpec.currentRevision')
                ->lockForUpdate()->find($requestId);
            if (! $request || ! in_array($request->status, [Status::Accepted, Status::InProduction], true)) {
                return false;
            }
            $plan = MrpPlan::query()->where('customer_production_request_id', $request->id)
                ->where('status', MrpPlanStatus::Active->value)->orderByDesc('version')->first();
            if (! $plan) return false;

            $held = CustomerProductionRequestStockHold::query()->where('customer_production_request_id', $request->id)
                ->where('status', 'held')->where('expires_at', '>', now())->lockForUpdate()->get();
            $heldQuantity = $held->reduce(static fn (string $sum, CustomerProductionRequestStockHold $hold): string =>
                bcadd($sum, $hold->untransferredQuantity(), 3), '0.000');
            $needed = bcsub((string) $request->requested_quantity, $heldQuantity, 3);
            if (bccomp($needed, '0', 3) <= 0) return false;

            $line = new SalesOrderItem([
                'product_id' => $request->product_id,
                'quantity' => (string) $request->requested_quantity,
                'fulfillment_policy' => 'stock_first',
            ]);
            $line->setRelation('product', $request->product);
            $sources = app(SalesOrderSupplyService::class)->sources($line, true, (int) $request->id);
            $sources = array_values(array_filter($sources, static fn (array $source): bool =>
                (int) $source['output']->workOrder?->mrp_plan_id === (int) $plan->id));
            $candidateQuantity = array_reduce($sources, static fn (string $sum, array $source): string =>
                bcadd($sum, (string) $source['quantity'], 3), '0.000');
            if (bccomp($candidateQuantity, $needed, 3) < 0) return false;

            $expiresAt = now()->addHours(48);
            $remaining = $needed;
            foreach ($sources as $source) {
                if (bccomp($remaining, '0', 3) <= 0) break;
                $quantity = bccomp((string) $source['quantity'], $remaining, 3) < 0 ? (string) $source['quantity'] : $remaining;
                if (bccomp($quantity, '0', 3) <= 0) continue;
                app(StockMovementService::class)->reserve((int) $source['item']->id, (int) $source['level']->location_id, $quantity);
                CustomerProductionRequestStockHold::query()->create([
                    'customer_production_request_id' => $request->id,
                    'work_order_output_id' => $source['output']->id,
                    'inspection_id' => $source['inspection']->id,
                    'item_id' => $source['item']->id,
                    'location_id' => $source['level']->location_id,
                    'lot_number' => $source['receipt']->lot_number,
                    'expiry_date' => $source['receipt']->expiry_date,
                    'quantity' => $quantity,
                    'status' => 'held',
                    'expires_at' => $expiresAt,
                ]);
                $remaining = bcsub($remaining, $quantity, 3);
            }
            if (bccomp($remaining, '0', 3) > 0) {
                throw new \App\Common\Exceptions\BusinessRuleException('Finished goods changed while Ogami was reserving the complete production request quantity. Retry after stock is reconciled.');
            }

            $requestService = app(CustomerProductionRequestService::class);
            $request = $requestService->transition($request, Status::ReadyToOrder, 'ready_to_order', 'system', null, [
                'held_quantity' => (int) $request->requested_quantity,
                'hold_expires_at' => $expiresAt->toIso8601String(),
            ], ['ready_notified_at' => now(), 'hold_expires_at' => $expiresAt]);
            $requestService->notifyPortal($request, 'customer_production_request.ready', 'Production is ready to order',
                "{$request->requested_quantity} pieces of {$request->product?->name} passed outgoing QC and are held until {$expiresAt->format('M j, Y g:i A')}.");

            return true;
        }, 3);
    }

    public function transferToOrder(CustomerProductionRequest $request, SalesOrderItem $line, int $actorId, string $requestKey, int $portalUserId): void
    {
        $request = CustomerProductionRequest::query()->lockForUpdate()->findOrFail($request->id);
        if ($request->status !== Status::ReadyToOrder || $request->hold_expires_at?->lte(now())
            || (int) $line->product_id !== (int) $request->product_id
            || bccomp((string) $line->quantity, (string) $request->requested_quantity, 3) !== 0) {
            throw new \App\Common\Exceptions\BusinessRuleException('The production request hold is no longer eligible for this exact Sales Order quantity.');
        }
        $holds = CustomerProductionRequestStockHold::query()->where('customer_production_request_id', $request->id)
            ->where('status', 'held')->where('expires_at', '>', now())->orderBy('id')->lockForUpdate()->get();
        $sum = $holds->reduce(static fn (string $total, CustomerProductionRequestStockHold $hold): string =>
            bcadd($total, $hold->untransferredQuantity(), 3), '0.000');
        if (bccomp($sum, (string) $request->requested_quantity, 3) !== 0) {
            throw new \App\Common\Exceptions\BusinessRuleException('The full production request quantity is not currently held. Refresh availability and contact Ogami Sales.');
        }
        foreach ($holds as $hold) {
            $quantity = $hold->untransferredQuantity();
            $allocation = SalesOrderStockAllocation::query()->create([
                'sales_order_item_id' => $line->id,
                'work_order_output_id' => $hold->work_order_output_id,
                'inspection_id' => $hold->inspection_id,
                'item_id' => $hold->item_id,
                'location_id' => $hold->location_id,
                'lot_number' => $hold->lot_number,
                'expiry_date' => $hold->expiry_date,
                'quantity' => $quantity,
                'status' => 'reserved',
                'allocated_by' => $actorId,
                'request_key' => $requestKey,
                'payload_fingerprint' => hash('sha256', implode(':', [$request->id, $hold->id, $line->id, $quantity])),
            ]);
            $hold->forceFill([
                'status' => 'transferred',
                'transferred_quantity' => bcadd((string) $hold->transferred_quantity, $quantity, 3),
                'transferred_at' => now(),
                'sales_order_stock_allocation_id' => $allocation->id,
            ])->save();
        }
        app(CustomerProductionRequestService::class)->transition($request, Status::Converted, 'converted_to_sales_order', 'customer_portal', $portalUserId, [
            'sales_order_id' => $line->sales_order_id,
        ], ['converted_sales_order_id' => $line->sales_order_id, 'converted_at' => now()]);
    }

    public function expireDue(int $limit = 200): int
    {
        $ids = CustomerProductionRequestStockHold::query()->where('status', 'held')->where('expires_at', '<=', now())
            ->orderBy('expires_at')->limit($limit)->pluck('customer_production_request_id')->unique();
        $count = 0;
        foreach ($ids as $requestId) {
            DB::transaction(function () use ($requestId, &$count): void {
                DB::statement('SELECT pg_advisory_xact_lock(20261009, 1)');
                $request = CustomerProductionRequest::query()->lockForUpdate()->find($requestId);
                if (! $request || $request->status !== Status::ReadyToOrder) return;
                $holds = CustomerProductionRequestStockHold::query()->where('customer_production_request_id', $requestId)
                    ->where('status', 'held')->where('expires_at', '<=', now())->orderBy('id')->lockForUpdate()->get();
                foreach ($holds as $hold) {
                    $remaining = $hold->untransferredQuantity();
                    if (bccomp($remaining, '0', 3) > 0) {
                        app(StockMovementService::class)->release((int) $hold->item_id, (int) $hold->location_id, $remaining);
                    }
                    $hold->forceFill(['status' => 'expired', 'released_quantity' => bcadd((string) $hold->released_quantity, $remaining, 3), 'released_at' => now()])->save();
                }
                if ($holds->isNotEmpty()) {
                    $service = app(CustomerProductionRequestService::class);
                    $expired = $service->transition($request, Status::Expired, 'ready_hold_expired', 'system', null, ['hold_expired_at' => now()->toIso8601String()], ['expired_at' => now()]);
                    $service->notifyPortal($expired, 'customer_production_request.expired', 'Production hold expired',
                        'The 48-hour hold has expired. Any remaining stock is now available for all customers to order.');
                    $count++;
                }
            }, 3);
        }
        return $count;
    }
}
