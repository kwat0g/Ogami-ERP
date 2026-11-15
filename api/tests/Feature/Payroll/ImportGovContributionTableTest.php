<?php

declare(strict_types=1);

namespace Tests\Feature\Payroll;

use App\Modules\Payroll\Enums\ContributionAgency;
use App\Modules\Payroll\Models\GovernmentContributionTable;
use App\Modules\Payroll\Models\GovernmentScheduleVersion;
use App\Modules\Payroll\Services\GovernmentContributionTableImportService;
use App\Modules\Payroll\Services\GovernmentScheduleVersionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ImportGovContributionTableTest extends TestCase
{
    use RefreshDatabase;

    private function makeCsv(string $body): string
    {
        $path = tempnam(sys_get_temp_dir(), 'gov_csv_');
        file_put_contents($path, $body);
        return $path;
    }

    public function test_import_publishes_a_new_version_without_mutating_prior_schedule_rows(): void
    {
        // Existing prior row for SSS — should be deactivated by import.
        GovernmentContributionTable::create([
            'agency'         => 'sss',
            'bracket_min'    => 0,
            'bracket_max'    => 4250,
            'ee_amount'      => 135.00,
            'er_amount'      => 285.00,
            'effective_date' => '2025-01-01',
            'is_active'      => true,
        ]);

        $csv = $this->makeCsv(<<<CSV
bracket_min,bracket_max,ee_amount,er_amount,effective_date
0,4249.99,180.00,380.00,2026-01-01
4250,4750,202.50,427.50,2026-01-01
CSV);
        $maker = \App\Modules\Auth\Models\User::factory()->create();
        $checker = \App\Modules\Auth\Models\User::factory()->create();

        $r = app(GovernmentContributionTableImportService::class)
            ->importFromPath(ContributionAgency::Sss, $csv, 'SSS source schedule', 'https://sss.gov.ph/schedule', $maker->id);

        $this->assertSame(2, $r['total']);
        $this->assertSame(2, $r['imported']);
        $this->assertSame(0, $r['updated']);
        $this->assertSame(0, $r['skipped']);
        $this->assertSame(0, $r['deactivated_prior']);
        $this->assertSame('draft', $r['status']);
        $version = GovernmentScheduleVersion::query()->findOrFail($r['version_id']);
        app(GovernmentScheduleVersionService::class)->publish($version, $checker, completenessConfirmed: true);

        // 2026-01-01 rows active.
        $this->assertSame(2, GovernmentContributionTable::where('agency', 'sss')
            ->where('effective_date', '2026-01-01')
            ->where('is_active', true)
            ->count());
        // Historical row remains intact and active in its legacy set; date/version
        // resolution selects the published 2026 version only for later dates.
        $this->assertSame(1, GovernmentContributionTable::where('agency', 'sss')
            ->where('effective_date', '2025-01-01')
            ->where('is_active', true)
            ->count());

        unlink($csv);
    }

    public function test_re_running_same_csv_is_idempotent(): void
    {
        $csv = $this->makeCsv(<<<CSV
bracket_min,bracket_max,ee_amount,er_amount,effective_date
0,4250,180.00,380.00,2026-01-01
CSV);

        $svc = app(GovernmentContributionTableImportService::class);
        $first = $svc->importFromPath(ContributionAgency::Sss, $csv, 'SSS source schedule', 'https://sss.gov.ph/schedule');
        $second = $svc->importFromPath(ContributionAgency::Sss, $csv, 'SSS source schedule', 'https://sss.gov.ph/schedule');

        $this->assertSame(1, $first['imported']);
        $this->assertSame(0, $second['imported']);
        $this->assertSame(0, $second['updated']);
        $this->assertTrue($second['idempotent']);
        $this->assertSame(1, GovernmentContributionTable::where('agency', 'sss')->count());
        unlink($csv);
    }

    public function test_bad_row_aborts_the_entire_import_without_partial_schedule(): void
    {
        $csv = $this->makeCsv(<<<CSV
bracket_min,bracket_max,ee_amount,er_amount,effective_date
0,4249.99,180.00,380.00,2026-01-01
9999,100,202.50,427.50,2026-01-01
4250,4750,202.50,427.50,2026-01-01
CSV);

        $r = app(GovernmentContributionTableImportService::class)
            ->importFromPath(ContributionAgency::Sss, $csv, 'SSS source schedule', 'https://sss.gov.ph/schedule');

        $this->assertSame(3, $r['total']);
        $this->assertSame(0, $r['imported']);
        $this->assertSame(3, $r['skipped']);
        $this->assertCount(1, $r['errors']);
        $this->assertSame(0, GovernmentContributionTable::where('agency', 'sss')
            ->where('effective_date', '2026-01-01')
            ->count());
        unlink($csv);
    }

    public function test_missing_required_column_fails_fast(): void
    {
        $csv = $this->makeCsv(<<<CSV
bracket_min,bracket_max,ee_amount,er_amount
0,4250,180.00,380.00
CSV);

        $r = app(GovernmentContributionTableImportService::class)
            ->importFromPath(ContributionAgency::Sss, $csv, 'SSS source schedule', 'https://sss.gov.ph/schedule');

        $this->assertSame(0, $r['total']);
        $this->assertSame(0, $r['imported']);
        $this->assertCount(1, $r['errors']);
        $this->assertStringContainsString('effective_date', $r['errors'][0]['message']);
        unlink($csv);
    }

    public function test_mixed_effective_dates_are_rejected_without_creating_a_schedule_version(): void
    {
        $csv = $this->makeCsv(<<<CSV
bracket_min,bracket_max,ee_amount,er_amount,effective_date
0,4249.99,180.00,380.00,2026-01-01
4250,999999.99,202.50,427.50,2027-01-01
CSV);

        $result = app(GovernmentContributionTableImportService::class)->importFromPath(
            ContributionAgency::Sss,
            $csv,
            'SSS schedule source',
            'https://sss.gov.ph/schedule',
        );

        $this->assertSame(0, $result['imported']);
        $this->assertSame(2, $result['skipped']);
        $this->assertStringContainsString('exactly one effective_date', $result['errors'][0]['message']);
        $this->assertSame(0, GovernmentScheduleVersion::query()->where('agency', 'sss')->count());
        unlink($csv);
    }
}
