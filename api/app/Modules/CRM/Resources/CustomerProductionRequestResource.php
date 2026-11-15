<?php

declare(strict_types=1);

namespace App\Modules\CRM\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CustomerProductionRequestResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $staffView = $request->user('sanctum') !== null;
        $plan = $this->relationLoaded('activeMrpPlan') ? $this->activeMrpPlan : null;

        return [
            'id' => $this->hash_id,
            'request_number' => $this->request_number,
            'status' => $this->status?->value,
            'status_label' => $this->status?->label(),
            'product' => $this->whenLoaded('product', fn () => $this->product ? [
                'id' => $this->product->hash_id,
                'part_number' => $this->product->part_number,
                'name' => $this->product->name,
                'unit_of_measure' => $this->product->unit_of_measure,
            ] : null),
            'customer' => $this->when($staffView && $this->relationLoaded('customer'), fn () => $this->customer ? [
                'id' => $this->customer->hash_id,
                'name' => $this->customer->name,
            ] : null),
            'requested_quantity' => (int) $this->requested_quantity,
            'requested_need_by_date' => $this->requested_need_by_date?->toDateString(),
            'offered_ready_date' => $this->offered_ready_date?->toDateString(),
            'customer_notes' => $this->customer_notes,
            'decision_notes' => $this->decision_notes,
            'created_at' => $this->created_at?->toIso8601String(),
            'offered_at' => $this->offered_at?->toIso8601String(),
            'accepted_at' => $this->accepted_at?->toIso8601String(),
            'ready_notified_at' => $this->ready_notified_at?->toIso8601String(),
            'hold_expires_at' => $this->hold_expires_at?->toIso8601String(),
            'converted_sales_order' => $this->when($this->convertedSalesOrder !== null, fn () => [
                'id' => $this->convertedSalesOrder->hash_id,
                'so_number' => $this->convertedSalesOrder->so_number,
            ]),
            'mrp_plan' => $this->when($staffView && $plan !== null, fn () => [
                'id' => $plan->hash_id,
                'mrp_plan_no' => $plan->mrp_plan_no,
                'status' => $plan->status?->value,
                'shortages_found' => (int) $plan->shortages_found,
                'diagnostics' => $plan->diagnostics ?? [],
            ]),
            'planning_work_order' => $this->when($staffView && $plan !== null, function () use ($plan): ?array {
                $root = $plan->relationLoaded('workOrders')
                    ? $plan->workOrders->first(fn ($workOrder) => $workOrder->parent_wo_id === null)
                    : null;
                return $root ? [
                    'id' => $root->hash_id,
                    'wo_number' => $root->wo_number,
                    'quantity_target' => (int) $root->quantity_target,
                    'status' => $root->status?->value,
                ] : null;
            }),
            'events' => $this->whenLoaded('events', fn () => $this->events->map(fn ($event) => [
                'from_status' => $event->from_status,
                'to_status' => $event->to_status,
                'action' => $event->action,
                'details' => $staffView ? $event->details : null,
                'created_at' => $event->created_at?->toIso8601String(),
            ])->values()),
            'capabilities' => [
                'can_cancel' => in_array($this->status?->value, ['requested', 'planning', 'date_offered'], true),
                'can_accept_offer' => $this->status?->value === 'date_offered',
                'can_decline_offer' => $this->status?->value === 'date_offered',
                'can_place_order' => $this->status?->value === 'ready_to_order',
            ],
        ];
    }
}
