<?php

declare(strict_types=1);

namespace App\Modules\CRM\Services;

use App\Common\Exceptions\BusinessRuleException;
use App\Common\Services\NotificationService;
use App\Common\Support\HashIdFilter;
use App\Modules\Accounting\Models\Customer;
use App\Modules\Auth\Models\User;
use App\Modules\B2B\Models\CustomerPortalUser;
use App\Modules\CRM\Enums\CustomerOrderLimitIncreaseRequestStatus as Status;
use App\Modules\CRM\Models\CustomerOrderLimitIncreaseRequest as LimitRequest;
use App\Modules\CRM\Models\Product;
use App\Modules\CRM\Services\ProductRealtimeService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

final class CustomerOrderLimitIncreaseRequestService
{
    public function createForPortal(CustomerPortalUser $portalUser, array $data): LimitRequest
    {
        $productId = HashIdFilter::decode((string) $data['product_id'], Product::class);
        if ($productId === null) {
            throw ValidationException::withMessages(['product_id' => ['Choose a product in your customer catalog.']]);
        }
        if (! Schema::hasColumns('customer_product_access', ['minimum_order_quantity', 'maximum_order_quantity'])) {
            throw new BusinessRuleException('Customer order limits are not available yet. Contact Ogami Sales.');
        }

        return DB::transaction(function () use ($portalUser, $productId, $data): LimitRequest {
            $customer = Customer::query()->lockForUpdate()->findOrFail($portalUser->customer_id);
            $product = Product::query()->active()->lockForUpdate()->find($productId);
            if (! $customer->is_active || ! $product || ! $product->isAvailableToCustomer($customer->id)) {
                throw new BusinessRuleException('This product is not available under your customer catalog. Contact Ogami Sales.');
            }

            $access = DB::table('customer_product_access')
                ->where('customer_id', $customer->id)
                ->where('product_id', $product->id)
                ->lockForUpdate()
                ->first(['minimum_order_quantity', 'maximum_order_quantity']);
            if (! $access || $access->maximum_order_quantity === null) {
                throw ValidationException::withMessages(['product_id' => ['This product has no maximum order quantity to increase. Contact Ogami Sales if you need help.']]);
            }

            $currentMaximum = (string) $access->maximum_order_quantity;
            $requestedMaximum = (string) $data['requested_maximum_quantity'];
            if (bccomp($requestedMaximum, $currentMaximum, 3) <= 0) {
                throw ValidationException::withMessages(['requested_maximum_quantity' => ["Enter a quantity greater than the current maximum of {$currentMaximum}."]]);
            }
            if (bccomp($requestedMaximum, (string) ($access->minimum_order_quantity ?? '1.000'), 3) < 0) {
                throw ValidationException::withMessages(['requested_maximum_quantity' => ['The requested maximum must be at least the account minimum order quantity.']]);
            }
            if (LimitRequest::query()->where('customer_id', $customer->id)->where('product_id', $product->id)
                ->where('status', Status::Requested->value)->lockForUpdate()->exists()) {
                throw new BusinessRuleException('A limit increase request for this product is already awaiting Sales review.');
            }

            $request = LimitRequest::create([
                'customer_id' => $customer->id,
                'product_id' => $product->id,
                'portal_user_id' => $portalUser->id,
                'current_maximum_quantity' => $currentMaximum,
                'requested_maximum_quantity' => $requestedMaximum,
                'status' => Status::Requested,
                'customer_notes' => isset($data['customer_notes']) ? trim((string) $data['customer_notes']) : null,
                'version' => 1,
            ]);

            $audience = app(NotificationService::class)->usersWithPermission('accounting.customers.manage');
            if ($audience->isNotEmpty()) {
                app(NotificationService::class)->sendInApp($audience, 'customer_order_limit_increase.requested', [
                    'title' => 'Customer requested a higher order limit',
                    'message' => "{$customer->name} requested a maximum of {$requestedMaximum} {$product->unit_of_measure} per order for {$product->part_number}.",
                    'link_to' => '/crm/order-limit-increase-requests',
                    'entity_type' => 'customer_order_limit_increase_request',
                    'entity_id' => $request->hash_id,
                ]);
            }

            return $request->load(['product', 'customer']);
        }, 3);
    }

    public function portalList(int $customerId, int $perPage = 20): LengthAwarePaginator
    {
        return LimitRequest::query()->where('customer_id', $customerId)
            ->with('product:id,part_number,name,unit_of_measure')
            ->latest('id')->paginate(min(max($perPage, 1), 100));
    }

