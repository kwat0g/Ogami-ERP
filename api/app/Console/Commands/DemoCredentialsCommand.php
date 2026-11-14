<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Common\Support\DemoSeedingGuard;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

class DemoCredentialsCommand extends Command
{
    protected $signature = 'demo:credentials';

    protected $description = 'List reserved-domain demo accounts and the configured shared demo password.';

    public function handle(DemoSeedingGuard $guard): int
    {
        try {
            $guard->assertAllowed();
            $accounts = $this->accounts();
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        if ($accounts === []) {
            $this->warn('No .test-domain demo accounts were found in this database.');

            return self::SUCCESS;
        }

        $this->table(['Role / portal', 'Name', 'Email'], $accounts);

        $password = (string) config('demo.credentials.password', '');
        if ($password === '') {
            $this->warn('DEMO_ACCOUNT_PASSWORD is not configured; no password is displayed.');
        } else {
            $this->line('Shared demo password: '.$password);
        }

        return self::SUCCESS;
    }

    /** @return list<array{string, string, string}> */
    private function accounts(): array
    {
        $accounts = [];

        if (Schema::hasTable('users') && Schema::hasTable('roles')) {
            foreach (DB::table('users as u')
                ->leftJoin('roles as r', 'r.id', '=', 'u.role_id')
                ->select(['r.slug as role', 'u.name', 'u.email'])
                ->orderBy('r.slug')
                ->orderBy('u.id')
                ->cursor() as $row) {
                if (is_string($row->email) && str_ends_with(strtolower($row->email), '.test')) {
                    $accounts[] = [(string) ($row->role ?? 'unassigned'), (string) $row->name, $row->email];
                }
            }
        }

        foreach ([
            'supplier_portal_users' => 'supplier_portal',
            'customer_portal_users' => 'customer_portal',
        ] as $table => $role) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            foreach (DB::table($table)->select(['name', 'email'])->orderBy('id')->cursor() as $row) {
                if (is_string($row->email) && str_ends_with(strtolower($row->email), '.test')) {
                    $accounts[] = [$role, (string) $row->name, $row->email];
                }
            }
        }

        return $accounts;
    }
}
