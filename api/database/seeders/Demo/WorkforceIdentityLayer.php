<?php

declare(strict_types=1);

namespace Database\Seeders\Demo;

use App\Common\DemoData\DemoSeedContext;
use App\Common\DemoData\DemoSeeder;
use App\Common\Services\SettingsService;
use App\Modules\Attendance\Models\Shift;
use App\Modules\Auth\Models\Role;
use App\Modules\Auth\Models\User;
use App\Modules\HR\Enums\CivilStatus;
use App\Modules\HR\Enums\EmploymentType;
use App\Modules\HR\Enums\Gender;
use App\Modules\HR\Enums\PayType;
use App\Modules\HR\Models\Department;
use App\Modules\HR\Models\Employee;
use App\Modules\HR\Models\Position;
use App\Modules\HR\Services\DepartmentService;
use App\Modules\HR\Services\EmployeeService;
use App\Modules\HR\Services\OnboardingService;
use Carbon\CarbonImmutable;
use Closure;
use Faker\Factory as FakerFactory;
use Faker\Generator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

/**
 * Creates the employee-linked actors required by approval workflows and the
 * reference workforce used by later attendance/payroll layers.
 *
 * Compensation, government identifiers and bank details are intentionally
 * blank: Phase 1 leaves their authoritative values unresolved.
 */
final class WorkforceIdentityLayer extends DemoSeeder
{
    /** @var list<array{key:string,role:string,email:string,department:string,position:string,first:string,middle:string,last:string,nationality?:string,payroll_persona?:string,pay_type?:string}> */
    private const ROLE_ACTORS = [
        ['key' => 'actor.system-admin', 'role' => 'system_admin', 'email' => 'system.admin@accounts.ogami-demo.test', 'department' => 'IT', 'position' => 'System Administrator', 'first' => 'Noel', 'middle' => 'Andres', 'last' => 'Villanueva'],
        ['key' => 'actor.hr-officer', 'role' => 'hr_officer', 'email' => 'hr.officer@accounts.ogami-demo.test', 'department' => 'HR', 'position' => 'HR Manager', 'first' => 'Bianca', 'middle' => 'Reyes', 'last' => 'Mercado', 'payroll_persona' => 'PAY-24'],
        ['key' => 'actor.finance-officer', 'role' => 'finance_officer', 'email' => 'finance.officer@accounts.ogami-demo.test', 'department' => 'FIN', 'position' => 'Accounting Officer', 'first' => 'Victor', 'middle' => 'Lim', 'last' => 'Tan', 'payroll_persona' => 'PAY-21', 'pay_type' => 'monthly'],
        ['key' => 'actor.vice-president', 'role' => 'vice_president', 'email' => 'vice.president@accounts.ogami-demo.test', 'department' => 'EXEC', 'position' => 'Vice President', 'first' => 'Kenji', 'middle' => 'Hiroshi', 'last' => 'Saito', 'nationality' => 'Japanese', 'payroll_persona' => 'PAY-25'],
        ['key' => 'actor.customer-service', 'role' => 'customer_service_officer', 'email' => 'customer.service@accounts.ogami-demo.test', 'department' => 'CS', 'position' => 'Customer Service Officer', 'first' => 'Marites', 'middle' => 'Joy', 'last' => 'Garcia'],
        ['key' => 'actor.sales-officer', 'role' => 'sales_officer', 'email' => 'sales.officer@accounts.ogami-demo.test', 'department' => 'SALES', 'position' => 'Sales Officer', 'first' => 'Arvin', 'middle' => 'Jose', 'last' => 'Santos'],
        ['key' => 'actor.production-manager', 'role' => 'production_manager', 'email' => 'production.manager@accounts.ogami-demo.test', 'department' => 'PROD', 'position' => 'Production Manager', 'first' => 'Ramon', 'middle' => 'Dela Cruz', 'last' => 'Cruz'],
        ['key' => 'actor.ppc-head', 'role' => 'ppc_head', 'email' => 'ppc.head@accounts.ogami-demo.test', 'department' => 'PPC', 'position' => 'PPC Head', 'first' => 'Gemma', 'middle' => 'Bautista', 'last' => 'Navarro'],
        ['key' => 'actor.purchasing-officer', 'role' => 'purchasing_officer', 'email' => 'purchasing.officer@accounts.ogami-demo.test', 'department' => 'PUR', 'position' => 'Purchasing Officer', 'first' => 'Isabel', 'middle' => 'Mae', 'last' => 'Ramos'],
        ['key' => 'actor.warehouse-staff', 'role' => 'warehouse_staff', 'email' => 'warehouse.staff@accounts.ogami-demo.test', 'department' => 'WH', 'position' => 'Warehouse Staff', 'first' => 'Joel', 'middle' => 'Antonio', 'last' => 'Mendoza'],
        ['key' => 'actor.warehouse-supervisor', 'role' => 'warehouse_staff', 'email' => 'warehouse.supervisor@accounts.ogami-demo.test', 'department' => 'WH', 'position' => 'Warehouse Head', 'first' => 'Rogelio', 'middle' => 'Luis', 'last' => 'Bautista'],
        ['key' => 'actor.qc-inspector', 'role' => 'qc_inspector', 'email' => 'qc.inspector@accounts.ogami-demo.test', 'department' => 'QC', 'position' => 'QC Inspector', 'first' => 'Lorna', 'middle' => 'Mae', 'last' => 'Bautista'],
        ['key' => 'actor.maintenance-tech-1', 'role' => 'maintenance_tech', 'email' => 'maintenance.tech.one@accounts.ogami-demo.test', 'department' => 'MAINT', 'position' => 'Maintenance Technician', 'first' => 'Carlo', 'middle' => 'Miguel', 'last' => 'Dizon'],
        ['key' => 'actor.maintenance-supervisor', 'role' => 'maintenance_supervisor', 'email' => 'maintenance.supervisor@accounts.ogami-demo.test', 'department' => 'MAINT', 'position' => 'Maintenance Head', 'first' => 'Teresita', 'middle' => 'Lopez', 'last' => 'Flores'],
        ['key' => 'actor.impex-officer', 'role' => 'impex_officer', 'email' => 'impex.officer@accounts.ogami-demo.test', 'department' => 'IMPEX', 'position' => 'ImpEx Officer', 'first' => 'Paolo', 'middle' => 'Garcia', 'last' => 'Reyes'],
        ['key' => 'actor.department-head', 'role' => 'department_head', 'email' => 'department.head@accounts.ogami-demo.test', 'department' => 'PROD', 'position' => 'Production Head', 'first' => 'Mila', 'middle' => 'Corazon', 'last' => 'Manalo'],
        ['key' => 'actor.employee', 'role' => 'employee', 'email' => 'employee@accounts.ogami-demo.test', 'department' => 'PROD', 'position' => 'Production Operator', 'first' => 'Ana', 'middle' => 'Mae', 'last' => 'Villanueva', 'payroll_persona' => 'PAY-01', 'pay_type' => 'semi_monthly'],
        ['key' => 'actor.driver', 'role' => 'driver', 'email' => 'driver@accounts.ogami-demo.test', 'department' => 'WH', 'position' => 'Driver', 'first' => 'Renato', 'middle' => 'Santos', 'last' => 'Aquino'],
        ['key' => 'actor.finance-reviewer', 'role' => 'finance_officer', 'email' => 'finance.reviewer@accounts.ogami-demo.test', 'department' => 'FIN', 'position' => 'Accounting Staff', 'first' => 'Alyssa', 'middle' => 'Mae', 'last' => 'Dizon'],
        ['key' => 'actor.purchasing-reviewer', 'role' => 'purchasing_officer', 'email' => 'purchasing.reviewer@accounts.ogami-demo.test', 'department' => 'PUR', 'position' => 'Purchasing Staff', 'first' => 'Mark', 'middle' => 'Anthony', 'last' => 'Ramos'],
        ['key' => 'actor.maintenance-tech-2', 'role' => 'maintenance_tech', 'email' => 'maintenance.tech.two@accounts.ogami-demo.test', 'department' => 'MAINT', 'position' => 'Maintenance Technician', 'first' => 'Joseph', 'middle' => 'Miguel', 'last' => 'Castro'],
    ];

