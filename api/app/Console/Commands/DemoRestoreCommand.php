<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Common\DemoData\DemoSnapshotManager;
use App\Common\Support\DemoSeedingGuard;
use Illuminate\Console\Command;
use Throwable;

class DemoRestoreCommand extends Command
{
    protected $signature = 'demo:restore
        {--file= : Snapshot filename created by demo:snapshot}';

    protected $description = 'Restore a verified demo snapshot into the same allowlisted demo database.';

    public function handle(DemoSeedingGuard $guard, DemoSnapshotManager $snapshots): int
    {
        try {
            $guard->assertAllowed();
            $filename = $this->option('file');
            if (! is_string($filename) || trim($filename) === '') {
                throw new \InvalidArgumentException('Pass --file=<snapshot.dump> created by demo:snapshot.');
            }

            $manifest = $snapshots->restore($filename);
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info("Demo snapshot restored: {$manifest['snapshot']}.");

        return self::SUCCESS;
    }
}
