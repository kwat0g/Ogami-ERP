<?php

declare(strict_types=1);

namespace App\Modules\Quality\Models;

use App\Common\Traits\HasHashId;
use App\Modules\Auth\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InspectionDefectSummary extends Model
{
    use HasHashId;

    protected $fillable = [
        'inspection_id', 'category', 'other_category', 'severity', 'affected_quantity',
        'description', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'affected_quantity' => 'decimal:3',
    ];

    public function inspection(): BelongsTo
    {
        return $this->belongsTo(Inspection::class);
    }

    public function photos(): HasMany
    {
        return $this->hasMany(InspectionDefectSummaryPhoto::class)->orderBy('id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
