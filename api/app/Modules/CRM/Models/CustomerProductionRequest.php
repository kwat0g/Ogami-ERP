<?php

declare(strict_types=1);

namespace App\Modules\CRM\Models;

use App\Common\Traits\HasAuditLog;
use App\Common\Traits\HasHashId;
use App\Modules\Accounting\Models\Customer;
use App\Modules\B2B\Models\CustomerPortalUser;
use App\Modules\CRM\Enums\CustomerProductionRequestStatus;
use App\Modules\MRP\Models\MrpPlan;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class CustomerProductionRequest extends Model
{
    use HasFactory, HasHashId, HasAuditLog;

    protected $fillable = [
        'request_number', 'customer_id', 'portal_user_id', 'product_id',
        'requested_quantity', 'requested_need_by_date', 'offered_ready_date', 'status',
        'customer_notes', 'decision_notes', 'request_key', 'payload_fingerprint',
        'planning_request_key', 'converted_sales_order_id', 'offered_at', 'accepted_at',
        'ready_notified_at', 'hold_expires_at', 'converted_at', 'declined_at', 'cancelled_at', 'expired_at',
    ];

    protected $casts = [
        'status' => CustomerProductionRequestStatus::class,
        'requested_quantity' => 'integer',
        'requested_need_by_date' => 'date',
        'offered_ready_date' => 'date',
        'offered_at' => 'datetime',
        'accepted_at' => 'datetime',
        'ready_notified_at' => 'datetime',
        'hold_expires_at' => 'datetime',
        'converted_at' => 'datetime',
        'declined_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'expired_at' => 'datetime',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function portalUser(): BelongsTo
    {
        return $this->belongsTo(CustomerPortalUser::class, 'portal_user_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function convertedSalesOrder(): BelongsTo
    {
        return $this->belongsTo(SalesOrder::class, 'converted_sales_order_id');
    }

    public function mrpPlans(): HasMany
    {
        return $this->hasMany(MrpPlan::class, 'customer_production_request_id');
    }

    public function activeMrpPlan(): HasOne
    {
        return $this->hasOne(MrpPlan::class, 'customer_production_request_id')
            ->where('status', 'active')->orderByDesc('version');
    }

    public function events(): HasMany
    {
        return $this->hasMany(CustomerProductionRequestEvent::class)->orderBy('created_at')->orderBy('id');
    }

    public function stockHolds(): HasMany
    {
        return $this->hasMany(CustomerProductionRequestStockHold::class);
    }
}
