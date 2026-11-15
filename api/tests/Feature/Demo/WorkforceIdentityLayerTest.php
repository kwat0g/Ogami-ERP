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
use App\Modules\HR\Models\ProfileUpdateRequest;
use App\Modules\HR\Models\SalaryAdjustment;
use Carbon\CarbonImmutable;
use Database\Seeders\Demo\WorkforceIdentityLayer;
use Database\Seeders\LeaveTypeSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SettingsSeeder;
use Database\Seeders\ShiftSeeder;
use Database\Seeders\WorkflowSeeder;
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
            WorkflowSeeder::class,
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

        $this->assertSame(21, Employee::query()->count());
        $this->assertSame(21, User::query()->count());
        $this->assertSame(21, EmploymentHistory::query()->count());
        $this->assertSame(21, EmployeeOnboarding::query()->count());
        $this->assertSame(21, EmployeeOnboarding::query()->whereNotNull('account_provisioned_at')->count());
        $this->assertSame(21, DB::table('event_outbox')->where('event_type', 'App\\Modules\\HR\\Events\\EmployeeCreated')->count());
        $this->assertSame(17, Role::query()->count());

        $mobileNumbers = [];
        $emergencyNumbers = [];
        foreach (Employee::query()->with('user.role')->get() as $employee) {
            $this->assertStringEndsWith('@gmail.com', (string) $employee->email);
            $this->assertSame($employee->email, $employee->user->email);
            $this->assertDoesNotMatchRegularExpression('/\ADemo Barangay /', (string) $employee->barangay);
            $this->assertMatchesRegularExpression('/\A09000000[0-9]{3}\z/', (string) $employee->mobile_number);
            $this->assertMatchesRegularExpression('/\A09000001[0-9]{3}\z/', (string) $employee->emergency_contact_phone);
            $mobileNumbers[] = $employee->mobile_number;
            $emergencyNumbers[] = $employee->emergency_contact_phone;
            $this->assertMatchesRegularExpression('/^999[0-9]{7}$/', (string) $employee->sss_no);
            $this->assertMatchesRegularExpression('/^999[0-9]{9}$/', (string) $employee->philhealth_no);
            $this->assertMatchesRegularExpression('/^998[0-9]{9}$/', (string) $employee->pagibig_no);
            $this->assertMatchesRegularExpression('/^997[0-9]{9}$/', (string) $employee->tin);
            $this->assertContains($employee->bank_name, [
                'BDO Unibank', 'Bank of the Philippine Islands', 'Metrobank', 'Land Bank of the Philippines',
                'Security Bank', 'UnionBank of the Philippines', 'Rizal Commercial Banking Corporation',
                'Philippine National Bank',
            ]);
            $this->assertMatchesRegularExpression('/\A[0-9]{12}\z/', (string) $employee->bank_account_no);
            $this->assertGreaterThan(0, (float) $employee->monthlyEquivalentSalary(), $employee->email.' must have a positive demo pay basis.');
            if ($employee->pay_type->value === 'monthly') {
                $this->assertGreaterThan(0, (float) $employee->basic_monthly_salary);
                $this->assertNull($employee->semi_monthly_rate);
            } else {
                $this->assertGreaterThan(0, (float) $employee->semi_monthly_rate);
                $this->assertNull($employee->basic_monthly_salary);
            }
            foreach ([
                'first_name', 'last_name', 'birth_date', 'gender', 'civil_status', 'nationality',
                'street_address', 'city', 'province', 'mobile_number', 'email',
                'emergency_contact_name', 'emergency_contact_phone', 'department_id',
                'position_id', 'employment_type', 'pay_type', 'date_hired',
            ] as $field) {
                $value = $employee->getAttribute($field);
                $this->assertNotNull($value, $employee->email.' is missing '.$field.'.');
                if ($value instanceof \BackedEnum) {
                    $value = $value->value;
                } elseif ($value instanceof \DateTimeInterface) {
                    $value = $value->format('Y-m-d');
                }
                $this->assertNotSame('', trim((string) $value), $employee->email.' has blank '.$field.'.');
            }
            $this->assertTrue(DB::table('employee_shift_assignments')->where('employee_id', $employee->id)->exists(), $employee->email.' must have a shift assignment.');
            $this->assertNotNull($employee->user);
            $this->assertSame('2026-11-14', $employee->user->created_at->toDateString());
            $this->assertTrue(Hash::check(self::DEMO_PASSWORD, $employee->user->password));
            $this->assertFalse($employee->user->must_change_password);
        }
        $this->assertCount(21, array_unique($mobileNumbers));
        $this->assertCount(21, array_unique($emergencyNumbers));

        $pay01Profile = collect(app(WorkforceIdentityLayer::class)->plannedProfiles(new DemoSeedContext($options)))
            ->firstWhere('payroll_persona', 'PAY-01');
        $this->assertNotNull($pay01Profile);
        $minimumWagePersona = Employee::query()->where('email', $pay01Profile['email_address'])->firstOrFail();
        $this->assertSame('16824.00', $minimumWagePersona->monthlyEquivalentSalary());
        $this->assertSame('8412.00', $minimumWagePersona->semi_monthly_rate);
        $this->assertContains('18000.00', Employee::query()->get()->map(fn (Employee $employee): string => (string) $employee->monthlyEquivalentSalary())->all());
        $this->assertContains('24000.00', Employee::query()->get()->map(fn (Employee $employee): string => (string) $employee->monthlyEquivalentSalary())->all());
        $this->assertContains('28000.00', Employee::query()->get()->map(fn (Employee $employee): string => (string) $employee->monthlyEquivalentSalary())->all());
        $this->assertContains('42000.00', Employee::query()->get()->map(fn (Employee $employee): string => (string) $employee->monthlyEquivalentSalary())->all());
        $this->assertContains('60000.00', Employee::query()->get()->map(fn (Employee $employee): string => (string) $employee->monthlyEquivalentSalary())->all());
        $this->assertContains('120000.00', Employee::query()->get()->map(fn (Employee $employee): string => (string) $employee->monthlyEquivalentSalary())->all());

        $employeeNumbers = Employee::query()->orderBy('employee_no')->pluck('employee_no')->all();
        $userEmails = User::query()->orderBy('email')->pluck('email')->all();
        $shiftAssignments = DB::table('employee_shift_assignments')->count();

        $runner->run($options);

        $this->assertSame(21, Employee::query()->count());
        $this->assertSame(21, User::query()->count());
        $this->assertSame(21, EmploymentHistory::query()->count());
        $this->assertSame(21, EmployeeOnboarding::query()->count());
        $this->assertSame(21, DB::table('event_outbox')->where('event_type', 'App\\Modules\\HR\\Events\\EmployeeCreated')->count());
        $this->assertSame($employeeNumbers, Employee::query()->orderBy('employee_no')->pluck('employee_no')->all());
        $this->assertSame($userEmails, User::query()->orderBy('email')->pluck('email')->all());
        $this->assertSame($shiftAssignments, DB::table('employee_shift_assignments')->count());
        $this->assertTrue(app(SettingsService::class)->requiredBool('hr.auto_provision_user.enabled'));
    }

    public function test_it_fills_missing_sensitive_profile_fields_with_demo_values_and_finance_approval(): void
    {
        app(DemoSeedRunner::class)->run($this->seedOptions('small'));

        $employees = Employee::query()->with('user')->orderBy('id')->get();
        $this->assertCount(21, $employees);
        foreach ($employees as $employee) {
            $this->assertMatchesRegularExpression('/^999[0-9]{7}$/', (string) $employee->sss_no);
            $this->assertMatchesRegularExpression('/^999[0-9]{9}$/', (string) $employee->philhealth_no);
            $this->assertMatchesRegularExpression('/^998[0-9]{9}$/', (string) $employee->pagibig_no);
            $this->assertMatchesRegularExpression('/^997[0-9]{9}$/', (string) $employee->tin);
            $this->assertContains($employee->bank_name, [
                'BDO Unibank', 'Bank of the Philippine Islands', 'Metrobank', 'Land Bank of the Philippines',
                'Security Bank', 'UnionBank of the Philippines', 'Rizal Commercial Banking Corporation',
                'Philippine National Bank',
            ]);
            $this->assertMatchesRegularExpression('/\A[0-9]{12}\z/', (string) $employee->bank_account_no);
            $this->assertGreaterThan(0, (float) $employee->monthlyEquivalentSalary());
        }

        $requests = ProfileUpdateRequest::query()->orderBy('id')->get();
        $this->assertCount(21, $requests);
        $this->assertTrue($requests->every(fn (ProfileUpdateRequest $request): bool => $request->status === 'approved'
            && $request->requires_finance
            && $request->reviewed_by !== null
            && $request->finance_reviewed_by !== null
            && (int) $request->reviewed_by !== (int) $request->finance_reviewed_by
            && str_starts_with((string) $request->note, 'Bank details update for employee ')));

        $requestIds = $requests->modelKeys();
        $legacyRequest = $requests->firstOrFail();
        $legacyRequest->update(['note' => 'DEMO-WORKFORCE-BANK:'.$legacyRequest->employee_id]);
        $preserved = $employees->firstOrFail();
        $preserved->forceFill([
            'sss_no' => 'LEGACY-SSS-KEEP',
            'philhealth_no' => 'LEGACY-PHILHEALTH-KEEP',
            'pagibig_no' => 'LEGACY-PAGIBIG-KEEP',
            'tin' => 'LEGACY-TIN-KEEP',
            'bank_name' => 'LEGACY BANK KEEP',
            'bank_account_no' => 'LEGACY-ACCOUNT-KEEP',
            'basic_monthly_salary' => null,
            'semi_monthly_rate' => null,
        ])->save();
        $existingPay = $employees->first(fn (Employee $employee): bool => (int) $employee->id !== (int) $preserved->id);
        $this->assertNotNull($existingPay);
        $legacyPay = $existingPay->pay_type->value === 'monthly'
            ? ['basic_monthly_salary' => '31234.56', 'semi_monthly_rate' => null]
            : ['basic_monthly_salary' => null, 'semi_monthly_rate' => '15617.28'];
        $existingPay->forceFill($legacyPay)->save();

        app(DemoSeedRunner::class)->run($this->seedOptions('small'));
        $this->assertSame($requestIds, ProfileUpdateRequest::query()->orderBy('id')->pluck('id')->all());
        $preserved->refresh();
        $this->assertSame('LEGACY-SSS-KEEP', $preserved->sss_no);
        $this->assertSame('LEGACY-PHILHEALTH-KEEP', $preserved->philhealth_no);
        $this->assertSame('LEGACY-PAGIBIG-KEEP', $preserved->pagibig_no);
        $this->assertSame('LEGACY-TIN-KEEP', $preserved->tin);
        $this->assertSame('LEGACY BANK KEEP', $preserved->bank_name);
        $this->assertSame('LEGACY-ACCOUNT-KEEP', $preserved->bank_account_no);
        $this->assertGreaterThan(0, (float) $preserved->monthlyEquivalentSalary());
        $existingPay->refresh();
        $this->assertSame($legacyPay['basic_monthly_salary'], $existingPay->basic_monthly_salary);
        $this->assertSame($legacyPay['semi_monthly_rate'], $existingPay->semi_monthly_rate);
        $adjustment = SalaryAdjustment::query()->where('employee_id', $preserved->id)->firstOrFail();
        $this->assertSame('approved', $adjustment->status->value);
        $this->assertNotNull($adjustment->applied_at);
        $this->assertSame(1, SalaryAdjustment::query()->where('employee_id', $preserved->id)->count());
        $approvalRecords = $adjustment->approvalRecords()->get();
        $this->assertSame(['production_manager', 'vice_president'], $approvalRecords->pluck('role_slug')->all());
        $this->assertTrue($approvalRecords->every(fn ($record): bool => $record->action === 'approved' && $record->approver_id !== null));
    }

    public function test_it_plans_the_approved_roster_sizes_for_every_scale(): void
    {
        $layer = app(WorkforceIdentityLayer::class);

        foreach (['small' => 21, 'demo' => 241, 'full' => 241] as $scale => $expectedCount) {
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
