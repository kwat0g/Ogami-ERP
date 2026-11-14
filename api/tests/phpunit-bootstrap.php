<?php

declare(strict_types=1);

$database = getenv('DB_DATABASE');
if (! is_string($database) || preg_match('/\Aogami_test(?:_[a-z0-9_]+)?\z/D', $database) !== 1) {
    $database = 'ogami_test';
}

putenv('DB_DATABASE='.$database);
$_ENV['DB_DATABASE'] = $database;
$_SERVER['DB_DATABASE'] = $database;

// Guarded demo seeder tests may write only to this exact test database.
putenv('DEMO_SEEDING_ENABLED=true');
$_ENV['DEMO_SEEDING_ENABLED'] = 'true';
$_SERVER['DEMO_SEEDING_ENABLED'] = 'true';
putenv('DEMO_SEEDING_DATABASE_ALLOWLIST='.$database);
$_ENV['DEMO_SEEDING_DATABASE_ALLOWLIST'] = $database;
$_SERVER['DEMO_SEEDING_DATABASE_ALLOWLIST'] = $database;

require dirname(__DIR__).'/vendor/autoload.php';
