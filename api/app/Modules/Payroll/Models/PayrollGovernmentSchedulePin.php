<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Models;

use App\Common\Exceptions\BusinessRuleException;
use App\Modules\Payroll\Enums\ContributionAgency;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayrollGovernmentSchedulePin extends Model
{
    public $timestamps = false;

    protected $table = 'payroll_period_government_schedule_versions';

    protected $fillable = [
        'payroll_period_id',
        'agency',
        'government_schedule_version_id',
        'effective_date',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'agency' => ContributionAgency::class,
            'effective_date' => 'date',
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(static function (): never {
            throw new BusinessRuleException('Payroll statutory schedule pins are immutable.');
        });
        static::deleting(static function (): never {
            throw new BusinessRuleException('Payroll statutory schedule pins are immutable.');
        });
    }

    public function period(): BelongsTo
    {
        return $this->belongsTo(PayrollPeriod::class, 'payroll_period_id');
    }

    public function scheduleVersion(): BelongsTo
    {
        return $this->belongsTo(GovernmentScheduleVersion::class, 'government_schedule_version_id');
    }
}
