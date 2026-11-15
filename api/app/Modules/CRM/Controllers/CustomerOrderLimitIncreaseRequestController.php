<?php

declare(strict_types=1);

namespace App\Modules\CRM\Controllers;

use App\Modules\B2B\Models\CustomerPortalUser;
use App\Modules\B2B\Requests\Customer\StoreCustomerOrderLimitIncreaseRequest as StorePortalRequest;
use App\Modules\CRM\Models\CustomerOrderLimitIncreaseRequest as LimitRequest;
use App\Modules\CRM\Requests\DecideCustomerOrderLimitIncreaseRequest;
use App\Modules\CRM\Resources\CustomerOrderLimitIncreaseRequestResource;
use App\Modules\CRM\Services\CustomerOrderLimitIncreaseRequestService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;

final class CustomerOrderLimitIncreaseRequestController extends Controller
{
    public function __construct(private readonly CustomerOrderLimitIncreaseRequestService $service) {}

    public function portalIndex(Request $request): AnonymousResourceCollection
    {
        $filters = $request->validate(['per_page' => ['sometimes', 'integer', 'between:1,100']]);
        return CustomerOrderLimitIncreaseRequestResource::collection(
            $this->service->portalList((int) $this->portalUser($request)->customer_id, (int) ($filters['per_page'] ?? 20)),
        );
    }

    public function portalStore(StorePortalRequest $request): CustomerOrderLimitIncreaseRequestResource
    {
        return new CustomerOrderLimitIncreaseRequestResource(
            $this->service->createForPortal($this->portalUser($request), $request->validated()),
        );
    }

    public function portalCancel(Request $request, LimitRequest $orderLimitIncreaseRequest): CustomerOrderLimitIncreaseRequestResource
    {
        $data = $request->validate(['version' => ['required', 'integer', 'min:1']]);
        return new CustomerOrderLimitIncreaseRequestResource(
            $this->service->cancelForPortal((int) $this->portalUser($request)->customer_id, $orderLimitIncreaseRequest, (int) $data['version']),
        );
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $filters = $request->validate([
            'status' => ['sometimes', 'nullable', 'in:requested,approved,rejected,cancelled'],
            'per_page' => ['sometimes', 'integer', 'between:1,100'],
        ]);
        return CustomerOrderLimitIncreaseRequestResource::collection(
            $this->service->internalList($filters['status'] ?? null, (int) ($filters['per_page'] ?? 30)),
        );
    }

    public function decide(DecideCustomerOrderLimitIncreaseRequest $request, LimitRequest $orderLimitIncreaseRequest): CustomerOrderLimitIncreaseRequestResource
    {
        $updated = $this->service->decide($orderLimitIncreaseRequest, $request->user(), $request->validated());
        return new CustomerOrderLimitIncreaseRequestResource($updated);
    }

    private function portalUser(Request $request): CustomerPortalUser
    {
        /** @var CustomerPortalUser $user */
        $user = $request->user('customer_portal');
        return $user;
    }
}
