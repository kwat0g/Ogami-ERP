<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Services;

use App\Common\Exceptions\BusinessRuleException;
use App\Common\Models\AuditLog;
use App\Common\Services\SettingsService;
use App\Modules\Attendance\Models\Attendance;
use App\Modules\Leave\Enums\LeaveRequestStatus;
use App\Modules\Leave\Models\LeaveRequest;
use App\Modules\Payroll\Enums\PayrollAdjustmentStatus;
use App\Modules\Payroll\Enums\PayrollAdjustmentType;
use App\Modules\Payroll\Enums\PayrollAnomalyType;
use App\Modules\Payroll\Enums\PayrollPeriodStatus;
use App\Modules\Payroll\Models\Payroll;
use App\Modules\Payroll\Models\PayrollAdjustment;
use App\Modules\Payroll\Models\PayrollAnomalyEvaluation;
use App\Modules\Payroll\Models\PayrollAnomalyFlag;
use App\Modules\Payroll\Models\PayrollPeriod;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Task A9 — Detect anomalies in a freshly computed payroll period.
 *
 * Regular payroll rules compare against the employee's previous regular payroll
 * of the same half. Annual 13th-month runs use only applicable payout checks:
 * zero pay and an excessive deduction ratio.
 *
 *   - Net pay change above the configured ratio → large_change
 *   - OT hours above the configured threshold  → excessive_ot
 *   - Deductions above the configured ratio     → high_deduction
 *   - No earlier regular payroll exists in this system → first_payroll
 *   - Net pay = 0                 → zero_pay
 *
 * Idempotent on (payroll_id, flag_type) via the unique index.
 */
class PayrollAnomalyService
{
    /** @var array{net_change_ratio:float,overtime_hours:float,deduction_ratio:float}|null */
    private ?array $regularPolicy = null;

    /** @var array{deduction_ratio:float}|null */
    private ?array $thirteenthMonthPolicy = null;

    public function __construct(
        private readonly SettingsService $settings,
        private readonly PayrollComputationFingerprintService $fingerprints,
    ) {}

    public function detect(PayrollPeriod $period): int
    {
        $this->regularPolicy = null;
        $this->thirteenthMonthPolicy = null;

        try {
            return DB::transaction(fn (): int => $this->evaluate($period));
        } catch (Throwable $exception) {
            $this->recordFailure($period, $exception);

            throw $exception;
        }
    }

    private function evaluate(PayrollPeriod $period): int
    {
        $created = 0;

        $payrolls = Payroll::query()
            ->where('payroll_period_id', $period->id)
            ->get();

        // Drop flags whose payroll row no longer exists. A recompute deletes and
        // recreates every row, and payroll_anomaly_flags cascades on payroll_id,
        // so orphans are normally impossible — but flags raised against rows that
        // were skipped this run would otherwise linger and block finalize()
        // forever with no way to reach them from the UI.
        $deleted = PayrollAnomalyFlag::query()
            ->where('payroll_period_id', $period->id)
            ->whereNotIn('payroll_id', $payrolls->pluck('id'))
            ->delete();

        foreach ($payrolls as $payroll) {
            $created += $this->detectForPayroll($payroll, $period);
        }

        if ($created > 0 || $deleted > 0) {
            app(PayrollAnomalyRealtimeService::class)->stage($period, 'updated');
        }

        $flags = PayrollAnomalyFlag::query()->where('payroll_period_id', $period->id);
        $findingCount = (clone $flags)->count();
        $unresolvedCount = (clone $flags)->where('is_resolved', false)->count();
        PayrollAnomalyEvaluation::query()->create([
            'payroll_period_id' => $period->id,
            'status' => 'passed',
            'input_fingerprint' => $this->fingerprints->fingerprint($period),
            'policy_version' => PayrollComputationFingerprintService::POLICY_VERSION,
            'finding_count' => $findingCount,
            'unresolved_finding_count' => $unresolvedCount,
            'evaluated_by' => $period->computed_by,
            'evaluated_at' => now(),
        ]);

        return $created;
    }