    /** @var array<string, string> */
    private const GENDER_BY_FIRST_NAME = [
        'Noel' => 'male', 'Bianca' => 'female', 'Victor' => 'male', 'Kenji' => 'male',
        'Marites' => 'female', 'Arvin' => 'male', 'Ramon' => 'male', 'Gemma' => 'female',
        'Isabel' => 'female', 'Joel' => 'male', 'Lorna' => 'female', 'Carlo' => 'male',
        'Teresita' => 'female', 'Paolo' => 'male', 'Mila' => 'female', 'Ana' => 'female',
        'Renato' => 'male', 'Alyssa' => 'female', 'Mark' => 'male', 'Joseph' => 'male',
        'Mariel' => 'female', 'Rogelio' => 'male', 'Joanne' => 'female', 'Leandro' => 'male',
        'Luz' => 'female', 'Angela' => 'female', 'Renan' => 'male', 'Josephine' => 'female',
        'Nestor' => 'male', 'Evelyn' => 'female', 'Danilo' => 'male', 'Patricia' => 'female',
        'Diego' => 'male', 'Cora' => 'female', 'Dennis' => 'male', 'Rosemarie' => 'female',
    ];

    /** @var list<array{code:string,first:string,middle:string,last:string,department:string,position:string,pay_type?:string,nationality?:string,hire_days_ago?:int}> */
    private const PAYROLL_PEOPLE = [
        ['code' => 'PAY-01', 'first' => 'Ana', 'middle' => 'Mae', 'last' => 'Villanueva', 'department' => 'PROD', 'position' => 'Production Operator', 'pay_type' => 'semi_monthly'],
        ['code' => 'PAY-02', 'first' => 'Joel', 'middle' => 'Antonio', 'last' => 'Mercado', 'department' => 'PROD', 'position' => 'Production Operator'],
        ['code' => 'PAY-03', 'first' => 'Lorna', 'middle' => 'Isabel', 'last' => 'Bautista', 'department' => 'PROD', 'position' => 'Production Operator'],
        ['code' => 'PAY-04', 'first' => 'Carlo', 'middle' => 'Miguel', 'last' => 'Dizon', 'department' => 'PROD', 'position' => 'Production Operator'],
        ['code' => 'PAY-05', 'first' => 'Mariel', 'middle' => 'Grace', 'last' => 'Santos', 'department' => 'PROD', 'position' => 'Production Operator'],
        ['code' => 'PAY-06', 'first' => 'Rogelio', 'middle' => 'Pascual', 'last' => 'Cruz', 'department' => 'PROD', 'position' => 'Production Operator'],
        ['code' => 'PAY-07', 'first' => 'Joanne', 'middle' => 'Reyes', 'last' => 'Reyes', 'department' => 'PROD', 'position' => 'Production Operator'],
        ['code' => 'PAY-08', 'first' => 'Paolo', 'middle' => 'Andres', 'last' => 'Garcia', 'department' => 'PROD', 'position' => 'Production Operator'],
        ['code' => 'PAY-09', 'first' => 'Leandro', 'middle' => 'Jose', 'last' => 'Mendoza', 'department' => 'PROD', 'position' => 'Production Operator'],
        ['code' => 'PAY-10', 'first' => 'Luz', 'middle' => 'Maria', 'last' => 'Navarro', 'department' => 'PROD', 'position' => 'Production Operator'],
        ['code' => 'PAY-11', 'first' => 'Angela', 'middle' => 'Ramos', 'last' => 'Ramos', 'department' => 'PROD', 'position' => 'Production Operator'],
        ['code' => 'PAY-12', 'first' => 'Renan', 'middle' => 'Flores', 'last' => 'Flores', 'department' => 'PROD', 'position' => 'Production Operator'],
        ['code' => 'PAY-13', 'first' => 'Josephine', 'middle' => 'Castro', 'last' => 'Castro', 'department' => 'PROD', 'position' => 'Production Operator'],
        ['code' => 'PAY-14', 'first' => 'Nestor', 'middle' => 'Ramos', 'last' => 'Villanueva', 'department' => 'PROD', 'position' => 'Production Operator'],
        ['code' => 'PAY-15', 'first' => 'Evelyn', 'middle' => 'Manalo', 'last' => 'Manalo', 'department' => 'PROD', 'position' => 'Production Operator'],
        ['code' => 'PAY-16', 'first' => 'Danilo', 'middle' => 'Reyes', 'last' => 'Reyes', 'department' => 'WH', 'position' => 'Warehouse Staff'],
        ['code' => 'PAY-17', 'first' => 'Patricia', 'middle' => 'Lim', 'last' => 'Lim', 'department' => 'HR', 'position' => 'HR Staff'],
        ['code' => 'PAY-18', 'first' => 'Diego', 'middle' => 'Bautista', 'last' => 'Bautista', 'department' => 'PROD', 'position' => 'Production Operator', 'hire_days_ago' => 8],
        ['code' => 'PAY-19', 'first' => 'Cora', 'middle' => 'Mendoza', 'last' => 'Mendoza', 'department' => 'PROD', 'position' => 'Production Operator'],
        ['code' => 'PAY-20', 'first' => 'Marites', 'middle' => 'Flores', 'last' => 'Flores', 'department' => 'PROD', 'position' => 'Production Operator'],
        ['code' => 'PAY-21', 'first' => 'Victor', 'middle' => 'Lim', 'last' => 'Tan', 'department' => 'FIN', 'position' => 'Accounting Officer'],
        ['code' => 'PAY-22', 'first' => 'Dennis', 'middle' => 'Aquino', 'last' => 'Aquino', 'department' => 'IT', 'position' => 'IT Staff', 'pay_type' => 'monthly'],
        ['code' => 'PAY-23', 'first' => 'Rosemarie', 'middle' => 'Castillo', 'last' => 'Castillo', 'department' => 'QC', 'position' => 'QC Inspector', 'pay_type' => 'semi_monthly'],
        ['code' => 'PAY-24', 'first' => 'Bianca', 'middle' => 'Reyes', 'last' => 'Mercado', 'department' => 'HR', 'position' => 'HR Manager'],
        ['code' => 'PAY-25', 'first' => 'Kenji', 'middle' => 'Hiroshi', 'last' => 'Saito', 'department' => 'EXEC', 'position' => 'Vice President', 'nationality' => 'Japanese'],
    ];

