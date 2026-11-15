<?php

declare(strict_types=1);

namespace Tests\Support\Payroll;

use App\Common\Support\CacheGeneration;
use Illuminate\Support\Facades\DB;

/** Publishes clearly synthetic schedule versions for payroll math fixtures only. */
final class GovernmentScheduleTestFixturePublisher
{
    public function publishExistingSchedules(): void
    {
        $groups = DB::table('government_contribution_tables as rows')
            ->leftJoin('government_schedule_versions as versions', 'versions.id', '=', 'rows.schedule_version_id')
            ->whereNull('rows.deleted_at')
            ->where('rows.is_active', true)
            ->groupBy('rows.agency', 'rows.effective_date', 'rows.schedule_version_id')
            ->select('rows.agency', 'rows.effective_date', 'rows.schedule_version_id')
            ->selectRaw('COUNT(*) as row_count, MAX(versions.version_number) as existing_version_number')
            ->orderBy('rows.agency')
            ->orderBy('rows.effective_date')
            ->get()
            ->groupBy(fn (object $group): string => $group->agency.'|'.$group->effective_date);

        foreach ($groups as $sameDateGroups) {
            $agency = (string) $sameDateGroups->first()->agency;
            $effectiveDate = (string) $sameDateGroups->first()->effective_date;

            if (DB::table('government_schedule_versions')
                ->where('agency', $agency)
                ->whereDate('effective_date', $effectiveDate)
                ->where('status', 'published')
                ->exists()) {
                continue;
            }

            $sourceGroup = $sameDateGroups->sortByDesc('existing_version_number')->first();
            $rowsQuery = DB::table('government_contribution_tables')
                ->where('agency', $agency)
                ->whereDate('effective_date', $effectiveDate)
                ->where('is_active', true)
                ->whereNull('deleted_at')
                ->orderBy('bracket_min');

            if ($sourceGroup->schedule_version_id === null) {
                $rowsQuery->whereNull('schedule_version_id');
            } else {
                $rowsQuery->where('schedule_version_id', $sourceGroup->schedule_version_id);
            }

            $rows = $rowsQuery->get();
            if ($rows->isEmpty()) {
                continue;
            }

            $csvLines = ['bracket_min,bracket_max,ee_amount,er_amount,effective_date'];
            foreach ($rows as $row) {
                $csvLines[] = implode(',', [
                    $row->bracket_min,
                    $row->bracket_max,
                    $row->ee_amount,
                    $row->er_amount,
                    $effectiveDate,
                ]);
            }
            $csv = implode("\n", $csvLines);
            $now = now();
            $versionNumber = ((int) DB::table('government_schedule_versions')
                ->where('agency', $agency)
                ->whereDate('effective_date', $effectiveDate)
                ->max('version_number')) + 1;

            $versionId = DB::table('government_schedule_versions')->insertGetId([
                'agency' => $agency,
                'effective_date' => $effectiveDate,
                'version_number' => $versionNumber,
                'status' => 'published',
                'source_title' => 'Automated test fixture schedule',
                'source_url' => 'https://example.test/payroll/schedule-fixture',
                'source_file_name' => 'payroll-schedule-fixture.csv',
                'source_file_sha256' => hash('sha256', $csv),
                'row_count' => $rows->count(),
                'imported_at' => $now,
                'published_at' => $now,
                'completeness_confirmed' => true,
                'completeness_confirmed_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            foreach ($rows as $row) {
                DB::table('government_contribution_tables')->insert([
                    'agency' => $agency,
                    'bracket_min' => $row->bracket_min,
                    'bracket_max' => $row->bracket_max,
                    'ee_amount' => $row->ee_amount,
                    'er_amount' => $row->er_amount,
                    'effective_date' => $effectiveDate,
                    'schedule_version_id' => $versionId,
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            CacheGeneration::rotateDefault(
                "gov_table:{$agency}:ver",
                604800,
                'payroll.test_fixture',
            );
        }
    }
}