    public function assertCurrentEvaluation(PayrollPeriod $period, string $action = 'finalize'): PayrollAnomalyEvaluation
    {
        if (! Schema::hasTable('payroll_anomaly_evaluations')) {
            throw new BusinessRuleException("Cannot {$action}: payroll anomaly evaluation storage is unavailable. Apply the pending payroll migration, then recompute the period.");
        }

        $evaluation = PayrollAnomalyEvaluation::query()
            ->where('payroll_period_id', $period->id)
            ->orderByDesc('id')
            ->first();

        if (! $evaluation || $evaluation->status !== 'passed') {
            throw new BusinessRuleException("Cannot {$action}: a successful payroll anomaly evaluation is missing. Recompute the period.");
        }

        $currentFingerprint = $this->fingerprints->fingerprint($period);
        if ($evaluation->policy_version !== PayrollComputationFingerprintService::POLICY_VERSION
            || ! is_string($evaluation->input_fingerprint)
            || ! hash_equals($evaluation->input_fingerprint, $currentFingerprint)) {
            throw new BusinessRuleException("Cannot {$action}: the payroll anomaly evaluation is stale because payroll inputs or policy changed. Recompute the period.");
        }

        return $evaluation;
    }

    public function hasCurrentEvaluation(PayrollPeriod $period): bool
    {
        try {
            $this->assertCurrentEvaluation($period);

            return true;
        } catch (BusinessRuleException) {
            return false;
        }
    }

    private function recordFailure(PayrollPeriod $period, Throwable $exception): void
    {
        try {
            PayrollAnomalyEvaluation::query()->create([
                'payroll_period_id' => $period->id,
                'status' => 'failed',
                'input_fingerprint' => $this->fingerprints->fingerprint($period),
                'policy_version' => PayrollComputationFingerprintService::POLICY_VERSION,
                'failure_message' => mb_substr($exception->getMessage(), 0, 4000),
                'evaluated_by' => $period->computed_by,
                'evaluated_at' => now(),
            ]);
        } catch (Throwable $recordingFailure) {
            Log::error('Payroll anomaly evaluation failure could not be persisted.', [
                'payroll_period_id' => $period->id,
                'evaluation_error' => $exception->getMessage(),
                'recording_error' => $recordingFailure->getMessage(),
            ]);
        }
    }