    public function internalList(?string $status, int $perPage = 30): LengthAwarePaginator
    {
        return LimitRequest::query()->with(['product:id,part_number,name,unit_of_measure', 'customer:id,name', 'portalUser:id,name'])
            ->when($status !== null, fn ($query) => $query->where('status', $status))
            ->orderByRaw("CASE status WHEN 'requested' THEN 0 ELSE 1 END")
            ->latest('id')->paginate(min(max($perPage, 1), 100));
    }

    public function cancelForPortal(int $customerId, LimitRequest $request, int $version): LimitRequest
    {
        return DB::transaction(function () use ($customerId, $request, $version): LimitRequest {
            $locked = LimitRequest::query()->lockForUpdate()->findOrFail($request->id);
            if ((int) $locked->customer_id !== $customerId) {
                abort(404);
            }
            if ($locked->status !== Status::Requested || $locked->version !== $version) {
                throw new BusinessRuleException('This request is no longer awaiting review. Refresh to see its current status.');
            }
            $locked->forceFill(['status' => Status::Cancelled, 'version' => $locked->version + 1])->save();

            return $locked->load('product');
        }, 3);
    }

    public function decide(LimitRequest $request, User $actor, array $data): LimitRequest
    {
        abort_unless($actor->hasPermission('accounting.customers.manage'), 403);

        return DB::transaction(function () use ($request, $actor, $data): LimitRequest {
            $locked = LimitRequest::query()->lockForUpdate()->findOrFail($request->id);
            if ($locked->status !== Status::Requested || $locked->version !== (int) $data['version']) {
                throw new BusinessRuleException('This request changed or is no longer awaiting review. Refresh and review the current status.');
            }

            $customer = Customer::query()->lockForUpdate()->findOrFail($locked->customer_id);
            $product = Product::query()->active()->lockForUpdate()->find($locked->product_id);
            $access = DB::table('customer_product_access')
                ->where('customer_id', $locked->customer_id)->where('product_id', $locked->product_id)
                ->lockForUpdate()->first(['minimum_order_quantity', 'maximum_order_quantity']);
            if (! $product || ! $access || $access->maximum_order_quantity === null
                || bccomp((string) $access->maximum_order_quantity, (string) $locked->current_maximum_quantity, 3) !== 0) {
                throw new BusinessRuleException('The customer maximum changed after this request was submitted. Refresh before deciding; the new request cannot overwrite that change.');
            }

            $decision = $data['decision'];
            if ($decision === 'approve') {
                if (! $customer->is_active || ! $product->isAvailableToCustomer($customer->id)) {
                    throw new BusinessRuleException('The customer or product is no longer active in the customer catalog.');
                }
                if (bccomp((string) $locked->requested_maximum_quantity, (string) $access->minimum_order_quantity, 3) < 0) {
                    throw new BusinessRuleException('The requested maximum is below the current minimum order quantity. Update the request before approving.');
                }
                DB::table('customer_product_access')
                    ->where('customer_id', $locked->customer_id)->where('product_id', $locked->product_id)
                    ->update(['maximum_order_quantity' => $locked->requested_maximum_quantity]);
            }

            $locked->forceFill([
                'status' => $decision === 'approve' ? Status::Approved : Status::Rejected,
                'decision_notes' => trim((string) $data['notes']),
                'reviewed_by' => $actor->id,
                'reviewed_at' => now(),
                'version' => $locked->version + 1,
            ])->save();

            if ($decision === 'approve') {
                app(ProductRealtimeService::class)->stage($product, 'updated', $actor);
            }
            $this->notifyPortal($locked, $decision);

            return $locked->load(['product', 'customer']);
        }, 3);
    }

    private function notifyPortal(LimitRequest $request, string $decision): void
    {
        $portalUser = $request->portalUser;
        if (! $portalUser) {
            return;
        }
        $quantity = $decision === 'approve'
            ? (string) $request->requested_maximum_quantity
            : (string) $request->current_maximum_quantity;
        app(NotificationService::class)->sendPortalInApp([$portalUser], 'customer_order_limit_increase.'.$decision, [
            'title' => $decision === 'approve' ? 'Order limit increase approved' : 'Order limit increase declined',
            'message' => $decision === 'approve'
                ? "Sales approved a maximum of {$quantity} {$request->product->unit_of_measure} per order for {$request->product->part_number}."
                : "Sales declined the requested increase for {$request->product->part_number}. {$request->decision_notes}",
            'link_to' => '/portal/customer/order-limit-requests',
            'entity_type' => 'customer_order_limit_increase_request',
            'entity_id' => $request->hash_id,
        ], 'customer-order-limit-increase:'.$request->id.':'.$decision);
    }
}
