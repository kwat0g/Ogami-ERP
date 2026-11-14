<?php

declare(strict_types=1);

namespace App\Common\DemoData;

use RuntimeException;

final class DemoResetGate
{
    public function assertReady(): void
    {
        if (config('demo.reset_ready') !== true) {
            throw new RuntimeException('Demo reset is disabled until the complete dataset is ready.');
        }
    }
}
