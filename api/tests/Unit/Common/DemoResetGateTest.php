<?php

declare(strict_types=1);

namespace Tests\Unit\Common;

use App\Common\DemoData\DemoResetGate;
use RuntimeException;
use Tests\TestCase;

class DemoResetGateTest extends TestCase
{
    public function test_it_rejects_reset_until_the_complete_dataset_is_ready(): void
    {
        config(['demo.reset_ready' => false]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Demo reset is disabled until the complete dataset is ready.');

        app(DemoResetGate::class)->assertReady();
    }

    public function test_it_allows_reset_after_readiness_is_explicitly_enabled(): void
    {
        config(['demo.reset_ready' => true]);

        app(DemoResetGate::class)->assertReady();

        $this->assertTrue(config('demo.reset_ready'));
    }
}
