<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Common\DemoData\DemoSnapshotManager;
use App\Common\Support\DemoSeedingGuard;
use Illuminate\Console\Command;
use Throwable;

class DemoSnapshotCommand extends Command
{
    protected $signature = 'demo:snapshot
        {--file= : Optional .dump filename (must not include a path)}';

    protected $description = 'Create a guarded PostgreSQL custom-format demo snapshot and checksum manifest.';

    public function handle(DemoSeedingGuard $guard, DemoSnapshotManager $snapshots): int
    {
        try {
            $guard->assertAllowed();
            $filename = $this->option('file');
            $manifest = $snapshots->create(is_string($filename) ? $filename : null);
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info("Demo snapshot written: {$manifest['snapshot']} (SHA-256 {$manifest['sha256']}).");

        return self::SUCCESS;
    }
}
