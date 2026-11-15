<?php

declare(strict_types=1);

namespace App\Modules\CRM\Models;

use App\Modules\Inventory\Models\Item;
use App\Modules\Inventory\Models\WarehouseLocation;
use App\Modules\Production\Models\WorkOrderOutput;
use App\Modules\Quality\Models\Inspection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerProductionRequestStockHold extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'quantity' => 'decimal:3', 'released_quantity' => 'decimal:3', 'transferred_quantity' => 'decimal:3',
        'expiry_date' => 'date', 'expires_at' => 'datetime', 'released_at' => 'datetime', 'transferred_at' => 'datetime',
    ];

    public function request(): BelongsTo
    {
        return $this->belongsTo(CustomerProductionRequest::class, 'customer_production_request_id');
    }

    public function output(): BelongsTo
    {
        return $this->belongsTo(WorkOrderOutput::class, 'work_order_output_id');
    }

    public function inspection(): BelongsTo
    {
        return $this->belongsTo(Inspection::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(WarehouseLocation::class, 'location_id');
    }

    public function untransferredQuantity(): string
    {
        return bcsub(bcsub((string) $this->quantity, (string) $this->released_quantity, 3), (string) $this->transferred_quantity, 3);
    }
}
