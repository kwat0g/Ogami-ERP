<?php

declare(strict_types=1);

namespace Tests\Feature\Payroll;

use App\Modules\Payroll\Enums\PayrollPeriodStatus;
use App\Modules\Payroll\Models\PayrollPeriod;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PayrollIntegrityPreflightTest extends TestCase
{
    use RefreshDatabase;

    public function test_preflight_reports_gaps_without_mutating_payroll_records(): void
    {
        $period = PayrollPeriod::factory()->create([
            'period_start' => '2026-11-01',
            'period_end' => '2026-11-15',
            'payroll_date' => '2026-11-17',
            'status' => PayrollPeriodStatus::Draft,
        ]);
        $periodCount = DB::table('payroll_periods')->count();
        $evaluationCount = DB::table('payroll_anomaly_evaluations')->count();

        $this->assertSame(0, Artisan::call('payroll:integrity:preflight', ['--json' => true]));
        $report = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);

        $this->assertSame('read_only', $report['mode']);
        $this->assertSame(1, $report['summary']['open_regular_periods']);
        $this->assertSame(1, $report['summary']['open_periods_missing_current_evaluation']);
        $this->assertSame($period->id, $report['open_regular_periods'][0]['id']);
        $this->assertSame('legacy_policy_recompute_or_review', $report['open_regular_periods'][0]['classification']);
        $this->assertSame($periodCount, DB::table('payroll_periods')->count());
        $this->assertSame($evaluationCount, DB::table('payroll_anomaly_evaluations')->count());
    }
}
