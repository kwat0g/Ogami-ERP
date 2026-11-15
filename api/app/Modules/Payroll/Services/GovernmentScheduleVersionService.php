<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Services;

use App\Common\Exceptions\BusinessRuleException;
use App\Modules\Auth\Models\User;
use App\Modules\Payroll\Enums\GovernmentScheduleVersionStatus;
use App\Modules\Payroll\Models\GovernmentScheduleVersion;
use Illuminate\Support\Facades\DB;

class GovernmentScheduleVersionService
{
    public function __construct(
        private readonly GovernmentContributionTableService $tables,
        private readonly GovernmentTableRealtimeService $realtime,
    ) {}

    public function publish(
        GovernmentScheduleVersion $version,
        User $publisher,
        bool $completenessConfirmed,
    ): GovernmentScheduleVersion {
        return DB::transaction(function () use ($version, $publisher, $completenessConfirmed): GovernmentScheduleVersion {
            $identity = GovernmentScheduleVersion::query()->findOrFail($version->id);
            $sameEffectiveDate = GovernmentScheduleVersion::query()
                ->where('agency', $identity->agency->value)
                ->whereDate('effective_date', $identity->effective_date->toDateString())
                ->orderBy('id')
                ->lockForUpdate()
                ->get();
            $locked = $sameEffectiveDate->firstWhere('id', $identity->id);
            if (! $locked) {
                throw new BusinessRuleException('Schedule version changed while it was being reviewed. Reload the schedule history.');
            }

            if ($locked->status === GovernmentScheduleVersionStatus::Published) {
                return $locked;
            }
            if ($locked->status !== GovernmentScheduleVersionStatus::Draft) {
                throw new BusinessRuleException('Only an imported draft schedule version can be published.');
            }
            if ($locked->imported_by !== null && (int) $locked->imported_by === (int) $publisher->id) {
                throw new BusinessRuleException('A different authorized reviewer must publish this imported schedule.');
            }
            if (! $completenessConfirmed) {
                throw new BusinessRuleException('Confirm that the full schedule matches its cited source before publishing.');
            }
            if (trim((string) $locked->source_title) === ''
                || ! str_starts_with(strtolower((string) $locked->source_url), 'https://')
                || ! preg_match('/^[a-f0-9]{64}$/', (string) $locked->source_file_sha256)) {
                throw new BusinessRuleException('This draft is missing required source evidence and cannot be published.');
            }

            $rows = $locked->contributionRows()->lockForUpdate()->get();
            if ($rows->isEmpty() || $rows->count() !== (int) $locked->row_count) {
                throw new BusinessRuleException('The imported schedule rows do not match the recorded row count. Reimport the source file.');
            }

            foreach ($rows as $row) {
                if ($row->agency?->value !== $locked->agency?->value
                    || $row->effective_date?->toDateString() !== $locked->effective_date?->toDateString()) {
                    throw new BusinessRuleException('Schedule rows do not match their version agency and effective date.');
                }
            }

            $sortedRows = $rows->sort(static fn ($left, $right): int => bccomp(
                (string) $left->bracket_min,
                (string) $right->bracket_min,
                2,
            ))->values();
            for ($index = 1; $index < $sortedRows->count(); $index++) {
                $previous = $sortedRows[$index - 1];
                $current = $sortedRows[$index];
                if (bccomp((string) $current->bracket_min, (string) $previous->bracket_max, 2) <= 0) {
                    throw new BusinessRuleException('This draft contains overlapping brackets. Reimport a corrected source file.');
                }
            }

            $now = now();
            GovernmentScheduleVersion::query()
                ->where('agency', $locked->agency->value)
                ->whereDate('effective_date', $locked->effective_date->toDateString())
                ->where('status', GovernmentScheduleVersionStatus::Published->value)
                ->lockForUpdate()
                ->get()
                ->each(function (GovernmentScheduleVersion $prior) use ($locked, $now): void {
                    $prior->forceFill([
                        'status' => GovernmentScheduleVersionStatus::Superseded,
                        'superseded_by_id' => $locked->id,
                        'superseded_at' => $now,
                    ])->save();
                });

            // Draft rows already exist as immutable records; the version state
            // is the only publication switch read by payroll resolution.
            $locked->forceFill([
                'status' => GovernmentScheduleVersionStatus::Published,
                'published_by' => $publisher->id,
                'published_at' => $now,
                'completeness_confirmed' => true,
                'completeness_confirmed_at' => $now,
            ])->save();

            DB::afterCommit(function () use ($locked): void {
                $this->tables->invalidateAgency($locked->agency);
            });
            $this->realtime->stage('published', (string) $locked->version_number);

            return $locked->fresh();
        });
    }
}
