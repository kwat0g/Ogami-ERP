<?php

declare(strict_types=1);

namespace Tests\Unit\Common;

use App\Common\Support\DemoSeedingGuard;
use RuntimeException;
use Tests\TestCase;

class DemoSeedingGuardTest extends TestCase
{
    private function configureGuard(
        string $environment,
        string $database,
        array $allowlist,
        string $host = 'localhost',
        bool $enabled = true,
    ): void {
        $this->app->detectEnvironment(fn (): string => $environment);
        config([
            'app.env' => $environment,
            'demo.seeding.enabled' => $enabled,
            'demo.seeding.database_allowlist' => $allowlist,
            'database.default' => 'pgsql',
            'database.connections.pgsql.driver' => 'pgsql',
            'database.connections.pgsql.database' => $database,
            'database.connections.pgsql.host' => $host,
            'database.connections.pgsql.url' => null,
        ]);
    }

    public function test_it_allows_an_explicit_local_demo_database(): void
    {
        $this->configureGuard('local', 'ogami_demo_thesis', ['ogami_demo_thesis'], 'db');

        app(DemoSeedingGuard::class)->assertAllowed();

        $this->assertTrue(true);
    }

    public function test_it_allows_an_explicit_test_database_for_a_local_browser_fixture(): void
    {
        $this->configureGuard('local', 'ogami_test_o2c_browser_abc123', ['ogami_test_o2c_browser_abc123'], 'db');

        app(DemoSeedingGuard::class)->assertAllowed();

        $this->assertTrue(true);
    }

    public function test_it_requires_the_explicit_environment_flag(): void
    {
        $this->configureGuard('local', 'ogami_demo_thesis', ['ogami_demo_thesis'], enabled: false);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('DEMO_SEEDING_ENABLED');

        app(DemoSeedingGuard::class)->assertAllowed();
    }

    public function test_it_requires_an_exact_database_allowlist_entry(): void
    {
        $this->configureGuard('local', 'ogami_demo_thesis', ['ogami_demo']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('not explicitly allowlisted');

        app(DemoSeedingGuard::class)->assertAllowed();
    }

    public function test_it_never_allows_the_application_database_name(): void
    {
        $this->configureGuard('local', 'ogami', ['ogami']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('must use an ogami_demo or ogami_test database');

        app(DemoSeedingGuard::class)->assertAllowed();
    }

    public function test_it_rejects_non_local_database_hosts(): void
    {
        $this->configureGuard('local', 'ogami_demo_thesis', ['ogami_demo_thesis'], 'erp-db.company.test');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('not an approved local database host');

        app(DemoSeedingGuard::class)->assertAllowed();
    }

    public function test_it_only_allows_the_explicit_test_database_in_testing(): void
    {
        $this->configureGuard('testing', 'ogami_test', ['ogami_test'], 'db');

        app(DemoSeedingGuard::class)->assertAllowed();

        $this->assertTrue(true);
    }

    public function test_it_refuses_production_even_when_every_flag_matches(): void
    {
        $this->configureGuard('production', 'ogami_demo_thesis', ['ogami_demo_thesis'], 'db');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('never be seeded in production');

        app(DemoSeedingGuard::class)->assertAllowed();
    }
}
