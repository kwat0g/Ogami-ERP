<?php

declare(strict_types=1);

namespace App\Modules\CRM\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerProductionRequestEvent extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'customer_production_request_id', 'from_status', 'to_status',
        'actor_type', 'actor_id', 'action', 'details', 'created_at',
    ];

    protected $casts = [
        'details' => 'array',
        'created_at' => 'datetime',
    ];

    public function request(): BelongsTo
    {
        return $this->belongsTo(CustomerProductionRequest::class, 'customer_production_request_id');
    }
}
