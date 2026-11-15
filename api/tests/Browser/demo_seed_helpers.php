<?php

declare(strict_types=1);

use App\Common\DemoData\DemoSideEffectScope;
use App\Common\Support\DemoSeedingGuard;
use Database\Seeders\DemoAccountSeeder;
use Illuminate\Support\Facades\DB;

/**
 * Transitional browser-test adapter for the shared demo-account seeder.
 * Each caller has already selected a disposable, uniquely named E2E database.
 */
function seedBrowserDemoAccounts(): void
{
    if (! app()->environment(['local', 'testing'])) {
        throw new RuntimeException('Browser demo fixtures may only seed in local or testing environments.');
    }

    $database = DB::connection()->getDatabaseName();
    if (preg_match('/\Aogami_test_[a-z0-9_]+\z/i', $database) !== 1) {
        throw new RuntimeException('Browser demo fixtures require a disposable ogami_test_* database.');
    }

    $previousEnabled = config('demo.seeding.enabled');
    $previousAllowlist = config('demo.seeding.database_allowlist');
    $previousEnabledEnvironment = getenv('DEMO_SEEDING_ENABLED');
    $previousAllowlistEnvironment = getenv('DEMO_SEEDING_DATABASE_ALLOWLIST');
    putenv('DEMO_SEEDING_ENABLED=true');
    putenv('DEMO_SEEDING_DATABASE_ALLOWLIST='.$database);
    $_ENV['DEMO_SEEDING_ENABLED'] = 'true';
    $_SERVER['DEMO_SEEDING_ENABLED'] = 'true';
    $_ENV['DEMO_SEEDING_DATABASE_ALLOWLIST'] = $database;
    $_SERVER['DEMO_SEEDING_DATABASE_ALLOWLIST'] = $database;
    config([
        'demo.seeding.enabled' => true,
        'demo.seeding.database_allowlist' => [$database],
    ]);

    try {
        app(DemoSideEffectScope::class)->run(static function (): void {
            app(DemoSeedingGuard::class)->assertAllowed();
            app(DemoAccountSeeder::class)->run();
        });
    } finally {
        config([
            'demo.seeding.enabled' => $previousEnabled,
            'demo.seeding.database_allowlist' => $previousAllowlist,
        ]);
        restoreEnvironment('DEMO_SEEDING_ENABLED', $previousEnabledEnvironment, $_ENV, $_SERVER);
        restoreEnvironment('DEMO_SEEDING_DATABASE_ALLOWLIST', $previousAllowlistEnvironment, $_ENV, $_SERVER);
    }
}

/** @param array<string, mixed> $environment @param array<string, mixed> $server */
function restoreEnvironment(string $key, string|false $value, array &$environment, array &$server): void
{
    if ($value === false) {
        putenv($key);
        unset($environment[$key], $server[$key]);

        return;
    }

    putenv($key.'='.$value);
    $environment[$key] = $value;
    $server[$key] = $value;
}
