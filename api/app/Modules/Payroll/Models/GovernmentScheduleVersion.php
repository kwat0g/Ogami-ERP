<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Models;

use App\Common\Exceptions\BusinessRuleException;
use App\Common\Traits\HasAuditLog;
use App\Common\Traits\HasHashId;
use App\Modules\Auth\Models\User;
use App\Modules\Payroll\Enums\ContributionAgency;
use App\Modules\Payroll\Enums\GovernmentScheduleVersionStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GovernmentScheduleVersion extends Model
{
    use HasAuditLog, HasHashId;

    protected $fillable = [
        'agency',
        'effective_date',
        'version_number',
        'status',
        'source_title',
        'source_url',
        'source_file_name',
        'source_file_sha256',
        'row_count',
        'imported_by',
        'imported_at',
        'published_by',
        'published_at',
        'completeness_confirmed',
        'completeness_confirmed_at',
        'superseded_by_id',
        'superseded_at',
    ];

    protected function casts(): array
    {
        return [
            'agency' => ContributionAgency::class,
            'effective_date' => 'date',
            'status' => GovernmentScheduleVersionStatus::class,
            'version_number' => 'integer',
            'row_count' => 'integer',
            'completeness_confirmed' => 'boolean',
            'imported_at' => 'datetime',
            'published_at' => 'datetime',
            'completeness_confirmed_at' => 'datetime',
            'superseded_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (self $version): void {
            $from = (string) $version->getRawOriginal('status');
            $to = $version->status?->value;
            $allowed = match ([$from, $to]) {
                [GovernmentScheduleVersionStatus::Draft->value, GovernmentScheduleVersionStatus::Published->value] => [
                    'status', 'published_by', 'published_at', 'completeness_confirmed',
                    'completeness_confirmed_at', 'updated_at',
                ],
                [GovernmentScheduleVersionStatus::Published->value, GovernmentScheduleVersionStatus::Superseded->value] => [
                    'status', 'superseded_by_id', 'superseded_at', 'updated_at',
                ],
                default => [],
            };

            $unexpected = array_diff(array_keys($version->getDirty()), $allowed);
            if ($allowed === [] || $unexpected !== []) {
                throw new BusinessRuleException('Schedule version evidence is immutable. Create a new version to correct a schedule.');
            }
        });

        static::deleting(static function (): never {
            throw new BusinessRuleException('Schedule version evidence cannot be deleted.');
        });
    }

    public function contributionRows(): HasMany
    {
        return $this->hasMany(GovernmentContributionTable::class, 'schedule_version_id');
    }

    public function importer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'imported_by');
    }

    public function publisher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'published_by');
    }

    public function supersededBy(): BelongsTo
    {
        return $this->belongsTo(self::class, 'superseded_by_id');
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', GovernmentScheduleVersionStatus::Published->value);
    }
}
