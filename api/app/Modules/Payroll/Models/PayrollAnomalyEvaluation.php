<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Models;

use App\Modules\Auth\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class PayrollAnomalyEvaluation extends Model
{
    protected $fillable = [
        'payroll_period_id',
        'status',
        'input_fingerprint',
        'policy_version',
        'finding_count',
        'unresolved_finding_count',
        'failure_message',
        'evaluated_by',
        'evaluated_at',
    ];

    protected $casts = [
        'finding_count' => 'integer',
        'unresolved_finding_count' => 'integer',
        'evaluated_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::updating(static function (): never {
            throw new LogicException('Payroll anomaly evaluations are append-only evidence.');
        });

        static::deleting(static function (): never {
            throw new LogicException('Payroll anomaly evaluations are append-only evidence.');
        });
    }

    public function period(): BelongsTo
    {
        return $this->belongsTo(PayrollPeriod::class, 'payroll_period_id');
    }

    public function evaluator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'evaluated_by');
    }
}
