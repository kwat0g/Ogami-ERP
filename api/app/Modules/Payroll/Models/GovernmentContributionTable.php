<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Models;

use App\Common\Exceptions\BusinessRuleException;
use App\Common\Traits\HasAuditLog;
use App\Common\Traits\HasHashId;
use App\Modules\Payroll\Enums\ContributionAgency;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class GovernmentContributionTable extends Model
{
    use HasAuditLog, HasFactory, HasHashId, SoftDeletes;

    protected $table = 'government_contribution_tables';

    protected $fillable = [
        'agency',
        'bracket_min',
        'bracket_max',
        'ee_amount',
        'er_amount',
        'ec_amount',
        'effective_date',
        'schedule_version_id',
        'is_active',
    ];

    protected $casts = [
        'agency' => ContributionAgency::class,
        'bracket_min' => 'decimal:2',
        'bracket_max' => 'decimal:2',
        'ee_amount' => 'decimal:4',
        'er_amount' => 'decimal:4',
        'ec_amount' => 'decimal:4',
        'effective_date' => 'date',
        'is_active' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::updating(function (self $row): void {
            if ($row->schedule_version_id !== null) {
                throw new BusinessRuleException('Imported schedule rows are immutable. Import a corrected version instead.');
            }
        });

        static::deleting(function (self $row): void {
            if ($row->schedule_version_id !== null) {
                throw new BusinessRuleException('Imported schedule rows are immutable. Import a corrected version instead.');
            }
        });
    }

    // ─── Scopes ────────────────────────────────────────────────────

    public function scopeAgency(Builder $q, string|ContributionAgency $agency): Builder
    {
        $value = $agency instanceof ContributionAgency ? $agency->value : $agency;

        return $q->where('agency', $value);
    }

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('is_active', true);
    }

    public function scheduleVersion(): BelongsTo
    {
        return $this->belongsTo(GovernmentScheduleVersion::class, 'schedule_version_id');
    }
}