    /** @var array<string, array<string, int>> */
    private const HEADCOUNTS = [
        'small' => ['EXEC' => 1, 'HR' => 1, 'FIN' => 2, 'PROD' => 3, 'QC' => 1, 'WH' => 3, 'PUR' => 2, 'PPC' => 1, 'MAINT' => 3, 'IMPEX' => 1, 'SALES' => 1, 'CS' => 1, 'IT' => 1],
        'demo' => ['EXEC' => 4, 'HR' => 5, 'FIN' => 4, 'PROD' => 100, 'QC' => 16, 'WH' => 16, 'PUR' => 7, 'PPC' => 8, 'MAINT' => 17, 'MOLD' => 6, 'IMPEX' => 2, 'SALES' => 4, 'CS' => 4, 'ADMIN' => 4, 'IT' => 4],
        'full' => ['EXEC' => 5, 'HR' => 6, 'FIN' => 6, 'PROD' => 115, 'QC' => 19, 'WH' => 20, 'PUR' => 8, 'PPC' => 10, 'MAINT' => 21, 'MOLD' => 7, 'IMPEX' => 3, 'SALES' => 5, 'CS' => 6, 'ADMIN' => 5, 'IT' => 5],
    ];

    /** @var array<string, list<string>> */
    private const FILLER_POSITIONS = [
        'EXEC' => ['Chairman', 'President', 'Vice President'],
        'HR' => ['HR Staff', 'Gen Admin Officer'],
        'FIN' => ['Accounting Staff', 'Accounting Officer'],
        'PROD' => ['Production Operator', 'Processing Head', 'Production Head'],
        'QC' => ['QC Inspector', 'Management System Head', 'QC/QA Head'],
        'WH' => ['Warehouse Staff', 'Driver', 'Warehouse Head'],
        'PUR' => ['Purchasing Staff', 'Purchasing Officer'],
        'PPC' => ['PPC Staff', 'PPC Head'],
        'MAINT' => ['Maintenance Technician', 'Maintenance Head'],
        'MOLD' => ['Mold Technician', 'Mold Manager'],
        'IMPEX' => ['ImpEx Staff', 'ImpEx Officer'],
        'SALES' => ['Sales Officer', 'Sales Manager'],
        'CS' => ['Customer Service Officer', 'Customer Service Manager'],
        'ADMIN' => ['Admin Staff'],
        'IT' => ['IT Staff', 'System Administrator'],
    ];

