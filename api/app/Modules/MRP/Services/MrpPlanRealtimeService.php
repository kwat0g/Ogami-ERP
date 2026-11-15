<?php

declare(strict_types=1);

namespace App\Modules\MRP\Services;

use App\Common\Services\OutboxService;
use App\Modules\Auth\Models\User;
use App\Modules\CRM\Models\SalesOrder;
use App\Modules\MRP\Events\MrpPlanChanged;
use App\Modules\MRP\Models\MrpPlan;
use App\Modules\Production\Models\WorkOrder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** Stages one durable plan refresh per row and transaction. */
final class MrpPlanRealtimeService
{
    public function stage(MrpPlan $plan, string $action): void
    {
        $version = $plan->fresh()?->updated_at?->format('Y-m-d\TH:i:s.uP')
            ?? $plan->updated_at?->format('Y-m-d\TH:i:s.uP')
            ?? now()->format('Y-m-d\TH:i:s.uP');
        $transactionId = DB::transactionLevel() > 0 && DB::connection()->getDriverName() === 'pgsql'
            ? (string) DB::scalar('select txid_current()')
            : (string) Str::uuid();
        $actor = auth()->user();
        [$sourceType, $sourceHashId] = match (true) {
            $plan->sales_order_id !== null => ['sales_order', SalesOrder::query()->find($plan->sales_order_id)?->hash_id],
            $plan->customer_production_request_id !== null => [
                'customer_production_request',
                \App\Modules\CRM\Models\CustomerProductionRequest::query()->find($plan->customer_production_request_id)?->hash_id,
            ],
            $plan->stock_production_work_order_id !== null => [
                'work_order',
                WorkOrder::query()->find($plan->stock_production_work_order_id)?->hash_id,
            ],
            default => [null, null],
        };
        if (! is_string($sourceHashId) || $sourceHashId === '') {
            throw new \RuntimeException("Cannot stage an MRP plan refresh for missing source on plan {$plan->mrp_plan_no}.");
        }

        app(OutboxService::class)->recordCoalesced(new MrpPlanChanged(
            planHashId: (string) $plan->hash_id,
            salesOrderHashId: $plan->sales_order_id !== null ? $sourceHashId : null,
            action: $action,
            version: $version,
            actorHashId: $actor instanceof User ? (string) $actor->hash_id : null,
            sourceEntityType: $plan->sales_order_id !== null ? null : $sourceType,
            sourceEntityHashId: $plan->sales_order_id !== null ? null : $sourceHashId,
        ), "mrp-plan-refresh:{$plan->getKey()}:{$transactionId}");
    }
}