    private function detectForPayroll(Payroll $payroll, PayrollPeriod $period): int
    {
        $policy = $this->policy($period->is_thirteenth_month);
        $created = 0;
        $employeeId = (int) $payroll->employee_id;

        // Compare against the employee's most recent payroll from a period of
        // the SAME half. Two fixes here:
        //
        //  - Order by the period's actual dates, not by payroll id. Recomputing
        //    a period deletes and recreates its rows with fresh (higher) ids, so
        //    id order stopped tracking chronology and a period could end up
        //    compared against a LATER one.
        //  - Match is_first_half. Government deductions are withheld on the
        //    first half only (PH semi-monthly convention), so first-vs-second
        //    half net pay always differs by roughly the whole gov contribution.
        //    Comparing across halves flagged 113 of 201 employees on a single
        //    period as "large change" — and because finalize() blocks on
        //    unresolved flags, HR had to hand-clear every one of them.
        $previous = null;
        $hasEarlierRegularPayroll = false;
        if (! $period->is_thirteenth_month) {
            $previous = Payroll::query()
                ->where('payrolls.employee_id', $employeeId)
                ->where('payrolls.id', '!=', $payroll->id)
                ->whereHas('period', fn ($q) => $q
                    ->where('is_thirteenth_month', false)
                    ->where('is_first_half', (bool) $period->is_first_half)
                    ->where('period_start', '<', $period->period_start)
                    ->where('status', '!=', PayrollPeriodStatus::Voided->value))
                ->join('payroll_periods', 'payroll_periods.id', '=', 'payrolls.payroll_period_id')
                ->orderByDesc('payroll_periods.period_start')
                ->select('payrolls.*')
                ->first();

            // The same-half payroll above is only the comparison baseline for
            // net-pay changes. First-payroll detection must look across both
            // halves; otherwise every employee paid in the other half is
            // incorrectly flagged again on alternating cutoffs.
            $hasEarlierRegularPayroll = $previous !== null || Payroll::query()
                ->where('payrolls.employee_id', $employeeId)
                ->where('payrolls.id', '!=', $payroll->id)
                ->whereHas('period', fn ($q) => $q
                    ->where('is_thirteenth_month', false)
                    ->where('period_start', '<', $period->period_start)
                    ->where('status', '!=', PayrollPeriodStatus::Voided->value))
                ->exists();
        }

        $current = (float) $payroll->net_pay;
        $gross = (float) $payroll->gross_pay;
        // `payrolls` has no overtime_hours column — the old read of
        // $payroll->overtime_hours was always null, so excessive_ot could never
        // fire. OT hours live on the attendance rows, so sum them for the
        // period. Cheap: one aggregate per payroll row, indexed on
        // (employee_id, date).
        $ot = $period->is_thirteenth_month
            ? 0.0
            : (float) Attendance::query()
                ->where('employee_id', $employeeId)
                ->whereBetween('date', [$period->period_start, $period->period_end])
                ->sum('overtime_hours');

        // 1. Zero pay
        if ($current === 0.0) {
            $created += $this->flag($payroll, $period, PayrollAnomalyType::ZeroPay, [
                'current_net' => $current,
            ]);
        }

        // Regular-pay comparisons do not apply to a distinct annual payout.
        if (! $period->is_thirteenth_month) {
            // 2. First payroll / large change. A first-payroll flag now means
            // no earlier regular payroll exists in this system, not merely
            // that this employee has no payroll from the same half.
            if (! $hasEarlierRegularPayroll) {
                $created += $this->flag($payroll, $period, PayrollAnomalyType::FirstPayroll, [
                    'current_net' => $current,
                    'explanation' => 'No earlier regular payroll is recorded for this employee in this system. Verify prior payroll history before accepting this as their first payroll.',
                ]);
            }

            if ($previous !== null) {
                $prev = (float) $previous->net_pay;
                if ($prev > 0.0) {
                    $delta = $current - $prev;
                    $pct = abs($delta) / max(0.01, $prev);
                    if ($pct > $policy['net_change_ratio']) {
                        $created += $this->flag($payroll, $period, PayrollAnomalyType::LargeChange, [
                            'previous_net' => $prev,
                            'current_net' => $current,
                            'percent_change' => round($pct * 100, 2),
                            'direction' => $delta >= 0 ? 'increase' : 'decrease',
                        ]);
                    }
                }
            }

            // 3. Excessive OT
            if ($ot > $policy['overtime_hours']) {
                $created += $this->flag($payroll, $period, PayrollAnomalyType::ExcessiveOt, [
                    'overtime_hours' => $ot,
                ]);
            }
        }

        // 4. High deduction ratio. Measure actual deductions, not (gross - net):
        // the latter also swept in signed adjustments, so a negative adjustment
        // inflated the ratio and a positive one masked a genuinely heavy
        // deduction load.
        if ($gross > 0) {
            $deductions = (float) $payroll->total_deductions;
            $ratio = $deductions / $gross;
            if ($ratio > $policy['deduction_ratio']) {
                $created += $this->flag($payroll, $period, PayrollAnomalyType::HighDeduction, [
                    'gross_pay' => $gross,
                    'net_pay' => $current,
                    'deduction_ratio' => round($ratio, 4),
                ]);
            }
        }

        // 5. An annual payout has no attendance cutoff; regular payroll does.
        if (! $period->is_thirteenth_month) {
            $openDates = Attendance::query()
                ->where('employee_id', $employeeId)
                ->whereBetween('date', [$period->period_start, $period->period_end])
                ->whereNotNull('time_in')->whereNull('time_out')
                ->whereDate('date', '<', now()->toDateString())
                ->orderBy('date')
                ->pluck('date')
                ->map(fn ($d) => $d->toDateString())
                ->all();
            if ($openDates !== []) {
                $created += $this->flag($payroll, $period, PayrollAnomalyType::OpenAttendance, ['dates' => $openDates]);
            }

            $missingUnpaidLeave = $this->missingUnpaidAbsenceLeaves($payroll, $period);
            $staleAbsenceAdjustments = $this->staleAppliedUnpaidAbsenceAdjustments($payroll, $period);
            if ($missingUnpaidLeave->isNotEmpty() || $staleAbsenceAdjustments->isNotEmpty()) {
                $created += $this->flag($payroll, $period, PayrollAnomalyType::UnpaidAbsence, [
                    'leave_requests' => $missingUnpaidLeave->map(static fn (LeaveRequest $leave): array => [
                        'leave_request_no' => $leave->leave_request_no,
                        'start_date' => $leave->start_date->toDateString(),
                        'end_date' => $leave->end_date->toDateString(),
                        'days' => (string) $leave->days,
                    ])->values()->all(),
                    'stale_adjustments' => $staleAbsenceAdjustments->map(static fn (PayrollAdjustment $adjustment): array => [
                        'id' => $adjustment->hash_id,
                        'leave_request_no' => $adjustment->sourceLeaveRequest()->withTrashed()->value('leave_request_no'),
                    ])->values()->all(),
                ]);
            }
        }

        return $created;
    }