    /** @var array<string, list<string>> */
    private const DEPARTMENT_HEAD_POSITIONS = [
        'EXEC' => ['Vice President'],
        'HR' => ['HR Manager'],
        'FIN' => ['Accounting Officer'],
        'PROD' => ['Production Head', 'Production Manager'],
        'QC' => ['QC/QA Manager', 'QC/QA Head', 'QC Inspector'],
        'WH' => ['Warehouse Head', 'Warehouse Staff'],
        'PUR' => ['Purchasing Officer'],
        'PPC' => ['PPC Head'],
        'MAINT' => ['Maintenance Head'],
        'MOLD' => ['Mold Manager'],
        'IMPEX' => ['ImpEx Officer'],
        'SALES' => ['Sales Manager', 'Sales Officer'],
        'CS' => ['Customer Service Manager', 'Customer Service Officer'],
        'ADMIN' => ['Admin Staff'],
        'IT' => ['System Administrator'],
    ];

    public function __construct(
        private readonly EmployeeService $employees,
        private readonly DepartmentService $departments,
    ) {}

    public function key(): string
    {
        return 'workforce-identities';
    }

    /** @return list<string> */
    public function modules(): array
    {
        return ['HR', 'Auth', 'Attendance', 'Leave'];
    }

    /** @return list<string> */
    public function dependencies(): array
    {
        return ['organization'];
    }

    protected function seedDemoData(DemoSeedContext $context): void
    {
        $password = trim((string) config('demo.credentials.password'));
        if (strlen($password) < 16) {
            throw new RuntimeException('Set DEMO_ACCOUNT_PASSWORD to a shared demo-only password of at least 16 characters.');
        }

        $profiles = $this->plannedProfiles($context);
        $expectedCount = array_sum(self::HEADCOUNTS[$context->options->scale]);
        if (count($profiles) !== $expectedCount) {
            throw new RuntimeException("The {$context->options->scale} workforce catalog resolved to ".count($profiles)." employees; expected {$expectedCount}.");
        }

        $employeesByKey = $this->seedEmployees($context, $profiles);
        $this->seedRoleAccounts($context, $profiles, $employeesByKey, $password);
        $this->assignDepartmentHeads($context, $employeesByKey);
    }

