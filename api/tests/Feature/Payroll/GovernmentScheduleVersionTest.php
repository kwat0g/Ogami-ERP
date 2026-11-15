<?php

declare(strict_types=1);

namespace Tests\Feature\Payroll;

use App\Modules\Auth\Models\User;
use App\Modules\Payroll\Enums\ContributionAgency;
use App\Modules\Payroll\Enums\GovernmentScheduleVersionStatus;
use App\Modules\Payroll\Models\GovernmentScheduleVersion;
use App\Modules\Payroll\Models\PayrollPeriod;
use App\Modules\Payroll\Services\GovernmentContributionTableImportService;
use App\Modules\Payroll\Services\GovernmentContributionTableService;
use App\Modules\Payroll\Services\GovernmentSchedulePinService;
use App\Modules\Payroll\Services\GovernmentScheduleVersionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GovernmentScheduleVersionTest extends TestCase
{
    use RefreshDatabase;

    private function csv(string $eeAmount, string $effectiveDate = '2026-01-01'): string
    {
        $path = tempnam(sys_get_temp_dir(), 'gov-schedule-');
        file_put_contents($path, implode("\n", [
            'bracket_min,bracket_max,ee_amount,er_amount,effective_date',
            "0,999999.99,{$eeAmount},200,{$effectiveDate}",
        ]));

        return $path;
    }

    public function test_import_creates_source_tracked_draft_versions_and_published_rows_are_immutable(): void
    {
        $maker = User::factory()->create();
        $checker = User::factory()->create();
        $importer = app(GovernmentContributionTableImportService::class);
        $versions = app(GovernmentScheduleVersionService::class);
        $csv = $this->csv('100');

        try {
            $import = $importer->importFromPath(
                ContributionAgency::Sss,
                $csv,
                'SSS contribution schedule',
                'https://www.sss.gov.ph/contribution-schedule',
                $maker->id,
            );

            $version = GovernmentScheduleVersion::query()->findOrFail($import['version_id']);
            $this->assertSame(GovernmentScheduleVersionStatus::Draft, $version->status);
            $this->assertSame(hash_file('sha256', $csv), $version->source_file_sha256);
            $this->assertSame($maker->id, $version->imported_by);
            $this->assertSame(1, $version->row_count);

            $versions->publish($version, $checker, completenessConfirmed: true);
            $originalRow = $version->contributionRows()->firstOrFail();

            $correctionCsv = $this->csv('125');
            try {
                $correction = $importer->importFromPath(
                    ContributionAgency::Sss,
                    $correctionCsv,
                    'Corrected SSS contribution schedule',
                    'https://www.sss.gov.ph/contribution-schedule',
                    $maker->id,
                );
            } finally {
                @unlink($correctionCsv);
            }

            $correctedVersion = GovernmentScheduleVersion::query()->findOrFail($correction['version_id']);
            $versions->publish($correctedVersion, $checker, completenessConfirmed: true);

            $this->assertSame(GovernmentScheduleVersionStatus::Superseded, $version->fresh()->status);
            $this->assertSame('100.0000', (string) $originalRow->fresh()->ee_amount);
            $this->assertSame('125.0000', (string) $correctedVersion->contributionRows()->firstOrFail()->ee_amount);
            $this->assertSame(2, $version->contributionRows()->count() + $correctedVersion->contributionRows()->count());
        } finally {
            @unlink($csv);
        }
    }

    public function test_regular_period_pins_exact_published_schedule_versions(): void
    {
        $maker = User::factory()->create();
        $checker = User::factory()->create();
        $importer = app(GovernmentContributionTableImportService::class);
        $publisher = app(GovernmentScheduleVersionService::class);
        $pins = app(GovernmentSchedulePinService::class);

        foreach (ContributionAgency::cases() as $agency) {
            $csv = $this->csv('100');
            try {
                $result = $importer->importFromPath(
                    $agency,
                    $csv,
                    "{$agency->label()} contribution schedule",
                    'https://example.gov/schedule',
                    $maker->id,
                );
                $version = GovernmentScheduleVersion::query()->findOrFail($result['version_id']);
                $publisher->publish($version, $checker, completenessConfirmed: true);
            } finally {
                @unlink($csv);
            }
        }

        $period = PayrollPeriod::factory()->create([
            'period_start' => '2026-01-01',
            'period_end' => '2026-01-15',
            'payroll_date' => '2026-01-15',
            'is_first_half' => true,
            'is_thirteenth_month' => false,
        ]);

        $pinned = $pins->pinForPeriod($period);
        $this->assertCount(4, $pinned);
        $this->assertSame(4, $period->governmentSchedulePins()->count());
        $this->assertSame(
            ['bir', 'pagibig', 'philhealth', 'sss'],
            $period->governmentSchedulePins()->orderBy('agency')->pluck('agency')->map(
                static fn (ContributionAgency $agency): string => $agency->value,
            )->all(),
        );

        $originalSssVersionId = (int) $pinned['sss']->government_schedule_version_id;
        $correctionCsv = $this->csv('150');
        try {
            $correction = $importer->importFromPath(
                ContributionAgency::Sss,
                $correctionCsv,
                'Corrected SSS schedule',
                'https://www.sss.gov.ph/contribution-schedule',
                $maker->id,
            );
            $publisher->publish(
                GovernmentScheduleVersion::query()->findOrFail($correction['version_id']),
                $checker,
                completenessConfirmed: true,
            );
        } finally {
            @unlink($correctionCsv);
        }

        $stillPinned = $pins->pinForPeriod($period);
        $this->assertSame($originalSssVersionId, (int) $stillPinned['sss']->government_schedule_version_id);
        $this->assertSame('100.0000', (string) app(GovernmentContributionTableService::class)
            ->bracketsForVersion($originalSssVersionId, ContributionAgency::Sss)
            ->firstOrFail()->ee_amount);
    }
}
