<?php

declare(strict_types=1);

namespace App\Modules\CRM\Resources;

use App\Modules\Auth\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CustomerOrderLimitIncreaseRequestResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $staffView = $request->user('sanctum') instanceof User;
        $status = $this->status?->value;

        return [
            'id' => $this->hash_id,
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
            'current_maximum_quantity' => (string) $this->current_maximum_quantity,
            'requested_maximum_quantity' => (string) $this->requested_maximum_quantity,
            'status' => $status,
            'status_label' => $this->status?->label(),
            'customer_notes' => $this->customer_notes,
            'decision_notes' => $this->decision_notes,
            'version' => $this->version,
            'created_at' => $this->created_at?->toIso8601String(),
            'reviewed_at' => $this->reviewed_at?->toIso8601String(),
            'available_actions' => [
                'can_cancel' => ! $staffView && $status === 'requested',
                'can_decide' => $staffView && $status === 'requested'
                    && $request->user('sanctum')?->hasPermission('accounting.customers.manage'),
            ],
        ];
    }
}
