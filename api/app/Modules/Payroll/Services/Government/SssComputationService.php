<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Services\Government;

use App\Modules\Payroll\Enums\ContributionAgency;
use App\Modules\Payroll\Services\GovernmentContributionTableService;
use Illuminate\Support\Carbon;

/**
 * SSS bracket lookup. Returns flat peso employee, employer, and EC amounts
 * for a given monthly salary basis.
 *
 * Brackets store flat amounts (no rates).
 */
class SssComputationService
{
    public function __construct(private readonly GovernmentContributionTableService $tables) {}

    /**
     * @param  string|float|int  $monthlySalary  Pesos (string preferred for precision).
     * @return array{ee: string, er: string, ec: string}
     */
    public function compute(string|float|int $monthlySalary, ?Carbon $effectiveDate = null, ?int $scheduleVersionId = null): array
    {
        $salary = (string) $monthlySalary;
        if (bccomp($salary, '0', 2) <= 0) {
            return ['ee' => '0.00', 'er' => '0.00', 'ec' => '0.00'];
        }

        $brackets = $scheduleVersionId === null
            ? $this->tables->bracketsEffectiveOn(ContributionAgency::Sss, $effectiveDate ?? now())
            : $this->tables->bracketsForVersion($scheduleVersionId, ContributionAgency::Sss);

        foreach ($brackets as $row) {
            $min = (string) $row->bracket_min;
            $max = (string) $row->bracket_max;

            // bracket_min ≤ salary ≤ bracket_max
            if (bccomp($salary, $min, 2) >= 0 && bccomp($salary, $max, 2) <= 0) {
                return [
                    'ee' => bcadd((string) $row->ee_amount, '0', 2),
                    'er' => bcadd((string) $row->er_amount, '0', 2),
                    'ec' => bcadd((string) $row->ec_amount, '0', 2),
                ];
            }
        }

        // Salary above the highest bracket — use the last bracket's amount.
        $last = $brackets->last();
        if ($last) {
            return [
                'ee' => bcadd((string) $last->ee_amount, '0', 2),
                'er' => bcadd((string) $last->er_amount, '0', 2),
                'ec' => bcadd((string) $last->ec_amount, '0', 2),
            ];
        }

        return ['ee' => '0.00', 'er' => '0.00', 'ec' => '0.00'];
    }
}
