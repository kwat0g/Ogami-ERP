<?php

declare(strict_types=1);

use Database\Seeders\Demo\InventoryCatalogLayer;
use Database\Seeders\Demo\OrganizationReferenceDataLayer;

$csv = static function (string $key, string $default = ''): array {
    return array_values(array_filter(array_map(
        static fn (string $value): string => trim($value),
        explode(',', (string) env($key, $default)),
    ), static fn (string $value): bool => $value !== ''));
};

return [
    'seeding' => [
        'enabled' => (bool) env('DEMO_SEEDING_ENABLED', false),
        'database_allowlist' => $csv('DEMO_SEEDING_DATABASE_ALLOWLIST'),
        'allowed_hosts' => $csv('DEMO_SEEDING_ALLOWED_HOSTS', 'localhost,127.0.0.1,::1,db'),
    ],
    'credentials' => [
        'password' => (string) env('DEMO_ACCOUNT_PASSWORD', ''),
    ],
    'snapshot' => [
        'directory' => env('DEMO_SNAPSHOT_DIRECTORY') ?: storage_path('app/demo-snapshots'),
        'pg_dump_binary' => env('DEMO_PG_DUMP_BINARY', 'pg_dump'),
        'pg_restore_binary' => env('DEMO_PG_RESTORE_BINARY', 'pg_restore'),
    ],
    // Enabled only after every required layer exists and demo:verify passes.
    'reset_ready' => false,
    // Reference and transaction layers are added in dependency order as each
    // module group is implemented and verified.
    'layers' => [
        OrganizationReferenceDataLayer::class,
        InventoryCatalogLayer::class,
    ],
];
