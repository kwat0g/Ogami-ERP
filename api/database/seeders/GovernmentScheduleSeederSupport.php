<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\Payroll\Models\GovernmentContributionTable;
use Illuminate\Support\Facades\DB;

/** Keeps demo/seed schedules explicitly unverified and avoids rewriting versions. */
final class GovernmentScheduleSeederSupport
{
    /** @param array<string, mixed> $identity @param array<string, mixed> $values */
    public static function upsertUnversioned(array $identity, array $values): void
    {
        $query = GovernmentContributionTable::query();
        foreach ($identity as $key => $value) {
            $query->where($key, $value);
        }

        $row = $query->first();
        if ($row?->schedule_version_id !== null) {
            return;
        }
        if ($row) {
            $row->fill($values)->save();

            return;
        }

        GovernmentContributionTable::query()->create([...$identity, ...$values]);
    }

    public static function markUnverifiedRows(): void
    {
        $groups = DB::table('government_contribution_tables')
            ->whereNull('schedule_version_id')
            ->whereNull('deleted_at')
            ->select('agency', 'effective_date')
            ->selectRaw('COUNT(*) as row_count')
            ->groupBy('agency', 'effective_date')
            ->get();

        foreach ($groups as $group) {
            $versionNumber = ((int) DB::table('government_schedule_versions')
                ->where('agency', $group->agency)
                ->whereDate('effective_date', $group->effective_date)
                ->max('version_number')) + 1;
            $versionId = DB::table('government_schedule_versions')->insertGetId([
                'agency' => $group->agency,
                'effective_date' => $group->effective_date,
                'version_number' => $versionNumber,
                'status' => 'legacy_unverified',
                'row_count' => $group->row_count,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('government_contribution_tables')
                ->where('agency', $group->agency)
                ->whereDate('effective_date', $group->effective_date)
                ->whereNull('schedule_version_id')
                ->whereNull('deleted_at')
                ->update(['schedule_version_id' => $versionId]);
        }
    }
}
