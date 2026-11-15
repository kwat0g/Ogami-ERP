<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Services;

use App\Common\Exceptions\BusinessRuleException;
use App\Modules\Payroll\Enums\ContributionAgency;
use App\Modules\Payroll\Enums\GovernmentScheduleVersionStatus;
use App\Modules\Payroll\Models\GovernmentScheduleVersion;
use App\Modules\Payroll\Models\PayrollGovernmentSchedulePin;
use App\Modules\Payroll\Models\PayrollPeriod;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class GovernmentSchedulePinService
{
    /** @return Collection<int, PayrollGovernmentSchedulePin> keyed by agency value */
    public function pinForPeriod(PayrollPeriod $period): Collection
    {
        if (! $period->is_first_half || $period->is_thirteenth_month) {
            return collect();
        }

        return DB::transaction(function () use ($period): Collection {
            $lockedPeriod = PayrollPeriod::query()->lockForUpdate()->findOrFail($period->id);
            $existing = $lockedPeriod->governmentSchedulePins()
                ->with(['scheduleVersion' => fn ($query) => $query->withCount('contributionRows')])
                ->lockForUpdate()
                ->get();

            if ($existing->isNotEmpty()) {
                $byAgency = $existing->keyBy(fn (PayrollGovernmentSchedulePin $pin): string => $pin->agency->value);
                $missing = array_values(array_diff(ContributionAgency::values(), $byAgency->keys()->all()));
                if ($missing !== [] || $existing->count() !== count(ContributionAgency::values())) {
                    throw new BusinessRuleException(sprintf(
                        'Payroll period has an incomplete or invalid statutory schedule pin. Missing: %s. This period requires review before recomputation.',
                        $missing === [] ? 'none' : implode(', ', $missing),
                    ));
                }

                foreach ($byAgency as $agency => $pin) {
                    $version = $pin->scheduleVersion;
                    if (! $version
                        || $version->agency?->value !== $agency
                        || $version->effective_date?->toDateString() !== $pin->effective_date?->toDateString()
                        || $pin->effective_date?->gt($lockedPeriod->payroll_date)
                        || ! $version->completeness_confirmed
                        || ! preg_match('/^[a-f0-9]{64}$/', (string) $version->source_file_sha256)
                        || (int) $version->contribution_rows_count !== (int) $version->row_count
                        || ! in_array($version->status, [
                            GovernmentScheduleVersionStatus::Published,
                            GovernmentScheduleVersionStatus::Superseded,
                        ], true)) {
                        throw new BusinessRuleException('Payroll period has an invalid statutory schedule pin. This period requires review.');
                    }
                }

                return $byAgency;
            }

            if ($lockedPeriod->payrolls()->exists()) {
                throw new BusinessRuleException(
                    'This payroll already has computed rows but no statutory schedule snapshot. It must be reviewed through the payroll correction process before recomputation.',
                );
            }

            $missing = [];
            $selected = [];
            foreach (ContributionAgency::cases() as $agency) {
                $version = GovernmentScheduleVersion::query()
                    ->published()
                    ->withCount('contributionRows')
                    ->where('agency', $agency->value)
                    ->whereDate('effective_date', '<=', $lockedPeriod->payroll_date->toDateString())
                    ->orderByDesc('effective_date')
                    ->orderByDesc('version_number')
                    ->lockForUpdate()
                    ->first();

                if (! $version
                    || (int) $version->row_count < 1
                    || (int) $version->contribution_rows_count !== (int) $version->row_count) {
                    $missing[] = $agency->label();

                    continue;
                }

                $selected[$agency->value] = $version;
            }

            if ($missing !== []) {
                throw new BusinessRuleException(sprintf(
                    'Payroll computation is blocked because no reviewed, published %s schedule is effective on or before %s. Import the source schedule and have a different authorized reviewer publish it.',
                    implode(', ', $missing),
                    $lockedPeriod->payroll_date->format('Y-m-d'),
                ));
            }

            $now = now();
            foreach ($selected as $agency => $version) {
                PayrollGovernmentSchedulePin::query()->create([
                    'payroll_period_id' => $lockedPeriod->id,
                    'agency' => $agency,
                    'government_schedule_version_id' => $version->id,
                    'effective_date' => $version->effective_date->toDateString(),
                    'created_at' => $now,
                ]);
            }

            return $lockedPeriod->governmentSchedulePins()
                ->with('scheduleVersion')
                ->get()
                ->keyBy(fn (PayrollGovernmentSchedulePin $pin): string => $pin->agency->value);
        });
    }
}