    private function flag(Payroll $payroll, PayrollPeriod $period, PayrollAnomalyType $type, array $details): int
    {
        try {
            // Use raw insert with ignore-on-conflict semantics via firstOrCreate.
            $row = PayrollAnomalyFlag::firstOrCreate([
                'payroll_id' => $payroll->id,
                'flag_type' => $type->value,
            ], [
                'payroll_period_id' => $period->id,
                'employee_id' => $payroll->employee_id,
                'details' => $details,
                'is_resolved' => false,
            ]);

            return $row->wasRecentlyCreated ? 1 : 0;
        } catch (QueryException $e) {
            // A concurrent detector inserting the same (payroll_id, flag_type)
            // is the ONE benign failure here: the flag it raced us to write is
            // the flag we wanted, so the row exists either way.
            //
            // Anything else — a missing column, a bad FK, a dead connection —
            // must NOT be swallowed. These flags are the gate finalize() reads,
            // so a detector that silently wrote nothing reads as a clean period.
            // A guard whose failure path hides its own failure is the defect
            // pattern CLAUDE.md calls out; the caller is responsible for
            // deciding what an unevaluable gate means.
            if (! $this->isUniqueViolation($e)) {
                throw $e;
            }

            Log::info('PayrollAnomalyService: flag already raised by a concurrent detector', [
                'payroll_id' => $payroll->id,
                'type' => $type->value,
            ]);

            return 0;
        }
    }

    private function isUniqueViolation(QueryException $e): bool
    {
        // PG: SQLSTATE 23505. SQLite (test fallback): 23000 with a message.
        return in_array((string) $e->getCode(), ['23505', '23000'], true)
            || str_contains(strtolower($e->getMessage()), 'unique');
    }

    public function resolve(PayrollAnomalyFlag $flag, int $userId, ?string $remarks): PayrollAnomalyFlag
    {
        return DB::transaction(function () use ($flag, $userId, $remarks): PayrollAnomalyFlag {
            // Match finalization's lock order so a reviewer cannot race the
            // unresolved-flag gate while the period is being finalized.
            $period = PayrollPeriod::query()->lockForUpdate()->find($flag->payroll_period_id);
            if (! $period) {
                throw new BusinessRuleException('Payroll period not found.');
            }
            if (! in_array($period->status, [PayrollPeriodStatus::Computed, PayrollPeriodStatus::Approved], true)) {
                throw new BusinessRuleException('Payroll anomalies can only be reviewed while the period is computed or approved.');
            }

            $locked = PayrollAnomalyFlag::query()
                ->where('payroll_period_id', $period->id)
                ->lockForUpdate()
                ->find($flag->id);
            if (! $locked) {
                throw new BusinessRuleException('Payroll anomaly flag not found.');
            }
            if ($locked->is_resolved) {
                throw new BusinessRuleException('This payroll anomaly has already been reviewed.');
            }

            $resolvedAt = now();
            $oldValues = [
                'is_resolved' => (bool) $locked->is_resolved,
                'resolved_by' => $locked->resolved_by,
                'resolved_at' => optional($locked->resolved_at)->toIso8601String(),
                'resolution_remarks' => $locked->resolution_remarks,
            ];
            $locked->update([
                'is_resolved' => true,
                'resolved_by' => $userId,
                'resolved_at' => $resolvedAt,
                'resolution_remarks' => $remarks,
            ]);
            app(PayrollAnomalyRealtimeService::class)->stage($period, 'resolved');

            AuditLog::create([
                'user_id' => $userId,
                'actor_type' => 'user',
                'action' => 'payroll.anomaly.resolve',
                'model_type' => PayrollAnomalyFlag::class,
                'model_id' => $locked->id,
                'old_values' => $oldValues,
                'new_values' => [
                    'is_resolved' => true,
                    'resolved_by' => $userId,
                    'resolved_at' => $resolvedAt->toIso8601String(),
                    'resolution_remarks' => $remarks,
                ],
                'ip_address' => request()?->ip(),
                'user_agent' => request()?->userAgent(),
                'created_at' => $resolvedAt,
            ]);

            return $locked->fresh();
        });
    }

