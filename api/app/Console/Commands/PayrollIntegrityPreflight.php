<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Common\Support\Money;
use App\Modules\HR\Models\Clearance;
use App\Modules\Payroll\Enums\PayrollPeriodStatus;
use App\Modules\Payroll\Models\PayrollAnomalyEvaluation;
use App\Modules\Payroll\Models\PayrollPeriod;
use App\Modules\Payroll\Services\PayrollAnomalyService;
use App\Modules\Payroll\Services\PayrollComputationFingerprintService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/** Read-only inventory of payroll integrity evidence and remediation candidates. */
class PayrollIntegrityPreflight extends Command
{
    protected $signature = 'payroll:integrity:preflight {--json : Print the complete report as JSON}';

    protected $description = 'Report payroll integrity gaps without changing payroll data';

    public function handle(): int
    {
        $report = $this->buildReport();

        if ($this->option('json')) {
            $this->line(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));

            return self::SUCCESS;
        }

        $this->info('Payroll integrity preflight (read only) · '.$report['generated_at']);
        foreach ($report['summary'] as $name => $count) {
            $this->line(sprintf('%-38s %d', str_replace('_', ' ', $name), $count));
        }
        $this->newLine();
        $this->line('Run with --json for period IDs, statuses, and classifications.');

        return self::SUCCESS;
    }

    /** @return array<string, mixed> */
    private function buildReport(): array
    {
        $currentPolicy = PayrollComputationFingerprintService::POLICY_VERSION;
        $openStates = [
            PayrollPeriodStatus::Draft->value,
            PayrollPeriodStatus::Processing->value,
            PayrollPeriodStatus::Computed->value,
            PayrollPeriodStatus::Approved->value,
        ];
        $openPeriods = PayrollPeriod::query()
            ->where('is_thirteenth_month', false)
            ->whereIn('status', $openStates)
            ->orderBy('period_start')
            ->orderBy('id')
            ->get(['id', 'period_start', 'period_end', 'status', 'computation_policy_version', 'anomaly_detection_failed']);
        $periodFindings = $openPeriods->map(function (PayrollPeriod $period) use ($currentPolicy): array {
            $evaluation = PayrollAnomalyEvaluation::query()
                ->where('payroll_period_id', $period->id)
                ->orderByDesc('id')
                ->first();
            $unresolved = DB::table('payroll_anomaly_flags')
                ->where('payroll_period_id', $period->id)
                ->where('is_resolved', false)
                ->count();
            $pins = Schema::hasTable('payroll_period_government_schedule_versions')
                ? DB::table('payroll_period_government_schedule_versions')
                    ->where('payroll_period_id', $period->id)
                    ->count()
                : 0;
            $currentEvaluation = false;
            if (in_array($period->status, [PayrollPeriodStatus::Computed, PayrollPeriodStatus::Approved], true)
                && $evaluation?->status === 'passed'
                && $period->computation_policy_version === $currentPolicy
                && ! $period->anomaly_detection_failed) {
                try {
                    app(PayrollAnomalyService::class)->assertCurrentEvaluation($period);
                    $currentEvaluation = true;
                } catch (Throwable) {
                    $currentEvaluation = false;
                }
            }

            return [
                'id' => $period->id,
                'period_start' => $period->period_start?->toDateString(),
                'period_end' => $period->period_end?->toDateString(),
                'status' => $period->status?->value,
                'policy_version' => $period->computation_policy_version,
                'evaluation_status' => $evaluation?->status ?? 'missing',
                'evaluation_current' => $currentEvaluation,
                'anomaly_detection_failed' => (bool) $period->anomaly_detection_failed,
                'unresolved_anomaly_count' => $unresolved,
                'government_schedule_pin_count' => $pins,
                'classification' => $this->classifyOpenPeriod($period, $evaluation?->status, $currentEvaluation, $unresolved, $pins),
            ];
        })->values()->all();

        $scheduleVersions = Schema::hasTable('government_schedule_versions')
            ? DB::table('government_schedule_versions')
                ->select('status')->selectRaw('COUNT(*) AS records')
                ->groupBy('status')->orderBy('status')->get()
                ->mapWithKeys(static fn (object $row): array => [(string) $row->status => (int) $row->records])
                ->all()
            : [];
        $periodsMissingPins = Schema::hasTable('payroll_period_government_schedule_versions')
            ? PayrollPeriod::query()
                ->where('is_thirteenth_month', false)
                ->whereIn('status', [PayrollPeriodStatus::Computed->value, PayrollPeriodStatus::Approved->value, PayrollPeriodStatus::Finalized->value, PayrollPeriodStatus::Disbursed->value])
                ->whereNotExists(function ($query): void {
                    $query->selectRaw('1')
                        ->from('payroll_period_government_schedule_versions as pins')
                        ->whereColumn('pins.payroll_period_id', 'payroll_periods.id')
                        ->groupBy('pins.payroll_period_id')
                        ->havingRaw('COUNT(*) >= 4');
                })
                ->count()
            : PayrollPeriod::query()->where('is_thirteenth_month', false)->whereIn('status', [PayrollPeriodStatus::Computed->value, PayrollPeriodStatus::Approved->value])->count();

        $loanEvidence = $this->loanEvidence();
        $finalPayEvidence = $this->finalPayEvidence();
        $annualRuns = $this->annualRuns();

        $summary = [
            'open_regular_periods' => count($periodFindings),
            'open_periods_missing_current_evaluation' => count(array_filter($periodFindings, static fn (array $row): bool => ! $row['evaluation_current'])),
            'open_periods_with_unresolved_anomalies' => count(array_filter($periodFindings, static fn (array $row): bool => $row['unresolved_anomaly_count'] > 0)),
            'open_periods_missing_four_statutory_pins' => count(array_filter($periodFindings, static fn (array $row): bool => $row['government_schedule_pin_count'] < 4)),
            'legacy_unverified_schedule_versions' => (int) ($scheduleVersions['legacy_unverified'] ?? 0),
            'scheduled_periods_missing_four_pins' => $periodsMissingPins,
            'current_policy_loan_rows_missing_allocation_evidence' => $loanEvidence['current_policy_missing_allocations'],
            'legacy_loan_rows_without_allocation_evidence' => $loanEvidence['legacy_without_allocations'],
            'loan_ledger_reconciliation_mismatches' => $loanEvidence['reconciliation_mismatches'],
            'final_pay_loan_recovery_records_without_evidence' => $finalPayEvidence['legacy_without_allocations'],
            'final_pay_loan_ledger_reconciliation_mismatches' => $finalPayEvidence['reconciliation_mismatches'],
            'annual_runs_with_unfinished_known_source_periods' => count(array_filter($annualRuns, static fn (array $row): bool => $row['unfinished_source_period_count'] > 0)),
            'annual_runs_missing_current_evaluation' => count(array_filter($annualRuns, static fn (array $row): bool => ! $row['evaluation_current'])),
        ];

        return [
            'generated_at' => now()->utc()->toIso8601String(),
            'mode' => 'read_only',
            'policy_version' => $currentPolicy,
            'summary' => $summary,
            'schedule_versions_by_status' => $scheduleVersions,
            'open_regular_periods' => $periodFindings,
            'loan_recovery_evidence' => $loanEvidence,
            'final_pay_recovery_evidence' => $finalPayEvidence,
            'annual_runs' => $annualRuns,
        ];
    }

    private function classifyOpenPeriod(PayrollPeriod $period, ?string $evaluationStatus, bool $evaluationCurrent, int $unresolved, int $pins): string
    {
        if ($period->anomaly_detection_failed || $evaluationStatus === 'failed') {
            return 'hold_failed_anomaly_evaluation';
        }
        if ($period->computation_policy_version !== PayrollComputationFingerprintService::POLICY_VERSION) {
            return 'legacy_policy_recompute_or_review';
        }
        if ($unresolved > 0) {
            return 'hold_unresolved_anomalies';
        }
        if ($pins < 4) {
            return 'hold_missing_statutory_schedule_pins';
        }
        if (! $evaluationCurrent) {
            return 'recompute_or_review_stale_evaluation';
        }

        return 'current_evidence_present';
    }

    /** @return array{current_policy_missing_allocations:int,legacy_without_allocations:int,reconciliation_mismatches:int,rows:array<int,array<string,mixed>>} */
    private function loanEvidence(): array
    {
        $rows = DB::table('payrolls as p')
            ->join('payroll_periods as pp', 'pp.id', '=', 'p.payroll_period_id')
            ->where('p.loan_deductions', '>', 0)
            ->orderBy('pp.id')
            ->orderBy('p.id')
            ->get(['p.id as payroll_id', 'p.payroll_period_id', 'p.loan_deductions', 'pp.computation_policy_version']);
        $allocations = DB::table('payroll_loan_recovery_allocations')
            ->whereNotNull('payroll_id')
            ->select('payroll_id')
            ->selectRaw('COALESCE(SUM(withheld_amount), 0) AS withheld')
            ->groupBy('payroll_id')
            ->get()->keyBy('payroll_id');
        $payments = DB::table('loan_payments')
            ->whereNotNull('payroll_id')
            ->select('payroll_id')
            ->selectRaw('COALESCE(SUM(amount), 0) AS paid')
            ->groupBy('payroll_id')
            ->get()->keyBy('payroll_id');
        $currentMissing = 0;
        $legacyMissing = 0;
        $mismatches = 0;
        $details = [];

        foreach ($rows as $row) {
            $allocation = $allocations->get($row->payroll_id);
            $payment = $payments->get($row->payroll_id);
            if (! $allocation) {
                if ($row->computation_policy_version === PayrollComputationFingerprintService::POLICY_VERSION) {
                    $currentMissing++;
                    $classification = 'current_policy_missing_allocation';
                } else {
                    $legacyMissing++;
                    $classification = 'legacy_unverified';
                }
            } elseif (bccomp((string) $allocation->withheld, (string) $row->loan_deductions, 2) !== 0
                || bccomp((string) ($payment->paid ?? '0.00'), (string) $row->loan_deductions, 2) !== 0) {
                $mismatches++;
                $classification = 'reconciliation_mismatch';
            } else {
                $classification = 'reconciled';
            }

            $details[] = [
                'payroll_id' => $row->payroll_id,
                'payroll_period_id' => $row->payroll_period_id,
                'loan_deductions' => (string) $row->loan_deductions,
                'allocation_withheld' => (string) ($allocation->withheld ?? '0.00'),
                'loan_payment_total' => (string) ($payment->paid ?? '0.00'),
                'classification' => $classification,
            ];
        }

        return [
            'current_policy_missing_allocations' => $currentMissing,
            'legacy_without_allocations' => $legacyMissing,
            'reconciliation_mismatches' => $mismatches,
            'rows' => $details,
        ];
    }

    /** @return array{legacy_without_allocations:int,reconciliation_mismatches:int,rows:array<int,array<string,mixed>>} */
    private function finalPayEvidence(): array
    {
        $legacy = 0;
        $mismatches = 0;
        $details = [];

        foreach (Clearance::query()->where('final_pay_computed', true)->orderBy('id')->get(['id', 'clearance_no', 'final_pay_breakdown']) as $clearance) {
            $allocationTotal = Money::round2((string) DB::table('payroll_loan_recovery_allocations')
                ->where('clearance_id', $clearance->id)->sum('withheld_amount'));
            $paymentTotal = Money::round2((string) DB::table('loan_payments')
                ->where('clearance_id', $clearance->id)
                ->where('payment_type', 'final_pay')
                ->sum('amount'));
            $breakdown = is_array($clearance->final_pay_breakdown) ? $clearance->final_pay_breakdown : [];
            $hasRunEvidence = is_string($breakdown['loan_recovery_allocation_run_id'] ?? null);

            if (! $hasRunEvidence && Money::gt($paymentTotal, '0.00')) {
                $legacy++;
                $classification = 'legacy_unverified';
            } elseif (Money::cmp($allocationTotal, $paymentTotal) !== 0
                || (isset($breakdown['loan_recovery_withheld'])
                    && Money::cmp($paymentTotal, (string) $breakdown['loan_recovery_withheld']) !== 0)) {
                $mismatches++;
                $classification = 'reconciliation_mismatch';
            } else {
                $classification = 'reconciled';
            }

            $details[] = [
                'clearance_id' => $clearance->id,
                'clearance_no' => $clearance->clearance_no,
                'allocation_withheld' => $allocationTotal,
                'loan_payment_total' => $paymentTotal,
                'classification' => $classification,
            ];
        }

        return [
            'legacy_without_allocations' => $legacy,
            'reconciliation_mismatches' => $mismatches,
            'rows' => $details,
        ];
    }

    /** @return array<int,array<string,mixed>> */
    private function annualRuns(): array
    {
        return PayrollPeriod::query()
            ->where('is_thirteenth_month', true)
            ->orderBy('period_start')
            ->orderBy('id')
            ->get(['id', 'period_start', 'payroll_date', 'status', 'computation_policy_version'])
            ->map(function (PayrollPeriod $period): array {
                $year = (int) $period->period_start->format('Y');
                $unfinishedSources = PayrollPeriod::query()
                    ->where('is_thirteenth_month', false)
                    ->whereYear('period_start', $year)
                    ->whereNotIn('status', [PayrollPeriodStatus::Finalized->value, PayrollPeriodStatus::Disbursed->value, PayrollPeriodStatus::Voided->value])
                    ->count();
                $evaluation = PayrollAnomalyEvaluation::query()
                    ->where('payroll_period_id', $period->id)
                    ->orderByDesc('id')
                    ->first();
                $evaluationCurrent = false;
                if (in_array($period->status, [PayrollPeriodStatus::Computed, PayrollPeriodStatus::Approved], true)
                    && $evaluation?->status === 'passed'
                    && $period->computation_policy_version === PayrollComputationFingerprintService::POLICY_VERSION) {
                    try {
                        app(PayrollAnomalyService::class)->assertCurrentEvaluation($period);
                        $evaluationCurrent = true;
                    } catch (Throwable) {
                        $evaluationCurrent = false;
                    }
                }

                return [
                    'id' => $period->id,
                    'year' => $year,
                    'status' => $period->status?->value,
                    'payroll_date' => $period->payroll_date?->toDateString(),
                    'policy_version' => $period->computation_policy_version,
                    'evaluation_status' => $evaluation?->status ?? 'missing',
                    'evaluation_current' => $evaluationCurrent,
                    'unfinished_source_period_count' => $unfinishedSources,
                    'classification' => $unfinishedSources > 0 ? 'hold_unfinished_source_periods' : ($evaluationCurrent ? 'current_evidence_present' : 'review_annual_run_evidence'),
                ];
            })->values()->all();
    }
}
