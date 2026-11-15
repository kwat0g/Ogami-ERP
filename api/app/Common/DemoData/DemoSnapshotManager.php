<?php

declare(strict_types=1);

namespace App\Common\DemoData;

use App\Common\Support\DemoSeedingGuard;
use RuntimeException;
use Symfony\Component\Process\Process;
use Throwable;

final class DemoSnapshotManager
{
    public function __construct(private readonly DemoSeedingGuard $guard) {}

    /** @return array<string, mixed> */
    public function create(?string $requestedFilename = null): array
    {
        $this->guard->assertAllowed();
        $target = $this->connectionTarget();
        $directory = $this->ensureDirectory();
        $filename = $this->snapshotFilename($requestedFilename, $target['database']);
        $path = $directory.DIRECTORY_SEPARATOR.$filename;
        $manifestPath = $this->manifestPath($path);

        if (file_exists($path) || file_exists($manifestPath)) {
            throw new RuntimeException("Snapshot file {$filename} already exists; choose a new filename.");
        }

        $process = new Process([
            (string) config('demo.snapshot.pg_dump_binary', 'pg_dump'),
            '--format=custom',
            '--no-owner',
            '--no-privileges',
            '--file='.$path,
            '--host='.$target['host'],
            '--port='.$target['port'],
            '--username='.$target['username'],
            '--dbname='.$target['database'],
        ], null, $this->processEnvironment($target), null, 1800);

        try {
            $process->run();
        } catch (Throwable $exception) {
            throw new RuntimeException('Could not start pg_dump for the allowlisted demo database.', previous: $exception);
        }

        if (! $process->isSuccessful() || ! is_file($path) || filesize($path) === 0) {
            @unlink($path);
            throw new RuntimeException('pg_dump failed to produce a non-empty custom-format snapshot (exit '.$process->getExitCode().').');
        }

        $checksum = hash_file('sha256', $path);
        if (! is_string($checksum)) {
            @unlink($path);
            throw new RuntimeException('Could not calculate the demo snapshot checksum.');
        }

        $manifest = [
            'format' => 'custom',
            'database' => $target['database'],
            'snapshot' => $filename,
            'created_at' => now('UTC')->toIso8601String(),
            'sha256' => $checksum,
        ];

        try {
            $json = json_encode($manifest, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
        } catch (Throwable $exception) {
            @unlink($path);
            throw new RuntimeException('Could not encode the snapshot manifest.', previous: $exception);
        }

        if (file_put_contents($manifestPath, $json.PHP_EOL, LOCK_EX) === false) {
            @unlink($path);
            throw new RuntimeException('Could not write the snapshot manifest.');
        }

        return $manifest;
    }

    /** @return array<string, mixed> */
    public function restore(string $requestedFilename): array
    {
        $this->guard->assertAllowed();
        $target = $this->connectionTarget();
        $directory = $this->ensureDirectory();
        $filename = $this->snapshotFilename($requestedFilename, $target['database']);
        $path = $directory.DIRECTORY_SEPARATOR.$filename;
        $resolvedPath = realpath($path);

        if ($resolvedPath === false || ! str_starts_with($resolvedPath, $directory.DIRECTORY_SEPARATOR)) {
            throw new RuntimeException("Demo snapshot {$filename} was not found in the configured snapshot directory.");
        }

        $manifestPath = $this->manifestPath($resolvedPath);
        if (! is_file($manifestPath)) {
            throw new RuntimeException("Demo snapshot {$filename} has no manifest.");
        }

        try {
            $manifest = json_decode((string) file_get_contents($manifestPath), true, 512, JSON_THROW_ON_ERROR);
        } catch (Throwable $exception) {
            throw new RuntimeException("Demo snapshot {$filename} has an invalid manifest.", previous: $exception);
        }

        if (! is_array($manifest)
            || ($manifest['format'] ?? null) !== 'custom'
            || ($manifest['snapshot'] ?? null) !== $filename
            || ($manifest['database'] ?? null) !== $target['database']) {
            throw new RuntimeException('Snapshot manifest does not match the current allowlisted demo database.');
        }

        $checksum = hash_file('sha256', $resolvedPath);
        if (! is_string($checksum) || ! hash_equals((string) ($manifest['sha256'] ?? ''), $checksum)) {
            throw new RuntimeException('Demo snapshot checksum does not match its manifest.');
        }

        $process = new Process([
            (string) config('demo.snapshot.pg_restore_binary', 'pg_restore'),
            '--clean',
            '--if-exists',
            '--no-owner',
            '--no-privileges',
            '--exit-on-error',
            '--single-transaction',
            '--host='.$target['host'],
            '--port='.$target['port'],
            '--username='.$target['username'],
            '--dbname='.$target['database'],
            $resolvedPath,
        ], null, $this->processEnvironment($target), null, 1800);

        try {
            $process->run();
        } catch (Throwable $exception) {
            throw new RuntimeException('Could not start pg_restore for the allowlisted demo database.', previous: $exception);
        }

        if (! $process->isSuccessful()) {
            throw new RuntimeException('pg_restore failed with exit code '.$process->getExitCode().'.');
        }

        return $manifest;
    }

    /** @return array{driver: string, database: string, host: string, port: string, username: string, password: string, sslmode: string} */
    private function connectionTarget(): array
    {
        $connectionName = (string) config('database.default');
        $connection = config("database.connections.{$connectionName}", []);
        if (! is_array($connection) || ($connection['driver'] ?? null) !== 'pgsql') {
            throw new RuntimeException('Demo snapshots require the PostgreSQL connection.');
        }

        $database = (string) ($connection['database'] ?? '');
        $host = (string) ($connection['host'] ?? '');
        $port = (string) ($connection['port'] ?? '5432');
        $username = (string) ($connection['username'] ?? '');
        $password = (string) ($connection['password'] ?? '');

        if (is_string($connection['url'] ?? null) && $connection['url'] !== '') {
            $parts = parse_url($connection['url']);
            if (! is_array($parts)) {
                throw new RuntimeException('The configured PostgreSQL URL is invalid.');
            }

            $database = rawurldecode(ltrim((string) ($parts['path'] ?? ''), '/'));
            $host = (string) ($parts['host'] ?? '');
            $port = (string) ($parts['port'] ?? $port);
            $username = rawurldecode((string) ($parts['user'] ?? $username));
            $password = rawurldecode((string) ($parts['pass'] ?? $password));
        }

        if ($database === '' || $host === '' || $username === '') {
            throw new RuntimeException('PostgreSQL connection host, database and username must be configured.');
        }

        return [
            'driver' => 'pgsql',
            'database' => $database,
            'host' => $host,
            'port' => $port,
            'username' => $username,
            'password' => $password,
            'sslmode' => (string) ($connection['sslmode'] ?? ''),
        ];
    }

    /** @param array<string, string> $target */
    private function processEnvironment(array $target): array
    {
        $environment = ['PGPASSWORD' => $target['password']];
        if ($target['sslmode'] !== '') {
            $environment['PGSSLMODE'] = $target['sslmode'];
        }

        return $environment;
    }

    private function ensureDirectory(): string
    {
        $configured = (string) config('demo.snapshot.directory', '');
        $directory = rtrim($configured !== '' ? $configured : storage_path('app/demo-snapshots'), DIRECTORY_SEPARATOR);

        if ($directory === '' || (! is_dir($directory) && ! @mkdir($directory, 0770, true) && ! is_dir($directory))) {
            throw new RuntimeException('Could not create the configured demo snapshot directory.');
        }

        $resolved = realpath($directory);
        if ($resolved === false || ! is_writable($resolved)) {
            throw new RuntimeException('The configured demo snapshot directory is not writable.');
        }

        return $resolved;
    }

    private function snapshotFilename(?string $requestedFilename, string $database): string
    {
        $filename = $requestedFilename;
        if ($filename === null || trim($filename) === '') {
            $filename = 'ogami-demo-'.$database.'-'.now('UTC')->format('Ymd_His').'.dump';
        }

        if (basename($filename) !== $filename || preg_match('/\A[a-zA-Z0-9][a-zA-Z0-9._-]*\.dump\z/', $filename) !== 1) {
            throw new RuntimeException('Snapshot filename must be a simple .dump filename without a path.');
        }

        return $filename;
    }

    private function manifestPath(string $snapshotPath): string
    {
        return $snapshotPath.'.manifest.json';
    }
}