    /** Resolve every currently unresolved flag in one atomic, audited review. */
    public function resolveAll(PayrollPeriod $period, int $userId, string $remarks): int
    {
        return DB::transaction(function () use ($period, $userId, $remarks): int {
            // Match single-item resolution and finalization lock ordering.
            $lockedPeriod = PayrollPeriod::query()->lockForUpdate()->find($period->id);
            if (! $lockedPeriod) {
                throw new BusinessRuleException('Payroll period not found.');
            }
            if (! in_array($lockedPeriod->status, [PayrollPeriodStatus::Computed, PayrollPeriodStatus::Approved], true)) {
                throw new BusinessRuleException('Payroll anomalies can only be reviewed while the period is computed or approved.');
            }

            $flags = PayrollAnomalyFlag::query()
                ->where('payroll_period_id', $lockedPeriod->id)
                ->where('is_resolved', false)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            if ($flags->isEmpty()) {
                return 0;
            }

            $resolvedAt = now();
            foreach ($flags as $flag) {
                $oldValues = [
                    'is_resolved' => (bool) $flag->is_resolved,
                    'resolved_by' => $flag->resolved_by,
                    'resolved_at' => optional($flag->resolved_at)->toIso8601String(),
                    'resolution_remarks' => $flag->resolution_remarks,
                ];
                $flag->update([
                    'is_resolved' => true,
                    'resolved_by' => $userId,
                    'resolved_at' => $resolvedAt,
                    'resolution_remarks' => $remarks,
                ]);

                AuditLog::create([
                    'user_id' => $userId,
                    'actor_type' => 'user',
                    'action' => 'payroll.anomaly.resolve',
                    'model_type' => PayrollAnomalyFlag::class,
                    'model_id' => $flag->id,
                    'old_values' => $oldValues,
                    'new_values' => [
                        'is_resolved' => true,
                        'resolved_by' => $userId,
                        'resolved_at' => $resolvedAt->toIso8601String(),
                        'resolution_remarks' => $remarks,
                    ],
                    'ip_address' => request()?->ip(),
                    'user_agent' => request()?->userAgent(),
                    'created_at' => $resolvedAt,
                ]);
            }

            app(PayrollAnomalyRealtimeService::class)->stage($lockedPeriod, 'resolved');

            return $flags->count();
        });
    }

    public function unresolvedCount(int $periodId): int
    {
        return PayrollAnomalyFlag::where('payroll_period_id', $periodId)
            ->where('is_resolved', false)
            ->count();
    }

    /**
     * Count approved unpaid leave records in this run that have no adjustment
     * actually applied to the matching employee payroll row. Finalization uses
     * this as a hard policy guard, even if a reviewer resolved the anomaly flag.
     */
    public function unpaidAbsenceGapCount(PayrollPeriod $period): int
    {
        if ($period->is_thirteenth_month) {
            return 0;
        }

        return Payroll::query()
            ->where('payroll_period_id', $period->id)
            ->get()
            ->sum(fn (Payroll $payroll): int => $this->missingUnpaidAbsenceLeaves($payroll, $period)->count()
                + $this->staleAppliedUnpaidAbsenceAdjustments($payroll, $period)->count());
    }

