<?php

declare(strict_types=1);

namespace App\Common\DemoData;

use Carbon\CarbonImmutable;
use InvalidArgumentException;

final readonly class DemoSeedOptions
{
    public function __construct(
        public CarbonImmutable $asOf,
        public string $scale = 'demo',
        public int $seed = 20261114,
        public ?string $only = null,
        public bool $noHistory = false,
    ) {
        if (! in_array($scale, ['small', 'demo', 'full'], true)) {
            throw new InvalidArgumentException("Unsupported demo scale: {$scale}");
        }

        if ($only !== null && trim($only) === '') {
            throw new InvalidArgumentException('The layer/module filter cannot be empty.');
        }
    }
}
