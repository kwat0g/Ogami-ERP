<?php

declare(strict_types=1);

namespace Tests\Unit\Common;

use App\Common\DemoData\DemoSnapshotManager;
use Illuminate\Support\Facades\File;
use RuntimeException;
use Tests\TestCase;

class DemoSnapshotManagerTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->directory = sys_get_temp_dir().'/ogami-demo-snapshot-test-'.bin2hex(random_bytes(5));
        File::ensureDirectoryExists($this->directory);

        config([
            'app.env' => 'testing',
            'demo.seeding.enabled' => true,
            'demo.seeding.database_allowlist' => ['ogami_test_snapshot'],
            'demo.seeding.allowed_hosts' => ['127.0.0.1'],
            'demo.snapshot.directory' => $this->directory,
            'database.default' => 'pgsql',
            'database.connections.pgsql.driver' => 'pgsql',
            'database.connections.pgsql.host' => '127.0.0.1',
            'database.connections.pgsql.port' => 5432,
            'database.connections.pgsql.database' => 'ogami_test_snapshot',
            'database.connections.pgsql.username' => 'ogami',
            'database.connections.pgsql.password' => 'demo-only-test-password',
            'database.connections.pgsql.url' => null,
            'demo.snapshot.pg_dump_binary' => $this->fakeProcess('pg-dump', 'dump'),
            'demo.snapshot.pg_restore_binary' => $this->fakeProcess('pg-restore', 'restore'),
        ]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->directory);
        parent::tearDown();
    }

    public function test_it_creates_a_custom_snapshot_and_manifest_without_exposing_passwords(): void
    {
        $result = app(DemoSnapshotManager::class)->create('rehearsal.dump');

        $this->assertFileExists($this->directory.'/rehearsal.dump');
        $this->assertSame('ogami_test_snapshot', $result['database']);
        $this->assertSame(hash_file('sha256', $this->directory.'/rehearsal.dump'), $result['sha256']);
        $this->assertFileExists($this->directory.'/rehearsal.dump.manifest.json');
        $this->assertStringNotContainsString('demo-only-test-password', file_get_contents($this->directory.'/rehearsal.dump.manifest.json'));
    }

    public function test_restore_refuses_a_modified_snapshot(): void
    {
        app(DemoSnapshotManager::class)->create('rehearsal.dump');
        file_put_contents($this->directory.'/rehearsal.dump', 'changed');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('checksum does not match');

        app(DemoSnapshotManager::class)->restore('rehearsal.dump');
    }

    public function test_it_restores_a_snapshot_created_by_the_manager(): void
    {
        app(DemoSnapshotManager::class)->create('rehearsal.dump');

        $manifest = app(DemoSnapshotManager::class)->restore('rehearsal.dump');

        $this->assertSame('ogami_test_snapshot', $manifest['database']);
    }

    private function fakeProcess(string $name, string $mode): string
    {
        $path = $this->directory.'/'.$name;
        $script = $mode === 'dump'
            ? "#!/bin/sh\nfor arg in \"\$@\"; do case \"\$arg\" in --file=*) out=$(printf '%s' \"\$arg\" | cut -c8-); printf 'synthetic demo snapshot' > \"\$out\" ;; esac; done\n"
            : "#!/bin/sh\nexit 0\n";
        file_put_contents($path, $script);
        chmod($path, 0700);

        return $path;
    }
}
