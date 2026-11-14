<?php

declare(strict_types=1);

namespace Tests\Feature\Demo;

use App\Common\DemoData\DemoSeedContext;
use App\Common\DemoData\DemoSeedOptions;
use App\Common\DemoData\DemoSeedRunner;
use App\Common\Services\SettingsService;
use App\Modules\Auth\Models\Role;
use App\Modules\Auth\Models\User;
use App\Modules\HR\Models\Employee;
use App\Modules\HR\Models\EmployeeOnboarding;
use App\Modules\HR\Models\EmploymentHistory;
use Carbon\CarbonImmutable;
use Database\Seeders\Demo\WorkforceIdentityLayer;
use Database\Seeders\LeaveTypeSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SettingsSeeder;
use Database\Seeders\ShiftSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class WorkforceIdentityLayerTest extends TestCase
{
    use RefreshDatabase;

    private const DEMO_PASSWORD = 'Demo-Only-Pass-2026!';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([
            RolePermissionSeeder::class,
            SettingsSeeder::class,
            ShiftSeeder::class,
            LeaveTypeSeeder::class,
        ]);
        $connectionName = (string) config('database.default');
        $databaseHost = (string) config("database.connections.{$connectionName}.host");
        config([
            'demo.credentials.password' => self::DEMO_PASSWORD,
            'demo.seeding.enabled' => true,
            'demo.seeding.database_allowlist' => ['ogami_test'],
            'demo.seeding.allowed_hosts' => [$databaseHost],
        ]);
    }

    public function test_it_seeds_safe_small_workforce_accounts_and_service_history_idempotently(): void
    {
        $options = $this->seedOptions('small');
        $runner = app(DemoSeedRunner::class);
        $runner->run($options);

        $this->assertSame(20, Employee::query()->count());
        $this->assertSame(20, User::query()->count());
        $this->assertSame(20, EmploymentHistory::query()->count());
        $this->assertSame(20, EmployeeOnboarding::query()->count());
        $this->assertSame(20, EmployeeOnboarding::query()->whereNotNull('account_provisioned_at')->count());
        $this->assertSame(20, DB::table('event_outbox')->where('event_type', 'App\\Modules\\HR\\Events\\EmployeeCreated')->count());
        $this->assertSame(17, Role::query()->count());

        foreach (Employee::query()->with('user.role')->get() as $employee) {
            $this->assertStringEndsWith('.ogami-demo.test', (string) strrchr((string) $employee->email, '@'));
            $this->assertMatchesRegularExpression('/\A\+63 000 555 [0-9]{4}\z/', (string) $employee->mobile_number);
            $this->assertNull($employee->sss_no);
            $this->assertNull($employee->philhealth_no);
            $this->assertNull($employee->pagibig_no);
            $this->assertNull($employee->tin);
            $this->assertNull($employee->bank_account_no);
            $this->assertNull($employee->basic_monthly_salary);
            $this->assertNotNull($employee->user);
            $this->assertSame('2026-11-14', $employee->user->created_at->toDateString());
            $this->assertTrue(Hash::check(self::DEMO_PASSWORD, $employee->user->password));
            $this->assertFalse($employee->user->must_change_password);
        }

        $employeeNumbers = Employee::query()->orderBy('employee_no')->pluck('employee_no')->all();
        $userEmails = User::query()->orderBy('email')->pluck('email')->all();
        $shiftAssignments = DB::table('employee_shift_assignments')->count();

        $runner->run($options);

        $this->assertSame(20, Employee::query()->count());
        $this->assertSame(20, User::query()->count());
        $this->assertSame(20, EmploymentHistory::query()->count());
        $this->assertSame(20, EmployeeOnboarding::query()->count());
        $this->assertSame(20, DB::table('event_outbox')->where('event_type', 'App\\Modules\\HR\\Events\\EmployeeCreated')->count());
        $this->assertSame($employeeNumbers, Employee::query()->orderBy('employee_no')->pluck('employee_no')->all());
        $this->assertSame($userEmails, User::query()->orderBy('email')->pluck('email')->all());
        $this->assertSame($shiftAssignments, DB::table('employee_shift_assignments')->count());
        $this->assertTrue(app(SettingsService::class)->requiredBool('hr.auto_provision_user.enabled'));
    }

    public function test_it_plans_the_approved_roster_sizes_for_every_scale(): void
    {
        $layer = app(WorkforceIdentityLayer::class);

        foreach (['small' => 20, 'demo' => 200, 'full' => 240] as $scale => $expectedCount) {
            $profiles = $layer->plannedProfiles(new DemoSeedContext($this->seedOptions($scale)));

            $this->assertCount($expectedCount, $profiles, "{$scale} scale must match the approved headcount.");
            $this->assertCount($expectedCount, array_unique(array_column($profiles, 'email_address')));
            $this->assertSame($scale === 'small' ? 4 : 25, count(array_filter(
                $profiles,
                static fn (array $profile): bool => $profile['payroll_persona'] !== null,
            )));
        }
    }

    public function test_missing_shared_demo_password_fails_before_employee_or_account_creation(): void
    {
        config(['demo.credentials.password' => '']);

        try {
            app(DemoSeedRunner::class)->run($this->seedOptions('small'));
            $this->fail('The workforce layer should require a configured shared demo password.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('DEMO_ACCOUNT_PASSWORD', $exception->getMessage());
        }

        $this->assertSame(0, Employee::query()->count());
        $this->assertSame(0, User::query()->count());
        $this->assertTrue(app(SettingsService::class)->requiredBool('hr.auto_provision_user.enabled'));
    }

    private function seedOptions(string $scale): DemoSeedOptions
    {
        return new DemoSeedOptions(
            asOf: CarbonImmutable::parse('2026-11-14', 'Asia/Manila'),
            scale: $scale,
            seed: 20261114,
            only: 'workforce-identities',
        );
    }
}
