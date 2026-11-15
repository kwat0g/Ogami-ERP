<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Services;

use App\Common\Support\Money;
use InvalidArgumentException;

/** Applies a stable, cent-precise cap across an already-prioritized request list. */
class DeductionAllocationService
{
    /**
     * @param  list<array{key:int|string,requested:string}>  $requests
     * @return list<array{key:int|string,requested:string,withheld:string,deferred:string}>
     */
    public function allocate(string $available, array $requests): array
    {
        $remaining = Money::round2($available);
        if (Money::lt($remaining, '0.00')) {
            $remaining = Money::zero();
        }

        $allocations = [];
        $seen = [];
        foreach ($requests as $request) {
            $key = (string) $request['key'];
            if (isset($seen[$key])) {
                throw new InvalidArgumentException('A deduction allocation request may appear only once.');
            }
            $seen[$key] = true;

            $requested = Money::round2($request['requested']);
            if (Money::lt($requested, '0.00')) {
                throw new InvalidArgumentException('A deduction allocation request cannot be negative.');
            }
            $withheld = Money::lt($remaining, $requested) ? $remaining : $requested;
            $deferred = Money::sub($requested, $withheld);

            $allocations[] = [
                'key' => $request['key'],
                'requested' => $requested,
                'withheld' => $withheld,
                'deferred' => $deferred,
            ];
            $remaining = Money::sub($remaining, $withheld);
        }

        return $allocations;
    }
}
