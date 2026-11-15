<?php

declare(strict_types=1);

namespace Tests\Feature\Demo;

use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class DemoSeedCommandTest extends TestCase
{
    public function test_it_refuses_to_run_when_the_demo_opt_in_is_disabled(): void
    {
        config([
            'demo.seeding.enabled' => false,
            'demo.layers' => [],
        ]);

        $exit = Artisan::call('demo:seed');

        $this->assertSame(1, $exit);
        $this->assertStringContainsString('DEMO_SEEDING_ENABLED', Artisan::output());
    }

    public function test_it_does_not_fall_back_to_legacy_seeders_when_no_layers_are_registered(): void
    {
        config([
            'demo.seeding.enabled' => true,
            'demo.seeding.database_allowlist' => ['ogami_test'],
            'demo.layers' => [],
        ]);

        $exit = Artisan::call('demo:seed', ['--as-of' => '2026-11-14']);

        $this->assertSame(1, $exit);
        $this->assertStringContainsString('No demo seed layers are registered', Artisan::output());
        $this->assertStringNotContainsString('ComprehensiveDemoSeeder', Artisan::output());
    }

    public function test_it_rejects_non_iso_as_of_dates(): void
    {
        config([
            'demo.seeding.enabled' => true,
            'demo.seeding.database_allowlist' => ['ogami_test'],
            'demo.layers' => [],
        ]);

        $exit = Artisan::call('demo:seed', ['--as-of' => '11/14/2026']);

        $this->assertSame(1, $exit);
        $this->assertStringContainsString('--as-of must use YYYY-MM-DD', Artisan::output());
    }

    public function test_reset_refuses_to_drop_the_database_until_layers_exist(): void
    {
        config([
            'demo.seeding.enabled' => true,
            'demo.seeding.database_allowlist' => ['ogami_test'],
            'demo.layers' => [],
        ]);

        $exit = Artisan::call('demo:reset');

        $this->assertSame(1, $exit);
        $this->assertStringContainsString('No demo seed layers are registered', Artisan::output());
    }

    public function test_snapshot_restore_and_credentials_commands_share_the_write_guard(): void
    {
        config([
            'demo.seeding.enabled' => false,
            'demo.layers' => [],
        ]);

        foreach (['demo:snapshot', 'demo:restore', 'demo:credentials'] as $command) {
            try {
                Artisan::call($command);
                $output = Artisan::output();
            } catch (\Throwable $exception) {
                $this->fail("{$command} must be registered and fail through the safety guard: {$exception->getMessage()}");
            }

            $this->assertStringContainsString('DEMO_SEEDING_ENABLED', $output, $command);
        }
    }
}
