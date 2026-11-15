<?php

declare(strict_types=1);

namespace App\Modules\CRM\Services;

use App\Common\Exceptions\BusinessRuleException;
use App\Common\Services\DocumentSequenceService;
use App\Common\Services\NotificationService;
use App\Common\Support\HashIdFilter;
use App\Modules\Accounting\Models\Customer;
use App\Modules\Auth\Models\User;
use App\Modules\B2B\Models\CustomerPortalUser;
use App\Modules\CRM\Enums\CustomerProductionRequestStatus as Status;
use App\Modules\CRM\Models\CustomerProductionRequest;
use App\Modules\CRM\Models\CustomerProductionRequestEvent;
use App\Modules\CRM\Models\Product;
use App\Modules\CRM\Services\CustomerPortalOrderAvailabilityService;
use App\Modules\MRP\Enums\MrpPlanStatus;
use App\Modules\MRP\Models\MrpPlan;
use App\Modules\Production\Models\WorkOrder;
use App\Modules\Production\Services\StockProductionPlanningService;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class CustomerProductionRequestService
{
    public function createForPortal(int $customerId, CustomerPortalUser $portalUser, array $data): array
    {
        $productId = HashIdFilter::decode((string) $data['product_id'], Product::class);
        if ($productId === null) {
            throw ValidationException::withMessages(['product_id' => ['Choose an available catalog product.']]);
        }
        $key = strtolower((string) $data['request_key']);
        $totalNeeded = (int) $data['total_needed_quantity'];
        $needBy = Carbon::parse($data['requested_need_by_date'])->toDateString();
        $notes = isset($data['customer_notes']) ? trim((string) $data['customer_notes']) : null;

        return DB::transaction(function () use ($customerId, $portalUser, $productId, $key, $totalNeeded, $needBy, $notes): array {
            DB::statement('SELECT pg_advisory_xact_lock(20261009, 1)');
            $fingerprint = hash('sha256', json_encode([
                'customer_id' => $customerId, 'portal_user_id' => $portalUser->id, 'product_id' => $productId,
                'total_needed_quantity' => $totalNeeded, 'requested_need_by_date' => $needBy, 'customer_notes' => $notes,
            ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
            $existing = CustomerProductionRequest::query()->where('customer_id', $customerId)->where('request_key', $key)->lockForUpdate()->first();
            if ($existing) {
                if (! hash_equals((string) $existing->payload_fingerprint, $fingerprint)) {
                    throw new BusinessRuleException('This request key was already used for a different production request.');
                }

                return ['request' => $this->loadPortal($existing), 'replayed' => true];
            }

            $customer = Customer::query()->lockForUpdate()->find($customerId);
            $product = Product::query()->active()->lockForUpdate()->find($productId);
            if (! $customer || ! $customer->is_active || ! $product || ! $product->isAvailableToCustomer($customerId)) {
                throw new BusinessRuleException('This product is not available under your customer catalog. Contact Ogami Sales.');
            }
            if ($totalNeeded < 1 || Carbon::parse($needBy)->lt(today())) {
                throw ValidationException::withMessages(['total_needed_quantity' => ['Enter a positive whole-number quantity and a need-by date of today or later.']]);
            }

            $availability = app(CustomerPortalOrderAvailabilityService::class)->forProduct($customer, $product);
            if ($availability['availability_mode'] !== 'stock_limited') {
                throw new BusinessRuleException('This product is already handled as make-to-order. Submit it through the ordinary order flow.');
            }
            $availableWhole = (int) floor((float) $availability['available_quantity']);
            if ($totalNeeded <= $availableWhole) {
                throw ValidationException::withMessages(['total_needed_quantity' => ['This quantity is currently available to order. Use Place an Order instead.']]);
            }
            $quantity = $totalNeeded - $availableWhole;
            if ($quantity > 5000) {
                throw ValidationException::withMessages(['total_needed_quantity' => ['The production request is limited to 5,000 pieces beyond current available stock.']]);
            }
            if (bccomp((string) $quantity, (string) $availability['minimum_order_quantity'], 3) < 0) {
                throw ValidationException::withMessages(['total_needed_quantity' => ["The remaining production quantity must be at least {$availability['minimum_order_quantity']} units under your customer agreement."]]);
            }
            if ($availability['maximum_order_quantity'] !== null
                && bccomp((string) $quantity, (string) $availability['maximum_order_quantity'], 3) > 0) {
                throw ValidationException::withMessages(['total_needed_quantity' => ["The remaining production quantity cannot exceed {$availability['maximum_order_quantity']} units under your customer agreement."]]);
            }

            try {
                $request = CustomerProductionRequest::create([
                    'request_number' => app(DocumentSequenceService::class)->generate('customer_production_request'),
                    'customer_id' => $customerId, 'portal_user_id' => $portalUser->id, 'product_id' => $product->id,
                    'requested_quantity' => $quantity, 'requested_need_by_date' => $needBy, 'customer_notes' => $notes,
                    'status' => Status::Requested->value, 'request_key' => $key, 'payload_fingerprint' => $fingerprint,
                ]);
            } catch (\Illuminate\Database\QueryException $error) {
                if (str_contains($error->getMessage(), 'customer_production_request_one_open_per_customer_product')) {
                    throw new BusinessRuleException('You already have an open production request for this product. View it in Production requests.');
                }
                throw $error;
            }
            $this->record($request, null, Status::Requested, 'submitted', 'customer_portal', (int) $portalUser->id, [
                'portal_user_id' => $portalUser->hash_id, 'total_needed_quantity' => $totalNeeded,
                'available_at_submission' => $availableWhole, 'requested_quantity' => $quantity, 'requested_need_by_date' => $needBy,
            ]);
            $this->notifySalesReview($request, $portalUser);

            return ['request' => $this->loadPortal($request), 'replayed' => false];
        }, 3);
    }

    public function portalList(int $customerId, array $filters): LengthAwarePaginator
    {
        return CustomerProductionRequest::query()->where('customer_id', $customerId)
            ->with(['product:id,part_number,name,unit_of_measure', 'convertedSalesOrder:id,so_number'])
            ->when(! empty($filters['status']), fn ($query) => $query->where('status', $filters['status']))
            ->orderByDesc('created_at')->paginate(min((int) ($filters['per_page'] ?? 20), 100));
    }

    public function portalShow(int $customerId, CustomerProductionRequest $request): CustomerProductionRequest
    {
        if ((int) $request->customer_id !== $customerId) {
            abort(404);
        }

        return $this->loadPortal($request);
    }

    public function portalRespond(int $customerId, CustomerPortalUser $portalUser, CustomerProductionRequest $request, string $decision, ?string $reason = null): CustomerProductionRequest
    {
        return DB::transaction(function () use ($customerId, $portalUser, $request, $decision, $reason): CustomerProductionRequest {
            DB::statement('SELECT pg_advisory_xact_lock(20261009, 1)');
            $locked = CustomerProductionRequest::query()->lockForUpdate()->findOrFail($request->id);
            if ((int) $locked->customer_id !== $customerId) {
                abort(404);
            }
            if ($decision === 'cancel') {
                if (! in_array($locked->status, [Status::Requested, Status::Planning, Status::DateOffered], true)) {
                    throw new BusinessRuleException('This request can no longer be cancelled online. Contact Ogami Sales for help with its current production status.');
                }
                if ($locked->activeMrpPlan()->exists()) {
                    app(StockProductionPlanningService::class)->cancelCustomerRequestPlan($locked, $reason ?: 'Customer cancelled the production request.');
                }
                return $this->transition($locked, Status::Cancelled, 'customer_cancelled', 'customer_portal', (int) $portalUser->id,
                    ['reason' => $reason], ['cancelled_at' => now(), 'decision_notes' => $reason]);
            }
            if ($locked->status !== Status::DateOffered || ! in_array($decision, ['accept', 'decline'], true)) {
                throw new BusinessRuleException('This request has no current date offer to accept or decline.');
            }
            if ($decision === 'accept') {
                $accepted = $this->transition($locked, Status::Accepted, 'offer_accepted', 'customer_portal', (int) $portalUser->id,
                    [], ['accepted_at' => now()]);
                $audience = app(NotificationService::class)->usersWithPermission('production.wo.confirm')
                    ->filter(fn (User $user): bool => $user->hasPermission('production.wo.view'));
                if ($audience->isNotEmpty()) {
                    app(NotificationService::class)->sendForSubject($accepted, $audience, 'customer_production_request.accepted', [
                        'title' => $accepted->request_number.' accepted',
                        'message' => "{$portalUser->name} accepted the ready-date offer. PPC can review and confirm the linked planned work order.",
                        'link_to' => '/crm/customer-production-requests/'.$accepted->hash_id,
                        'entity_type' => 'customer_production_request', 'entity_id' => $accepted->hash_id,
                    ]);
                }
                return $accepted;
            }

            app(StockProductionPlanningService::class)->cancelCustomerRequestPlan($locked, 'Customer declined the offered ready date.');

            return $this->transition($locked, Status::Declined, 'offer_declined', 'customer_portal', (int) $portalUser->id,
                ['reason' => $reason], ['declined_at' => now(), 'decision_notes' => $reason]);
        }, 3);
    }

    public function internalList(array $filters): LengthAwarePaginator
    {
        return CustomerProductionRequest::query()->with([
            'customer:id,name', 'product:id,part_number,name,unit_of_measure', 'activeMrpPlan.workOrders',
        ])->when(! empty($filters['status']), fn ($query) => $query->where('status', $filters['status']))
            ->orderByRaw("CASE status WHEN 'requested' THEN 0 WHEN 'planning' THEN 1 WHEN 'date_offered' THEN 2 WHEN 'accepted' THEN 3 WHEN 'in_production' THEN 4 ELSE 5 END")
            ->orderBy('created_at')->paginate(min((int) ($filters['per_page'] ?? 30), 100));
    }

    public function startPlanning(CustomerProductionRequest $request, User $actor, array $data): CustomerProductionRequest
    {
        return DB::transaction(function () use ($request, $actor, $data): CustomerProductionRequest {
            DB::statement('SELECT pg_advisory_xact_lock(20261009, 1)');
            $locked = CustomerProductionRequest::query()->lockForUpdate()->findOrFail($request->id);
            if ($locked->status !== Status::Requested) {
                throw new BusinessRuleException('Only a new customer request can enter production planning.');
            }
            $key = (string) Str::uuid();
            $locked->forceFill(['status' => Status::Planning, 'planning_request_key' => $key])->save();
            $this->record($locked, Status::Requested, Status::Planning, 'planning_started', 'user', (int) $actor->id, [
                'planned_start' => $data['planned_start'], 'planned_end' => $data['planned_end'],
            ]);

            $root = app(StockProductionPlanningService::class)->createForCustomerRequest([
                'product_id' => (int) $locked->product_id,
                'quantity_target' => (int) $locked->requested_quantity,
                'planned_start' => $data['planned_start'],
                'planned_end' => $data['planned_end'],
                'preferred_production_date' => $locked->requested_need_by_date?->toDateString(),
                'minimum_workers' => (int) ($data['minimum_workers'] ?? 1),
                'maximum_workers' => (int) ($data['maximum_workers'] ?? 4),
            ], $actor, $locked);
            $locked->setAttribute('root_work_order_hash_id', $root->hash_id);

            return $this->loadInternal($locked);
        }, 3);
    }

    public function offer(CustomerProductionRequest $request, User $actor, string $readyDate, ?string $notes = null): CustomerProductionRequest
    {
        return DB::transaction(function () use ($request, $actor, $readyDate, $notes): CustomerProductionRequest {
            DB::statement('SELECT pg_advisory_xact_lock(20261009, 1)');
            $locked = CustomerProductionRequest::query()->lockForUpdate()->findOrFail($request->id);
            if ($locked->status !== Status::Planning || ! $locked->activeMrpPlan()->exists()) {
                throw new BusinessRuleException('Start MRP planning before offering a ready date.');
            }
            $date = Carbon::parse($readyDate)->startOfDay();
            if ($date->lt(today())) {
                throw ValidationException::withMessages(['offered_ready_date' => ['Offer a date that is today or later.']]);
            }
            $offered = $this->transition($locked, Status::DateOffered, 'ready_date_offered', 'user', (int) $actor->id,
                ['offered_ready_date' => $date->toDateString(), 'notes' => $notes], [
                    'offered_ready_date' => $date->toDateString(), 'offered_at' => now(), 'decision_notes' => $notes,
                ]);
            $this->notifyPortal($offered, 'customer_production_request.date_offered', 'Production date offered',
                "Ogami can have {$offered->requested_quantity} pieces of {$offered->product?->name} ready to order by {$date->format('M j, Y')}. Review the offer in Production requests.");
            return $offered;
        }, 3);
    }

    public function declineByStaff(CustomerProductionRequest $request, User $actor, string $reason): CustomerProductionRequest
    {
        return DB::transaction(function () use ($request, $actor, $reason): CustomerProductionRequest {
            DB::statement('SELECT pg_advisory_xact_lock(20261009, 1)');
            $locked = CustomerProductionRequest::query()->lockForUpdate()->findOrFail($request->id);
            if (! in_array($locked->status, [Status::Requested, Status::Planning, Status::DateOffered], true)) {
                throw new BusinessRuleException('This request can no longer be declined.');
            }
            if ($locked->activeMrpPlan()->exists()) {
                app(StockProductionPlanningService::class)->cancelCustomerRequestPlan($locked, $reason);
            }
            $declined = $this->transition($locked, Status::Declined, 'declined_by_sales', 'user', (int) $actor->id,
                ['reason' => $reason], ['declined_at' => now(), 'decision_notes' => $reason]);
            $this->notifyPortal($declined, 'customer_production_request.declined', 'Production request declined',
                'Ogami cannot proceed with this production request. Open the request for the reason or contact Sales.');
            return $declined;
        }, 3);
    }

    public function markWorkOrderConfirmed(WorkOrder $workOrder, ?User $actor): void
    {
        $plan = $workOrder->mrpPlan()->first();
        $requestId = $plan?->customer_production_request_id;
        if (! $requestId) return;
        $updated = DB::transaction(function () use ($requestId, $actor, $workOrder): ?CustomerProductionRequest {
            DB::statement('SELECT pg_advisory_xact_lock(20261009, 1)');
            $request = CustomerProductionRequest::query()->lockForUpdate()->find($requestId);
            if ($request?->status === Status::Accepted) {
                return $this->transition($request, Status::InProduction, 'production_confirmed', $actor ? 'user' : 'system', $actor?->id, [
                    'work_order_id' => $workOrder->hash_id,
                ]);
            }
            return null;
        }, 3);
        if ($updated) {
            try {
                $this->notifyPortal($updated, 'customer_production_request.in_production', 'Production has started',
                    "PPC confirmed production for {$updated->requested_quantity} pieces of {$updated->product?->name}. We will notify you when the finished goods are ready to order.");
            } catch (\Throwable $error) {
                report($error);
            }
        }
    }

    public function transition(CustomerProductionRequest $request, Status $to, string $action, string $actorType, ?int $actorId, array $details = [], array $attributes = []): CustomerProductionRequest
    {
        $from = $request->status;
        $request->forceFill(['status' => $to->value, ...$attributes])->save();
        $this->record($request, $from, $to, $action, $actorType, $actorId, $details);

        return $request->fresh();
    }

    public function notifyPortal(CustomerProductionRequest $request, string $type, string $title, string $message): void
    {
        $portalUser = $request->portalUser;
        if ($portalUser) {
            app(NotificationService::class)->sendPortalInApp([$portalUser], $type, [
                'title' => $title, 'message' => $message,
                'link_to' => '/portal/customer/production-requests/'.$request->hash_id,
                'entity_type' => 'customer_production_request', 'entity_id' => $request->hash_id,
            ], 'customer-production-request:'.$request->id.':'.$type);
        }
    }

    private function notifySalesReview(CustomerProductionRequest $request, CustomerPortalUser $portalUser): void
    {
        try {
            $notifications = app(NotificationService::class);
            $audience = $notifications->usersWithPermission('crm.sales_orders.confirm')
                ->filter(fn (User $user): bool => $user->hasPermission('crm.sales_orders.view'));
            if ($audience->isEmpty()) return;
            $notifications->sendForSubject($request, $audience, 'customer_production_request.submitted', [
                'title' => "Production request {$request->request_number}",
                'message' => "{$portalUser->name} requested {$request->requested_quantity} pieces of {$request->product?->name} by {$request->requested_need_by_date?->toDateString()}.",
                'link_to' => '/crm/customer-production-requests/'.$request->hash_id,
                'entity_type' => 'customer_production_request', 'entity_id' => $request->hash_id,
            ]);
        } catch (\Throwable $error) {
            report($error);
        }
    }

    private function loadPortal(CustomerProductionRequest $request): CustomerProductionRequest
    {
        return $request->load(['product:id,part_number,name,unit_of_measure', 'events', 'convertedSalesOrder:id,so_number']);
    }

    private function loadInternal(CustomerProductionRequest $request): CustomerProductionRequest
    {
        return $request->load(['customer:id,name', 'product:id,part_number,name,unit_of_measure', 'events', 'activeMrpPlan.workOrders']);
    }

    private function record(CustomerProductionRequest $request, ?Status $from, Status $to, string $action, string $actorType, ?int $actorId, array $details): void
    {
        CustomerProductionRequestEvent::create([
            'customer_production_request_id' => $request->id,
            'from_status' => $from?->value, 'to_status' => $to->value,
            'actor_type' => $actorType, 'actor_id' => $actorId,
            'action' => $action, 'details' => $details, 'created_at' => now(),
        ]);
    }
}