    /** @return list<array<string, mixed>> */
    public function plannedProfiles(DemoSeedContext $context): array
    {
        $profiles = [];
        $personaKeys = [];
        $departmentOrdinals = [];

        foreach (self::ROLE_ACTORS as $actor) {
            $personaCode = $actor['payroll_persona'] ?? null;
            $key = $personaCode === null ? $actor['key'] : 'payroll.'.strtolower($personaCode);
            $profiles[] = $this->profile($context, $key, [
                'department' => $actor['department'],
                'position' => $actor['position'],
                'first' => $actor['first'],
                'middle' => $actor['middle'],
                'last' => $actor['last'],
                'nationality' => $actor['nationality'] ?? 'Filipino',
                'email' => $personaCode === null
                    ? 'identity+'.str_replace('.', '-', $actor['key']).'@people.ogami-demo.test'
                    : 'payroll.'.strtolower($personaCode).'@payroll.ogami-demo.test',
                'pay_type' => $actor['pay_type'] ?? null,
                'payroll_persona' => $personaCode,
                'role_slug' => $actor['role'],
                'role_email' => $actor['email'],
                'actor_key' => $actor['key'],
            ]);
            if ($personaCode !== null) {
                $personaKeys[$personaCode] = true;
            }
            $departmentOrdinals[$actor['department']] = ($departmentOrdinals[$actor['department']] ?? 0) + 1;
        }

        if ($context->options->scale !== 'small') {
            foreach (self::PAYROLL_PEOPLE as $person) {
                if (isset($personaKeys[$person['code']])) {
                    continue;
                }
                $key = 'payroll.'.strtolower($person['code']);
                $profiles[] = $this->profile($context, $key, [
                    'department' => $person['department'],
                    'position' => $person['position'],
                    'first' => $person['first'],
                    'middle' => $person['middle'],
                    'last' => $person['last'],
                    'nationality' => $person['nationality'] ?? 'Filipino',
                    'email' => 'payroll.'.strtolower($person['code']).'@payroll.ogami-demo.test',
                    'pay_type' => $person['pay_type'] ?? null,
                    'payroll_persona' => $person['code'],
                    'hire_days_ago' => $person['hire_days_ago'] ?? null,
                ]);
                $personaKeys[$person['code']] = true;
                $departmentOrdinals[$person['department']] = ($departmentOrdinals[$person['department']] ?? 0) + 1;
            }
        }

        foreach (self::HEADCOUNTS[$context->options->scale] as $departmentCode => $targetCount) {
            $present = $departmentOrdinals[$departmentCode] ?? 0;
            if ($present > $targetCount) {
                throw new RuntimeException("Workforce actors exceed the {$departmentCode} headcount at scale {$context->options->scale}.");
            }

            $positionTitles = self::FILLER_POSITIONS[$departmentCode] ?? null;
            if ($positionTitles === null) {
                throw new RuntimeException("No employee position catalog is defined for department {$departmentCode}.");
            }

            for ($ordinal = 1; $ordinal <= $targetCount - $present; $ordinal++) {
                $key = sprintf('staff.%s.%04d', strtolower($departmentCode), $ordinal);
                $profiles[] = $this->profile($context, $key, [
                    'department' => $departmentCode,
                    'position' => $positionTitles[($ordinal - 1) % count($positionTitles)],
                    'nationality' => 'Filipino',
                    'email' => sprintf('staff-%s-%04d@people.ogami-demo.test', strtolower($departmentCode), $ordinal),
                    'pay_type' => null,
                ]);
            }
        }

        usort($profiles, static fn (array $left, array $right): int => [$left['date_hired'], $left['key']] <=> [$right['date_hired'], $right['key']]);

        return $profiles;
    }

