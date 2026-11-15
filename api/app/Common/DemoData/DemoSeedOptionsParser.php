<?php

declare(strict_types=1);

namespace App\Common\DemoData;

use Carbon\CarbonImmutable;
use InvalidArgumentException;
use Throwable;

final class DemoSeedOptionsParser
{
    public function parse(
        ?string $asOf,
        string $scale,
        string $seed,
        ?string $only = null,
        bool $noHistory = false,
    ): DemoSeedOptions {
        $date = $asOf === null || trim($asOf) === ''
            ? CarbonImmutable::now('Asia/Manila')->format('Y-m-d')
            : trim($asOf);

        try {
            $parsed = CarbonImmutable::createFromFormat('!Y-m-d', $date, 'Asia/Manila');
        } catch (Throwable $exception) {
            throw new InvalidArgumentException('--as-of must use YYYY-MM-DD and be a valid date.', previous: $exception);
        }

        $dateErrors = CarbonImmutable::getLastErrors();
        if ($parsed === false
            || $parsed->format('Y-m-d') !== $date
            || ($dateErrors !== false && ($dateErrors['warning_count'] > 0 || $dateErrors['error_count'] > 0))) {
            throw new InvalidArgumentException('--as-of must use YYYY-MM-DD and be a valid date.');
        }

        if (preg_match('/\A-?\d+\z/', $seed) !== 1) {
            throw new InvalidArgumentException('--seed must be an integer.');
        }

        return new DemoSeedOptions(
            asOf: $parsed,
            scale: $scale,
            seed: (int) $seed,
            only: $only === null || trim($only) === '' ? null : trim($only),
            noHistory: $noHistory,
        );
    }
}
