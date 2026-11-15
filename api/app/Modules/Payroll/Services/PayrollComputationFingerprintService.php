<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Services;

use App\Modules\Payroll\Models\Payroll;
use App\Modules\Payroll\Models\PayrollPeriod;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use JsonException;

class PayrollComputationFingerprintService
{
    public const POLICY_VERSION = 'payroll-integrity-2026-11-v2';

    /**
     * Hash the persisted result and all inputs the payroll/anomaly gates read.
     * Raw employee data is only used to calculate the digest and is never stored
     * in the evaluation record.
     *
     * @throws JsonException
     */
    public function fingerprint(PayrollPeriod $period): string
    {
        $payrolls = Payroll::query()
            ->where('payroll_period_id', $period->id)
            ->orderBy('employee_id')
            ->orderBy('id')
            ->get([
                'id', 'employee_id', 'pay_type', 'days_worked', 'basic_pay', 'leave_pay',
                'overtime_pay', 'night_diff_pay', 'holiday_pay', 'tardiness_deduction',
                'undertime_deduction', 'gross_pay', 'sss_ee', 'sss_er', 'sss_ec', 'philhealth_ee',
                'philhealth_er', 'pagibig_ee', 'pagibig_er', 'withholding_tax',
                'thirteenth_month_taxable_excess', 'thirteenth_month_correction_delta',
                'loan_deductions', 'other_deductions', 'adjustment_amount',
                'total_deductions', 'net_pay', 'error_message',
            ])
            ->map(static fn (Payroll $payroll): array => $payroll->getAttributes())
            ->values()
            ->all();
        $payrollIds = array_column($payrolls, 'id');
        $employeeIds = collect($payrolls)->pluck('employee_id')->map(static fn ($id): int => (int) $id);
        if ($period->is_thirteenth_month && Schema::hasTable('thirteenth_month_accruals')) {
            $employeeIds = $employeeIds->merge(DB::table('thirteenth_month_accruals')
                ->where('year', (int) $period->period_start->format('Y'))
                ->where('is_paid', false)
                ->pluck('employee_id')
                ->map(static fn ($id): int => (int) $id));
        } elseif (! $period->is_thirteenth_month) {
            // Include employees who became eligible after this run even when
            // they have no payroll row yet. Otherwise a roster change could
            // leave a previously clean evaluation looking current.
            $employeeIds = $employeeIds->merge(app(PayrollPeriodService::class)
                ->availableEmployeeQuery($period)
                ->pluck('id')
                ->map(static fn ($id): int => (int) $id));
        }
        $employeeIds = $employeeIds->unique()->sort()->values()->all();

        $sources = [
            'policy_version' => self::POLICY_VERSION,
            'period' => [
                'id' => $period->id,
                'period_start' => $period->period_start?->toDateString(),
                'period_end' => $period->period_end?->toDateString(),
                'payroll_date' => $period->payroll_date?->toDateString(),
                'is_first_half' => (bool) $period->is_first_half,
                'is_thirteenth_month' => (bool) $period->is_thirteenth_month,
                'scope_employment_types' => $period->scope_employment_types,
                'scope_department_ids' => $period->scope_department_ids,
                'scope_pay_types' => $period->scope_pay_types,
            ],
            'payrolls' => $payrolls,
            'comparison_payrolls' => $this->comparisonPayrolls($employeeIds, $period),
            'employees' => $employeeIds === [] ? [] : DB::table('employees')
                ->whereIn('id', $employeeIds)
                ->orderBy('id')
                ->get([
                    'id', 'status', 'employment_type', 'pay_type', 'date_hired', 'date_regularized',
                    'department_id', 'position_id', 'basic_monthly_salary', 'semi_monthly_rate',
                ])
                ->all(),
            'attendance' => $this->rowsForEmployeesInPeriod('attendances', $employeeIds, $period),
            'leave_requests' => $this->rowsForEmployeesInPeriod('leave_requests', $employeeIds, $period),
            'salary_history' => $this->salaryHistory($employeeIds, $period),
            'adjustments' => $this->adjustments($period->id, $payrollIds),
            'loan_balances' => $this->loanBalances($employeeIds),
            'loan_payments' => $payrollIds === [] || ! Schema::hasTable('loan_payments') ? [] : DB::table('loan_payments')
                ->whereIn('payroll_id', $payrollIds)->orderBy('id')->get()->all(),
            'deduction_details' => $payrollIds === [] || ! Schema::hasTable('payroll_deduction_details') ? [] : DB::table('payroll_deduction_details')
                ->whereIn('payroll_id', $payrollIds)->orderBy('id')->get()->all(),
            'loan_allocations' => $payrollIds === [] || ! Schema::hasTable('payroll_loan_recovery_allocations') ? [] : DB::table('payroll_loan_recovery_allocations')
                ->whereIn('payroll_id', $payrollIds)->orderBy('id')->get()->all(),
            'government_schedule_pins' => $this->governmentSchedulePins($period),
            'annual_sources' => $this->annualSources($employeeIds, $period),
            'clearances' => $employeeIds === [] || ! Schema::hasTable('clearances') ? [] : DB::table('clearances')
                ->whereIn('employee_id', $employeeIds)
                ->whereNotNull('separation_date')
                ->orderBy('id')
                ->get(['id', 'employee_id', 'status', 'separation_date', 'updated_at'])
                ->all(),
            'payroll_settings' => Schema::hasTable('settings') ? DB::table('settings')
                ->where(static fn ($query) => $query
                    ->where('group', 'payroll')
                    ->orWhere('key', 'like', 'accounting.accounts.%'))
                ->orderBy('key')
                ->get(['key', 'value'])
                ->all() : [],
        ];

        return hash('sha256', json_encode($sources, JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }

    /** @param array<int, int> $employeeIds
     * @return array<int, object>
     */
    private function rowsForEmployeesInPeriod(string $table, array $employeeIds, PayrollPeriod $period): array
    {
        if ($employeeIds === [] || ! Schema::hasTable($table)) {
            return [];
        }

        $query = DB::table($table)->whereIn('employee_id', $employeeIds);
        if ($table === 'attendances') {
            $query->whereBetween('date', [$period->period_start->toDateString(), $period->period_end->toDateString()]);
        } else {
            $query->whereDate('start_date', '<=', $period->period_end->toDateString())
                ->whereDate('end_date', '>=', $period->period_start->toDateString());
        }

        return $query->orderBy('id')->get()->all();
    }

    /** @param array<int, int> $employeeIds
     * @return array<int, object>
     */
    private function salaryHistory(array $employeeIds, PayrollPeriod $period): array
    {
        if ($employeeIds === [] || ! Schema::hasTable('employee_salary_history')) {
            return [];
        }

        return DB::table('employee_salary_history')
            ->whereIn('employee_id', $employeeIds)
            ->whereDate('effective_date', '<=', $period->period_end->toDateString())
            ->orderBy('employee_id')
            ->orderBy('effective_date')
            ->orderBy('id')
            ->get()
            ->all();
    }

    /** @param array<int, int> $payrollIds
     * @return array<int, object>
     */
    private function adjustments(int $periodId, array $payrollIds): array
    {
        if (! Schema::hasTable('payroll_adjustments')) {
            return [];
        }

        return DB::table('payroll_adjustments')
            ->where(static function ($query) use ($periodId, $payrollIds): void {
                $query->where('target_payroll_period_id', $periodId)
                    ->orWhere('payroll_period_id', $periodId);
                if ($payrollIds !== []) {
                    $query->orWhereIn('applied_to_payroll_id', $payrollIds);
                }
            })
            ->orderBy('id')
            ->get()
            ->all();
    }

    /** @param array<int, int> $employeeIds
     * @return array<int, object>
     */
    private function loanBalances(array $employeeIds): array
    {
        if ($employeeIds === [] || ! Schema::hasTable('employee_loans')) {
            return [];
        }

        return DB::table('employee_loans')
            ->whereIn('employee_id', $employeeIds)
            ->orderBy('id')
            ->get(['id', 'employee_id', 'loan_type', 'status', 'balance', 'monthly_amortization', 'pay_periods_remaining', 'is_final_pay_deduction'])
            ->all();
    }

    /** @return array<int, object> */
    private function governmentSchedulePins(PayrollPeriod $period): array
    {
        if (! Schema::hasTable('payroll_period_government_schedule_versions')) {
            return [];
        }

        return DB::table('payroll_period_government_schedule_versions as pins')
            ->join('government_schedule_versions as versions', 'versions.id', '=', 'pins.government_schedule_version_id')
            ->where('pins.payroll_period_id', $period->id)
            ->orderBy('pins.agency')
            ->get([
                'pins.agency', 'pins.government_schedule_version_id', 'pins.effective_date',
                'versions.version_number', 'versions.status', 'versions.source_file_sha256',
            ])
            ->all();
    }

    /** @param array<int, int> $employeeIds
     * @return array<int, object>
     */
    private function comparisonPayrolls(array $employeeIds, PayrollPeriod $period): array
    {
        if ($employeeIds === [] || $period->is_thirteenth_month) {
            return [];
        }

        return DB::table('payrolls as payrolls')
            ->join('payroll_periods as periods', 'periods.id', '=', 'payrolls.payroll_period_id')
            ->whereIn('payrolls.employee_id', $employeeIds)
            ->where('periods.is_thirteenth_month', false)
            ->where('periods.is_first_half', (bool) $period->is_first_half)
            ->whereDate('periods.period_start', '<', $period->period_start->toDateString())
            ->orderBy('payrolls.employee_id')
            ->orderBy('periods.period_start')
            ->orderBy('payrolls.id')
            ->get([
                'payrolls.employee_id', 'payrolls.pay_type', 'payrolls.gross_pay',
                'payrolls.total_deductions', 'payrolls.net_pay', 'payrolls.overtime_pay',
                'periods.period_start', 'periods.period_end', 'periods.is_first_half',
            ])
            ->all();
    }

    /** @param array<int, int> $employeeIds
     * @return array<string, mixed>
     */
    private function annualSources(array $employeeIds, PayrollPeriod $period): array
    {
        if (! $period->is_thirteenth_month) {
            return [];
        }

        $year = (int) $period->period_start->format('Y');
        $periods = DB::table('payroll_periods')
            ->where('is_thirteenth_month', false)
            ->whereYear('period_start', $year)
            ->orderBy('period_start')
            ->orderBy('period_end')
            ->orderBy('id')
            ->get(['id', 'period_start', 'period_end', 'status', 'is_first_half']);
        $sourcePeriodIds = $periods->pluck('id')->all();

        return [
            'periods' => $periods->all(),
            'payrolls' => $sourcePeriodIds === [] ? [] : DB::table('payrolls')
                ->whereIn('payroll_period_id', $sourcePeriodIds)
                ->orderBy('employee_id')
                ->orderBy('payroll_period_id')
                ->orderBy('id')
                ->get([
                    'id', 'payroll_period_id', 'employee_id', 'basic_pay', 'gross_pay',
                    'sss_ee', 'philhealth_ee', 'pagibig_ee', 'withholding_tax', 'net_pay',
                ])
                ->all(),
            'accruals' => Schema::hasTable('thirteenth_month_accruals') ? DB::table('thirteenth_month_accruals')
                ->where('year', $year)
                ->orderBy('employee_id')
                ->get(['employee_id', 'total_basic_earned', 'accrued_amount', 'is_paid', 'payroll_id'])
                ->all() : [],
            'tax_brackets' => Schema::hasTable('payroll_annual_tax_brackets') ? DB::table('payroll_annual_tax_brackets')
                ->whereDate('effective_from', '<=', $period->payroll_date->toDateString())
                ->orderBy('effective_from')
                ->orderBy('bracket_min')
                ->get()
                ->all() : [],
            'tax_rules' => Schema::hasTable('thirteenth_month_tax_rules') ? DB::table('thirteenth_month_tax_rules')
                ->whereDate('effective_from', '<=', $period->payroll_date->toDateString())
                ->orderBy('effective_from')
                ->get()
                ->all() : [],
            'taxable_benefits' => $employeeIds === [] || ! Schema::hasTable('de_minimis_benefits') ? [] : DB::table('de_minimis_benefits')
                ->whereIn('employee_id', $employeeIds)
                ->where('period_year', $year)
                ->orderBy('employee_id')
                ->orderBy('id')
                ->get()
                ->all(),
        ];
    }
}