    /** @param array<string, mixed> $attributes
     * @return array<string, mixed>
     */
    private function profile(DemoSeedContext $context, string $key, array $attributes): array
    {
        $faker = $this->fakerFor($context, $key);
        $asOf = $context->options->asOf->setTimezone('Asia/Manila')->startOfDay();
        $hireDate = isset($attributes['hire_days_ago'])
            ? $asOf->subDays((int) $attributes['hire_days_ago'])
            : $asOf->subMonths($faker->randomElement([2, 3, 4, 6, 8, 10, 12, 15, 18, 24, 30, 36, 48, 60, 72, 96, 120, 144, 180]))
                ->subDays($faker->numberBetween(0, 24));
        $hireDate = $this->previousWeekday($hireDate);
        $payType = $attributes['pay_type'] ?? $faker->randomElement([
            PayType::Monthly->value,
            PayType::Monthly->value,
            PayType::Monthly->value,
            PayType::Monthly->value,
            PayType::SemiMonthly->value,
        ]);
        $tenureMonths = max(0, $hireDate->diffInMonths($asOf));
        $employmentType = $tenureMonths < 6
            ? EmploymentType::Probationary->value
            : $faker->randomElement([
                EmploymentType::Regular->value,
                EmploymentType::Regular->value,
                EmploymentType::Regular->value,
                EmploymentType::Regular->value,
                EmploymentType::Contractual->value,
                EmploymentType::ProjectBased->value,
            ]);
        $regularized = $employmentType === EmploymentType::Regular->value && $tenureMonths >= 6
            ? $hireDate->addMonths(6)->toDateString()
            : null;
        $firstName = (string) ($attributes['first'] ?? '');
        $genderValue = self::GENDER_BY_FIRST_NAME[$firstName] ?? null;
        $gender = $genderValue === null
            ? $faker->randomElement(Gender::cases())
            : Gender::from($genderValue);
        $birthAge = $faker->numberBetween(21, 45);
        $home = $this->homeAddress($faker);
        $stableIndex = (int) hexdec(substr(hash('sha256', $context->options->seed.':workforce-index:'.$key), 0, 7));

        return [
            'key' => $key,
            'stable_index' => $stableIndex,
            'department' => (string) $attributes['department'],
            'position' => (string) $attributes['position'],
            'email' => (string) $attributes['email'],
            'first_name' => $firstName !== '' ? $firstName : $faker->firstName($gender->value),
            'middle_name' => (string) ($attributes['middle'] ?? $faker->lastName()),
            'last_name' => (string) ($attributes['last'] ?? $faker->lastName()),
            'gender' => $gender->value,
            'civil_status' => $faker->randomElement(CivilStatus::cases())->value,
            'nationality' => (string) $attributes['nationality'],
            'date_hired' => $hireDate->toDateString(),
            'date_regularized' => $regularized,
            'employment_type' => $employmentType,
            'pay_type' => $payType,
            'birth_date' => $hireDate->subYears($birthAge)->subDays($faker->numberBetween(0, 364))->toDateString(),
            'street_address' => $home['street'],
            'barangay' => $home['barangay'],
            'city' => $home['city'],
            'province' => $home['province'],
            'zip_code' => $home['zip'],
            'mobile_number' => sprintf('+63 000 555 %04d', 1000 + ($stableIndex % 8000)),
            'email_address' => (string) $attributes['email'],
            'emergency_contact_name' => $faker->name(),
            'emergency_contact_relation' => $faker->randomElement(['Parent', 'Spouse', 'Sibling']),
            'emergency_contact_phone' => sprintf('+63 000 555 %04d', 9000 + ($stableIndex % 900)),
            'role_slug' => $attributes['role_slug'] ?? null,
            'role_email' => $attributes['role_email'] ?? null,
            'actor_key' => $attributes['actor_key'] ?? null,
            'payroll_persona' => $attributes['payroll_persona'] ?? null,
        ];
    }

    /** @param list<array<string, mixed>> $profiles
     * @return array<string, Employee>
     */
    private function seedEmployees(DemoSeedContext $context, array $profiles): array
    {
        $employeesByKey = [];
        $shiftIds = $this->shiftIds();

        $this->withoutEmployeeAutoProvisioning(function () use ($context, $profiles, $shiftIds, &$employeesByKey): void {
            foreach (array_chunk($profiles, 25) as $chunk) {
                // Preserve EmployeeService's own transaction and per-hire clock,
                // while committing a small group together. This keeps the 200+
                // roster tractable on the demo database without bypassing the
                // service's lifecycle, sequence, audit or outbox behavior.
                $context->withoutNotifications(function () use ($context, $chunk, $shiftIds, &$employeesByKey): void {
                    $context->at($context->options->asOf, function () use ($context, $chunk, $shiftIds, &$employeesByKey): void {
                        DB::transaction(function () use ($context, $chunk, $shiftIds, &$employeesByKey): void {
                            foreach ($chunk as $profile) {
                                $department = Department::query()->where('code', $profile['department'])->first();
                                if (! $department) {
                                    throw new RuntimeException("Department {$profile['department']} is missing; run the organization layer first.");
                                }
                                $position = Position::query()
                                    ->where('department_id', $department->id)
                                    ->where('title', $profile['position'])
                                    ->first();
                                if (! $position) {
                                    throw new RuntimeException("Position {$profile['position']} in {$profile['department']} is missing; run the organization layer first.");
                                }

                                $existing = Employee::withTrashed()->where('email', $profile['email_address'])->first();
                                if ($existing?->trashed()) {
                                    throw new RuntimeException("Seed-owned employee {$profile['email_address']} is archived; restore it through the employee lifecycle before reseeding.");
                                }

                                $employee = $existing ?? $context->at($profile['date_hired'], fn (): Employee => $this->employees->create([
                                    'first_name' => $profile['first_name'],
                                    'middle_name' => $profile['middle_name'],
                                    'last_name' => $profile['last_name'],
                                    'birth_date' => $profile['birth_date'],
                                    'gender' => $profile['gender'],
                                    'civil_status' => $profile['civil_status'],
                                    'nationality' => $profile['nationality'],
                                    'street_address' => $profile['street_address'],
                                    'barangay' => $profile['barangay'],
                                    'city' => $profile['city'],
                                    'province' => $profile['province'],
                                    'zip_code' => $profile['zip_code'],
                                    'mobile_number' => $profile['mobile_number'],
                                    'email' => $profile['email_address'],
                                    'emergency_contact_name' => $profile['emergency_contact_name'],
                                    'emergency_contact_relation' => $profile['emergency_contact_relation'],
                                    'emergency_contact_phone' => $profile['emergency_contact_phone'],
                                    'department_id' => (int) $department->id,
                                    'position_id' => (int) $position->id,
                                    'employment_type' => $profile['employment_type'],
                                    'pay_type' => $profile['pay_type'],
                                    'date_hired' => $profile['date_hired'],
                                    'date_regularized' => $profile['date_regularized'],
                                    'shift_id' => $this->shiftFor($profile, $shiftIds),
                                ]));

                                $this->assertEmployeeMatchesProfile($employee, $profile, (int) $department->id, (int) $position->id);
                                $employee->setRelation('department', $department);
                                $employee->setRelation('position', $position);
                                $employeesByKey[$profile['key']] = $employee;
                                $context->records()->remember('workforce.employee.'.$profile['key'], $employee);
                                if (is_string($profile['payroll_persona'])) {
                                    $context->records()->remember('workforce.employee.persona.'.strtolower($profile['payroll_persona']), $employee);
                                }
                            }
                        });
                    });
                });
            }
        });

        return $employeesByKey;
    }

