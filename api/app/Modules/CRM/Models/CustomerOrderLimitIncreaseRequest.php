<?php

declare(strict_types=1);

namespace App\Modules\CRM\Models;

use App\Common\Traits\HasAuditLog;
use App\Common\Traits\HasHashId;
use App\Modules\Accounting\Models\Customer;
use App\Modules\Auth\Models\User;
use App\Modules\B2B\Models\CustomerPortalUser;
use App\Modules\CRM\Enums\CustomerOrderLimitIncreaseRequestStatus as Status;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerOrderLimitIncreaseRequest extends Model
{
    use HasAuditLog, HasHashId;

    protected $fillable = [
        'customer_id', 'product_id', 'portal_user_id', 'current_maximum_quantity',
        'requested_maximum_quantity', 'status', 'customer_notes', 'decision_notes',
        'reviewed_by', 'reviewed_at', 'version',
    ];

    protected $casts = [
        'status' => Status::class,
        'current_maximum_quantity' => 'decimal:3',
        'requested_maximum_quantity' => 'decimal:3',
        'reviewed_at' => 'datetime',
        'version' => 'integer',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class)->withTrashed();
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }

    public function portalUser(): BelongsTo
    {
        return $this->belongsTo(CustomerPortalUser::class, 'portal_user_id')->withTrashed();
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
