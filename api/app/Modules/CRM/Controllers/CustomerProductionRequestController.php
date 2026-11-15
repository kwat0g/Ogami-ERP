<?php

declare(strict_types=1);

namespace App\Modules\CRM\Controllers;

use App\Modules\CRM\Models\CustomerProductionRequest;
use App\Modules\CRM\Resources\CustomerProductionRequestResource;
use App\Modules\CRM\Services\CustomerProductionRequestService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CustomerProductionRequestController
{
    public function __construct(private readonly CustomerProductionRequestService $requests) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $filters = $request->validate([
            'status' => ['sometimes', 'nullable', 'in:requested,planning,date_offered,accepted,in_production,ready_to_order,converted,declined,cancelled,expired'],
            'per_page' => ['sometimes', 'integer', 'between:1,100'],
        ]);
        return CustomerProductionRequestResource::collection($this->requests->internalList($filters));
    }

    public function show(CustomerProductionRequest $customerProductionRequest): CustomerProductionRequestResource
    {
        return new CustomerProductionRequestResource($customerProductionRequest->load([
            'customer:id,name', 'product:id,part_number,name,unit_of_measure', 'events', 'activeMrpPlan.workOrders', 'convertedSalesOrder:id,so_number',
        ]));
    }

    public function plan(Request $request, CustomerProductionRequest $customerProductionRequest): CustomerProductionRequestResource
    {
        $data = $request->validate([
            'planned_start' => ['required', 'date', 'after_or_equal:today'],
            'planned_end' => ['required', 'date', 'after_or_equal:planned_start'],
            'minimum_workers' => ['sometimes', 'integer', 'between:1,4'],
            'maximum_workers' => ['sometimes', 'integer', 'between:1,4', 'gte:minimum_workers'],
        ]);
        return new CustomerProductionRequestResource($this->requests->startPlanning($customerProductionRequest, $request->user(), $data));
    }

    public function offer(Request $request, CustomerProductionRequest $customerProductionRequest): CustomerProductionRequestResource
    {
        $data = $request->validate([
            'offered_ready_date' => ['required', 'date', 'after_or_equal:today'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);
        return new CustomerProductionRequestResource($this->requests->offer(
            $customerProductionRequest, $request->user(), $data['offered_ready_date'], $data['notes'] ?? null,
        ));
    }

    public function decline(Request $request, CustomerProductionRequest $customerProductionRequest): CustomerProductionRequestResource
    {
        $data = $request->validate(['reason' => ['required', 'string', 'min:1', 'max:500']]);
        return new CustomerProductionRequestResource($this->requests->declineByStaff($customerProductionRequest, $request->user(), $data['reason']));
    }
}