    /** @param list<array<string, mixed>> $profiles
     * @param  array<string, Employee>  $employeesByKey
     */
    private function seedRoleAccounts(DemoSeedContext $context, array $profiles, array $employeesByKey, string $password): void
    {
        $context->at($context->options->asOf, function () use ($context, $profiles, $employeesByKey, $password): void {
            $roleAccountNumber = [];

            foreach ($profiles as $profile) {
                if (! is_string($profile['role_slug'])) {
                    continue;
                }

                $role = Role::query()->where('slug', $profile['role_slug'])->first();
                if (! $role) {
                    throw new RuntimeException("Role {$profile['role_slug']} is missing; run RolePermissionSeeder before demo layers.");
                }
                $employee = $employeesByKey[$profile['key']];
                $email = (string) $profile['role_email'];
                $user = User::withTrashed()->where('employee_id', $employee->id)->first();
                $emailOwner = User::withTrashed()->where('email', $email)->first();
                if ($emailOwner && (int) $emailOwner->employee_id !== (int) $employee->id) {
                    throw new RuntimeException("Demo login {$email} is already linked to another employee.");
                }
                if ($user?->trashed() || $emailOwner?->trashed()) {
                    throw new RuntimeException("Seed-owned demo login {$email} is archived; restore it before reseeding.");
                }

                $attributes = [
                    'name' => $employee->full_name,
                    'email' => $email,
                    'role_id' => (int) $role->id,
                    'employee_id' => (int) $employee->id,
                    'is_active' => true,
                    'must_change_password' => false,
                    'password_changed_at' => $context->options->asOf,
                    'failed_login_attempts' => 0,
                    'locked_until' => null,
                ];

                if ($user === null) {
                    $attributes['password'] = $password;
                    $user = new User($attributes);
                    $user->setCreatedAt($context->options->asOf);
                    $user->setUpdatedAt($context->options->asOf);
                    $user->save();
                } else {
                    if (! Hash::check($password, (string) $user->getRawOriginal('password'))) {
                        $attributes['password'] = $password;
                    }
                    $user->fill($attributes);
                    if ($user->isDirty()) {
                        $user->updated_at = $context->options->asOf;
                        $user->save();
                    }
                }

                $number = ($roleAccountNumber[$profile['role_slug']] ?? 0) + 1;
                $roleAccountNumber[$profile['role_slug']] = $number;
                $roleKey = 'workforce.user.role.'.$profile['role_slug'].($number === 1 ? '' : '.'.$number);
                $context->records()->remember($roleKey, $user);
                $context->records()->remember('workforce.user.account.'.strtolower($email), $user);
                $context->withoutNotifications(fn () => app(OnboardingService::class)->recompute($employee));
            }
        });
    }

    /** @param array<string, Employee> $employeesByKey */
    private function assignDepartmentHeads(DemoSeedContext $context, array $employeesByKey): void
    {
        $employeesByDepartment = [];
        foreach ($employeesByKey as $employee) {
            $employeesByDepartment[(string) $employee->department->code][] = $employee;
        }

        foreach (self::DEPARTMENT_HEAD_POSITIONS as $departmentCode => $positionTitles) {
            $department = Department::query()->where('code', $departmentCode)->first();
            if (! $department) {
                continue;
            }
            $head = null;
            foreach ($positionTitles as $title) {
                $head = collect($employeesByDepartment[$departmentCode] ?? [])->first(
                    static fn (Employee $employee): bool => $employee->position?->title === $title,
                );
                if ($head instanceof Employee) {
                    break;
                }
            }
            if (! $head instanceof Employee || (int) $department->head_employee_id === (int) $head->id) {
                continue;
            }

            $context->at($context->options->asOf, fn () => $this->departments->update($department, [
                'head_employee_id' => (int) $head->id,
            ]));
        }
    }

    /** @return array{day:int,night:int,office:int} */
    private function shiftIds(): array
    {
        return [
            'day' => $this->requiredShiftId('Day Shift'),
            'night' => $this->requiredShiftId('Night Shift'),
            'office' => $this->requiredShiftId('Office Hours'),
        ];
    }