    /** @return Collection<int, LeaveRequest> */
    private function missingUnpaidAbsenceLeaves(Payroll $payroll, PayrollPeriod $period): Collection
    {
        $appliedLeaveIds = PayrollAdjustment::query()
            ->where('employee_id', $payroll->employee_id)
            ->where('target_payroll_period_id', $period->id)
            ->where('type', PayrollAdjustmentType::UnpaidAbsence->value)
            ->where('status', PayrollAdjustmentStatus::Applied->value)
            ->where('applied_to_payroll_id', $payroll->id)
            ->pluck('source_leave_request_id')
            ->filter()
            ->map(static fn ($id): int => (int) $id)
            ->all();

        return LeaveRequest::query()
            ->with('leaveType')
            ->where('employee_id', $payroll->employee_id)
            ->where('status', LeaveRequestStatus::Approved->value)
            ->whereHas('leaveType', static fn ($query) => $query->where('is_paid', false))
            ->whereDate('start_date', '<=', $period->period_end->toDateString())
            ->whereDate('end_date', '>=', $period->period_start->toDateString())
            ->get()
            ->reject(static fn (LeaveRequest $leave): bool => in_array((int) $leave->id, $appliedLeaveIds, true))
            ->values();
    }

    /** @return Collection<int, PayrollAdjustment> */
    private function staleAppliedUnpaidAbsenceAdjustments(Payroll $payroll, PayrollPeriod $period): Collection
    {
        $applied = PayrollAdjustment::query()
            ->where('employee_id', $payroll->employee_id)
            ->where('target_payroll_period_id', $period->id)
            ->where('type', PayrollAdjustmentType::UnpaidAbsence->value)
            ->where('status', PayrollAdjustmentStatus::Applied->value)
            ->where('applied_to_payroll_id', $payroll->id)
            ->get();
        if ($applied->isEmpty()) {
            return $applied;
        }

        $stillValidSourceIds = LeaveRequest::query()
            ->whereIn('id', $applied->pluck('source_leave_request_id')->filter()->unique())
            ->where('employee_id', $payroll->employee_id)
            ->where('status', LeaveRequestStatus::Approved->value)
            ->whereHas('leaveType', static fn ($query) => $query->where('is_paid', false))
            ->whereDate('start_date', '<=', $period->period_end->toDateString())
            ->whereDate('end_date', '>=', $period->period_start->toDateString())
            ->pluck('id')
            ->map(static fn ($id): int => (int) $id)
            ->all();

        return $applied
            ->reject(static fn (PayrollAdjustment $adjustment): bool => in_array(
                (int) $adjustment->source_leave_request_id,
                $stillValidSourceIds,
                true,
            ))
            ->values();
    }

    /**
     * @return array{net_change_ratio?:float,overtime_hours?:float,deduction_ratio:float}
     */
    private function policy(bool $thirteenthMonth): array
    {
        if ($thirteenthMonth) {
            if ($this->thirteenthMonthPolicy === null) {
                $deductionRatio = $this->numberSetting('payroll.anomaly.deduction_ratio');
                if ($deductionRatio < 0 || $deductionRatio > 1) {
                    throw new BusinessRuleException('Payroll anomaly policy settings are invalid.');
                }

                $this->thirteenthMonthPolicy = ['deduction_ratio' => $deductionRatio];
            }

            return $this->thirteenthMonthPolicy;
        }

        if ($this->regularPolicy === null) {
            $policy = [
                'net_change_ratio' => $this->numberSetting('payroll.anomaly.net_change_ratio'),
                'overtime_hours' => $this->numberSetting('payroll.anomaly.overtime_hours'),
                'deduction_ratio' => $this->numberSetting('payroll.anomaly.deduction_ratio'),
            ];
            if ($policy['net_change_ratio'] < 0 || $policy['deduction_ratio'] < 0 || $policy['deduction_ratio'] > 1 || $policy['overtime_hours'] < 0) {
                throw new BusinessRuleException('Payroll anomaly policy settings are invalid.');
            }

            $this->regularPolicy = $policy;
        }

        return $this->regularPolicy;
    }

    private function numberSetting(string $key): float
    {
        $value = $this->settings->get($key, '__missing_payroll_policy__');
        if (! is_numeric($value)) {
            throw new BusinessRuleException("Required payroll setting {$key} is not configured.");
        }

        return (float) $value;
    }
}
