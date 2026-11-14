<?php

declare(strict_types=1);

namespace App\Common\Support;

use RuntimeException;

final class DemoSeedingGuard
{
    /**
     * Stop demo writes unless an explicit opt-in, local host, and exact DB
     * allowlist are configured. Local browser tests may use ogami_test_* names.
     */
    public function assertAllowed(): void
    {
        $environment = (string) app()->environment();

        if ($environment === 'production') {
            throw new RuntimeException('Demo data must never be seeded in production.');
        }

        if (! in_array($environment, ['local', 'testing', 'demo'], true)) {
            throw new RuntimeException('Demo data may only be seeded in local, testing, or demo environments.');
        }

        if (config('demo.seeding.enabled') !== true) {
            throw new RuntimeException('Demo seeding is disabled. Set DEMO_SEEDING_ENABLED=true explicitly.');
        }

        [$database, $host] = $this->connectionTarget();

        $prefixes = match ($environment) {
            'testing' => ['ogami_test'],
            'local' => ['ogami_demo', 'ogami_test'],
            default => ['ogami_demo'],
        };
        if (! $this->hasOneOfPrefixes($database, $prefixes)) {
            throw new RuntimeException("Demo seeding in {$environment} must use an ".implode(' or ', $prefixes).' database.');
        }

        $allowlist = config('demo.seeding.database_allowlist', []);
        if (! is_array($allowlist) || ! in_array($database, $allowlist, true)) {
            throw new RuntimeException("Database {$database} is not explicitly allowlisted for demo seeding.");
        }

        $allowedHosts = config('demo.seeding.allowed_hosts', []);
        if (! is_array($allowedHosts)) {
            $allowedHosts = [];
        }

        $normalizedHost = strtolower(trim($host, '[]'));
        $normalizedHosts = array_map(
            static fn ($value): string => strtolower(trim((string) $value, '[]')),
            $allowedHosts,
        );

        if ($normalizedHost === '' || ! in_array($normalizedHost, $normalizedHosts, true)) {
            throw new RuntimeException("Database host {$host} is not an approved local database host.");
        }
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function connectionTarget(): array
    {
        $name = (string) config('database.default');
        $connection = config("database.connections.{$name}", []);
        if (! is_array($connection)) {
            $connection = [];
        }

        $database = (string) ($connection['database'] ?? '');
        $host = (string) ($connection['host'] ?? '');

        $url = $connection['url'] ?? null;
        if (is_string($url) && $url !== '') {
            $parts = parse_url($url);
            if (! is_array($parts)) {
                throw new RuntimeException('The configured database URL is invalid for demo seeding.');
            }

            $database = rawurldecode(ltrim((string) ($parts['path'] ?? ''), '/'));
            $host = (string) ($parts['host'] ?? '');
        }

        return [$database, $host];
    }

    private function hasPrefix(string $database, string $prefix): bool
    {
        return $database === $prefix || str_starts_with($database, $prefix.'_');
    }

    /** @param list<string> $prefixes */
    private function hasOneOfPrefixes(string $database, array $prefixes): bool
    {
        foreach ($prefixes as $prefix) {
            if ($this->hasPrefix($database, $prefix)) {
                return true;
            }
        }

        return false;
    }
}