    private function requiredShiftId(string $name): int
    {
        $id = (int) (Shift::query()->where('name', $name)->value('id') ?? 0);
        if ($id === 0) {
            throw new RuntimeException("Shift {$name} is missing; run ShiftSeeder before workforce identities.");
        }

        return $id;
    }

    /** @param array<string, mixed> $profile
     * @param  array{day:int,night:int,office:int}  $shiftIds
     */
    private function shiftFor(array $profile, array $shiftIds): int
    {
        if (in_array($profile['department'], ['PROD', 'QC', 'WH', 'MAINT', 'MOLD'], true)) {
            return ((int) $profile['stable_index'] % 5) === 0 ? $shiftIds['night'] : $shiftIds['day'];
        }

        return $shiftIds['office'];
    }

    private function fakerFor(DemoSeedContext $context, string $key): Generator
    {
        $faker = FakerFactory::create((string) config('demo.faker_locale', 'en_PH'));
        $unpacked = unpack('Nseed', substr(hash('sha256', $context->options->seed.':workforce:'.$key, true), 0, 4));
        $faker->seed((int) ($unpacked['seed'] ?? $context->options->seed));

        return $faker;
    }

    private function previousWeekday(CarbonImmutable $date): CarbonImmutable
    {
        while ($date->isWeekend()) {
            $date = $date->subDay();
        }

        return $date;
    }

    /** @return array{street:string,barangay:string,city:string,province:string,zip:string} */
    private function homeAddress(Generator $faker): array
    {
        $localities = [
            ['city' => 'Dasmariñas', 'province' => 'Cavite', 'zip' => '4114'],
            ['city' => 'General Trias', 'province' => 'Cavite', 'zip' => '4107'],
            ['city' => 'Imus', 'province' => 'Cavite', 'zip' => '4103'],
            ['city' => 'Carmona', 'province' => 'Cavite', 'zip' => '4116'],
            ['city' => 'Biñan', 'province' => 'Laguna', 'zip' => '4024'],
            ['city' => 'Santa Rosa', 'province' => 'Laguna', 'zip' => '4026'],
            ['city' => 'Calamba', 'province' => 'Laguna', 'zip' => '4027'],
            ['city' => 'Santo Tomas', 'province' => 'Batangas', 'zip' => '4234'],
            ['city' => 'Lipa', 'province' => 'Batangas', 'zip' => '4217'],
            ['city' => 'Muntinlupa', 'province' => 'Metro Manila', 'zip' => '1770'],
        ];
        $locality = $faker->randomElement($localities);

        return [
            'street' => 'Block '.$faker->numberBetween(1, 28).', Sample Village, Illustrative Road',
            'barangay' => 'Demo Barangay '.$faker->numberBetween(1, 8),
            'city' => $locality['city'],
            'province' => $locality['province'],
            'zip' => $locality['zip'],
        ];
    }

    /** @param array<string, mixed> $profile */
    private function assertEmployeeMatchesProfile(Employee $employee, array $profile, int $departmentId, int $positionId): void
    {
        $expected = [
            'first_name' => $profile['first_name'],
            'middle_name' => $profile['middle_name'],
            'last_name' => $profile['last_name'],
            'department_id' => $departmentId,
            'position_id' => $positionId,
            'date_hired' => $profile['date_hired'],
            'pay_type' => $profile['pay_type'],
        ];
        foreach ($expected as $field => $value) {
            $actual = $employee->getAttribute($field);
            if ($actual instanceof \BackedEnum) {
                $actual = $actual->value;
            } elseif ($actual instanceof CarbonImmutable || $actual instanceof Carbon) {
                $actual = $actual->toDateString();
            } elseif ($field === 'date_hired' && $actual instanceof \DateTimeInterface) {
                $actual = $actual->format('Y-m-d');
            }
            if ((string) ($actual ?? '') !== (string) ($value ?? '')) {
                throw new RuntimeException("Seed-owned employee {$profile['email_address']} differs from its deterministic {$field} value.");
            }
        }
        foreach (['basic_monthly_salary', 'semi_monthly_rate', 'sss_no', 'philhealth_no', 'pagibig_no', 'tin', 'bank_name', 'bank_account_no'] as $field) {
            if ($employee->getAttribute($field) !== null) {
                throw new RuntimeException("Seed-owned employee {$profile['email_address']} has an unresolved {$field} value; refusing to overwrite it.");
            }
        }
    }

    private function withoutEmployeeAutoProvisioning(Closure $action): mixed
    {
        $wasResolved = app()->resolved(SettingsService::class);
        $previous = $wasResolved ? app(SettingsService::class) : null;
        $disabled = new class extends SettingsService
        {
            public function requiredBool(string $key): bool
            {
                return $key === 'hr.auto_provision_user.enabled' ? false : parent::requiredBool($key);
            }
        };
        app()->instance(SettingsService::class, $disabled);

        try {
            return $action();
        } finally {
            if ($wasResolved && $previous instanceof SettingsService) {
                app()->instance(SettingsService::class, $previous);
            } else {
                app()->forgetInstance(SettingsService::class);
            }
        }
    }
}
