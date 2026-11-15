<?php

declare(strict_types=1);

namespace Tests\Feature\Payroll;

use App\Modules\Payroll\Enums\ContributionAgency;
use App\Modules\Payroll\Models\GovernmentContributionTable;
use App\Modules\Payroll\Models\GovernmentScheduleVersion;
use App\Modules\Payroll\Services\GovernmentContributionTableImportService;
use App\Modules\Payroll\Services\GovernmentContributionTableService;
use App\Modules\Payroll\Services\GovernmentScheduleVersionService;
use App\Common\Support\CacheGeneration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class EffectiveDatedBracketsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    private function sssRow(string $effective, float $ee, float $er): void
    {
        GovernmentContributionTable::create([
            'agency'         => 'sss',
            'bracket_min'    => 0.00,
            'bracket_max'    => 999999.99,
            'ee_amount'      => $ee,
            'er_amount'      => $er,
            'effective_date' => $effective,
            'is_active'      => true,
        ]);
    }

    /** @param array<int, array{0:string, 1:string, 2:string, 3:string}> $brackets */
    private function publishSchedule(ContributionAgency $agency, string $effectiveDate, array $brackets): void
    {
        $path = tempnam(sys_get_temp_dir(), 'gov-published-');
        $lines = ['bracket_min,bracket_max,ee_amount,er_amount,effective_date'];
        foreach ($brackets as [$min, $max, $ee, $er]) {
            $lines[] = implode(',', [$min, $max, $ee, $er, $effectiveDate]);
        }
        file_put_contents($path, implode("\n", $lines));

        try {
            $maker = \App\Modules\Auth\Models\User::factory()->create();
            $checker = \App\Modules\Auth\Models\User::factory()->create();
            $result = app(GovernmentContributionTableImportService::class)->importFromPath(
                $agency,
                $path,
                $agency->label().' test schedule',
                'https://example.gov/schedule',
                $maker->id,
            );
            $version = GovernmentScheduleVersion::query()->findOrFail($result['version_id']);
            app(GovernmentScheduleVersionService::class)->publish($version, $checker, completenessConfirmed: true);
        } finally {
            @unlink($path);
        }
    }

    public function test_picks_latest_schedule_on_or_before_the_date(): void
    {
        $this->sssRow('2024-01-01', 100.00, 200.00);
        $this->sssRow('2025-01-01', 150.00, 300.00);

        $svc = app(GovernmentContributionTableService::class);

        $in2024 = $svc->bracketsEffectiveOn(ContributionAgency::Sss, '2024-06-15');
        $this->assertSame('100.0000', (string) $in2024->first()->ee_amount);

        $in2025 = $svc->bracketsEffectiveOn(ContributionAgency::Sss, '2025-06-15');
        $this->assertSame('150.0000', (string) $in2025->first()->ee_amount);
    }

    public function test_active_bracket_late_fill_stays_in_the_retired_generation(): void
    {
        $this->sssRow('2025-01-01', 100.00, 200.00);
        $service = app(GovernmentContributionTableService::class);
        $service->activeBrackets(ContributionAgency::Sss);
        // Model a table update followed by generation rotation. A reader must
        // miss the old payload namespace and reload the new row value.
        GovernmentContributionTable::query()->update(['ee_amount' => 250.00]);
        CacheGeneration::rotateDefault('gov_table:sss:ver', 604800, 'payroll.test', 'after-write');

        $freshRows = $service->activeBrackets(ContributionAgency::Sss);

        $this->assertSame('250.0000', (string) $freshRows->first()->ee_amount);
    }

    public function test_inactive_effective_rows_are_not_used_for_payroll(): void
    {
        $this->sssRow('2024-01-01', 100.00, 200.00);
        GovernmentContributionTable::create([
            'agency' => 'sss',
            'bracket_min' => 0.00,
            'bracket_max' => 999999.99,
            'ee_amount' => 150.00,
            'er_amount' => 300.00,
            'effective_date' => '2025-01-01',
            'is_active' => false,
        ]);

        $rows = app(GovernmentContributionTableService::class)
            ->bracketsEffectiveOn(ContributionAgency::Sss, '2025-06-15');

        $this->assertSame('100.0000', (string) $rows->sole()->ee_amount);
    }

    public function test_falls_back_to_active_set_when_no_dated_rows_match(): void
    {
        $this->sssRow('2025-01-01', 150.00, 300.00);

        $svc = app(GovernmentContributionTableService::class);
        // A date before any effective_date → fall back to active set, not empty.
        $rows = $svc->bracketsEffectiveOn(ContributionAgency::Sss, '2020-01-01');
        $this->assertCount(1, $rows);
    }

    public function test_delete_and_restore_update_the_effective_schedule_source(): void
    {
        $this->sssRow('2025-01-01', 150.00, 300.00);
        $svc = app(GovernmentContributionTableService::class);
        $row = GovernmentContributionTable::query()
            ->where('agency', 'sss')
            ->whereDate('effective_date', '2025-01-01')
            ->firstOrFail();

        $this->assertCount(1, $svc->bracketsEffectiveOn(ContributionAgency::Sss, '2025-06-01'));
        $svc->delete($row);
        $this->assertSoftDeleted('government_contribution_tables', ['id' => $row->id]);

        $svc->restore($row);
        $this->assertDatabaseHas('government_contribution_tables', ['id' => $row->id, 'deleted_at' => null]);
        $this->assertCount(1, $svc->bracketsEffectiveOn(ContributionAgency::Sss, '2025-06-01'));
    }

    public function test_sss_service_uses_schedule_in_force_on_pay_date(): void
    {
        $this->publishSchedule(ContributionAgency::Sss, '2024-01-01', [['0', '999999.99', '100', '200']]);
        $this->publishSchedule(ContributionAgency::Sss, '2025-01-01', [['0', '999999.99', '150', '300']]);

        $svc = app(\App\Modules\Payroll\Services\Government\SssComputationService::class);

        $r2024 = $svc->compute('20000', \Illuminate\Support\Carbon::parse('2024-06-15'));
        $this->assertSame('100.00', $r2024['ee']);

        $r2025 = $svc->compute('20000', \Illuminate\Support\Carbon::parse('2025-06-15'));
        $this->assertSame('150.00', $r2025['ee']);
    }

    public function test_payroll_engine_deducts_using_schedule_on_pay_date(): void
    {
        // SSS flat amounts: 2024 EE 100 / ER 200; 2025 EE 150 / ER 300.
        $this->publishSchedule(ContributionAgency::Sss, '2024-01-01', [['0', '999999.99', '100', '200']]);
        $this->publishSchedule(ContributionAgency::Sss, '2025-01-01', [['0', '999999.99', '150', '300']]);
        // The compute-time guard (PY-02) refuses a first-half period unless ALL
        // FOUR agencies have brackets effective on the payroll date, so seed
        // minimal PhilHealth/Pag-IBIG/BIR rows; only SSS moves sss_ee below.
        $this->publishSchedule(ContributionAgency::Philhealth, '2024-01-01', [['0', '999999.99', '0.0225', '0.0225']]);
        $this->publishSchedule(ContributionAgency::Pagibig, '2024-01-01', [['0', '999999.99', '0.02', '0.02']]);
        $this->publishSchedule(ContributionAgency::Bir, '2024-01-01', [['0', '999999.99', '0', '0']]);

        $employee = \App\Modules\HR\Models\Employee::factory()->create([
            'pay_type'             => 'monthly',
            'basic_monthly_salary' => 20000.00,
            'date_hired'           => '2020-01-01',
        ]);
        // Pay date in 2024 → engine must use the 2024 schedule (EE 100), NOT today's date
        // (now()=2026 → 2025 schedule → 150). This proves payroll_date is threaded through.
        $period = \App\Modules\Payroll\Models\PayrollPeriod::factory()->create([
            'status'              => 'draft',
            'period_start'        => '2024-06-01',
            'period_end'          => '2024-06-15',
            'payroll_date'        => '2024-06-15',
            'is_first_half'       => true,
            'is_thirteenth_month' => false,
        ]);

        $payroll = app(\App\Modules\Payroll\Services\PayrollCalculatorService::class)
            ->computeForEmployee($period, $employee);

        $this->assertSame('100.00', (string) $payroll->sss_ee);
    }

    public function test_2025_seeder_makes_sss_effective_2025(): void
    {
        $this->seed(\Database\Seeders\GovernmentTableSeeder::class);      // 2024
        $this->seed(\Database\Seeders\GovernmentTable2025Seeder::class);  // 2025
        Cache::flush();

        $svc = app(\App\Modules\Payroll\Services\Government\SssComputationService::class);

        // MSC 20,000 in 2025 → EE = 20000 * 5% = 1000.00
        $r = $svc->compute('20000', \Illuminate\Support\Carbon::parse('2025-07-15'));
        $this->assertSame('1000.00', $r['ee']);
        $this->assertSame('2000.00', $r['er']); // 20000 * 10%
    }
}
