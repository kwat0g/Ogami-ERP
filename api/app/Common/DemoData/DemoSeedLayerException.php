<?php

declare(strict_types=1);

namespace App\Common\DemoData;

use RuntimeException;
use Throwable;

final class DemoSeedLayerException extends RuntimeException
{
    public function __construct(
        public readonly string $layer,
        Throwable $previous,
    ) {
        parent::__construct("Demo seed layer {$layer} failed: {$previous->getMessage()}", previous: $previous);
    }
}
