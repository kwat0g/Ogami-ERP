<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Models;

use App\Modules\HR\Models\Clearance;
use App\Modules\Loans\Models\EmployeeLoan;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class PayrollLoanRecoveryAllocation extends Model
{
    protected $fillable = [
        'payroll_id',
        'clearance_id',
        'allocation_run_id',
        'employee_loan_id',
        'requested_amount',
        'withheld_amount',
        'deferred_amount',
        'deferred_reason',
    ];

    protected $casts = [
        'requested_amount' => 'decimal:2',
        'withheld_amount' => 'decimal:2',
        'deferred_amount' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        static::updating(static function (): never {
            throw new LogicException('Loan recovery allocations are immutable. Recompute the source payroll or final pay.');
        });

        static::deleting(static function (): never {
            throw new LogicException('Loan recovery allocations are immutable. Recompute the source payroll or final pay.');
        });
    }

    public function payroll(): BelongsTo
    {
        return $this->belongsTo(Payroll::class);
    }

    public function clearance(): BelongsTo
    {
        return $this->belongsTo(Clearance::class);
    }

    public function loan(): BelongsTo
    {
        return $this->belongsTo(EmployeeLoan::class, 'employee_loan_id');
    }
}
