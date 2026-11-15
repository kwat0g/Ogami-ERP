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
use App\Modules\HR\Models\ProfileUpdateRequest;
use App\Modules\HR\Models\Position;
use App\Modules\HR\Models\SalaryAdjustment;
use App\Modules\HR\Services\DepartmentService;
use App\Modules\HR\Services\EmployeeService;
use App\Modules\HR\Services\OnboardingService;
use App\Modules\HR\Services\ProfileUpdateRequestService;
use App\Modules\HR\Services\SalaryAdjustmentService;
use Carbon\CarbonImmutable;
use Closure;
use Faker\Factory as FakerFactory;
use Faker\Generator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Creates the employee-linked actors required by approval workflows and the
 * reference workforce used by later attendance/payroll layers.
 *
 * Demo compensation uses the explicitly documented regional wage floor and
 * synthetic position tiers. Government IDs and bank accounts use deterministic
 * synthetic values; bank updates go through the HR/Finance flow.
 */
final class WorkforceIdentityLayer extends DemoSeeder
{
    /** @var list<string> */
    private const SYNTHETIC_BANK_NAMES = [
        'BDO Unibank',
        'Bank of the Philippine Islands',
        'Metrobank',
        'Land Bank of the Philippines',
        'Security Bank',
        'UnionBank of the Philippines',
        'Rizal Commercial Banking Corporation',
        'Philippine National Bank',
    ];

    /** @var list<array{key:string,role:string,email:string,department:string,position:string,first:string,middle:string,last:string,nationality?:string,payroll_persona?:string,pay_type?:string}> */
    private const ROLE_ACTORS = [
        ['key' => 'actor.system-admin', 'role' => 'system_admin', 'email' => 'villanueva.noel@gmail.com', 'department' => 'IT', 'position' => 'System Administrator', 'first' => 'Noel', 'middle' => 'Andres', 'last' => 'Villanueva'],
        ['key' => 'actor.hr-officer', 'role' => 'hr_officer', 'email' => 'mercado.bianca@gmail.com', 'department' => 'HR', 'position' => 'HR Manager', 'first' => 'Bianca', 'middle' => 'Reyes', 'last' => 'Mercado', 'payroll_persona' => 'PAY-24'],
        ['key' => 'actor.finance-officer', 'role' => 'finance_officer', 'email' => 'tan.victor@gmail.com', 'department' => 'FIN', 'position' => 'Accounting Officer', 'first' => 'Victor', 'middle' => 'Lim', 'last' => 'Tan', 'payroll_persona' => 'PAY-21', 'pay_type' => 'monthly'],
        ['key' => 'actor.vice-president', 'role' => 'vice_president', 'email' => 'saito.kenji@gmail.com', 'department' => 'EXEC', 'position' => 'Vice President', 'first' => 'Kenji', 'middle' => 'Hiroshi', 'last' => 'Saito', 'nationality' => 'Japanese', 'payroll_persona' => 'PAY-25'],
        ['key' => 'actor.customer-service', 'role' => 'customer_service_officer', 'email' => 'garcia.marites@gmail.com', 'department' => 'CS', 'position' => 'Customer Service Officer', 'first' => 'Marites', 'middle' => 'Joy', 'last' => 'Garcia'],
        ['key' => 'actor.sales-officer', 'role' => 'sales_officer', 'email' => 'santos.arvin@gmail.com', 'department' => 'SALES', 'position' => 'Sales Officer', 'first' => 'Arvin', 'middle' => 'Jose', 'last' => 'Santos'],
        ['key' => 'actor.production-manager', 'role' => 'production_manager', 'email' => 'cruz.ramon@gmail.com', 'department' => 'PROD', 'position' => 'Production Manager', 'first' => 'Ramon', 'middle' => 'Dela Cruz', 'last' => 'Cruz'],
        ['key' => 'actor.ppc-head', 'role' => 'ppc_head', 'email' => 'navarro.gemma@gmail.com', 'department' => 'PPC', 'position' => 'PPC Head', 'first' => 'Gemma', 'middle' => 'Bautista', 'last' => 'Navarro'],
        ['key' => 'actor.purchasing-officer', 'role' => 'purchasing_officer', 'email' => 'ramos.isabel@gmail.com', 'department' => 'PUR', 'position' => 'Purchasing Officer', 'first' => 'Isabel', 'middle' => 'Mae', 'last' => 'Ramos'],
        ['key' => 'actor.warehouse-staff', 'role' => 'warehouse_staff', 'email' => 'mendoza.joel@gmail.com', 'department' => 'WH', 'position' => 'Warehouse Staff', 'first' => 'Joel', 'middle' => 'Antonio', 'last' => 'Mendoza'],
        ['key' => 'actor.warehouse-supervisor', 'role' => 'warehouse_staff', 'email' => 'bautista.rogelio@gmail.com', 'department' => 'WH', 'position' => 'Warehouse Head', 'first' => 'Rogelio', 'middle' => 'Luis', 'last' => 'Bautista'],
        ['key' => 'actor.qc-inspector', 'role' => 'qc_inspector', 'email' => 'bautista.lorna@gmail.com', 'department' => 'QC', 'position' => 'QC Inspector', 'first' => 'Lorna', 'middle' => 'Mae', 'last' => 'Bautista'],
        ['key' => 'actor.maintenance-tech-1', 'role' => 'maintenance_tech', 'email' => 'dizon.carlo@gmail.com', 'department' => 'MAINT', 'position' => 'Maintenance Technician', 'first' => 'Carlo', 'middle' => 'Miguel', 'last' => 'Dizon'],
        ['key' => 'actor.maintenance-supervisor', 'role' => 'maintenance_supervisor', 'email' => 'flores.teresita@gmail.com', 'department' => 'MAINT', 'position' => 'Maintenance Head', 'first' => 'Teresita', 'middle' => 'Lopez', 'last' => 'Flores'],
        ['key' => 'actor.impex-officer', 'role' => 'impex_officer', 'email' => 'reyes.paolo@gmail.com', 'department' => 'IMPEX', 'position' => 'ImpEx Officer', 'first' => 'Paolo', 'middle' => 'Garcia', 'last' => 'Reyes'],
        ['key' => 'actor.department-head', 'role' => 'department_head', 'email' => 'manalo.mila@gmail.com', 'department' => 'PROD', 'position' => 'Production Head', 'first' => 'Mila', 'middle' => 'Corazon', 'last' => 'Manalo'],
        ['key' => 'actor.employee', 'role' => 'employee', 'email' => 'villanueva.ana@gmail.com', 'department' => 'PROD', 'position' => 'Production Operator', 'first' => 'Ana', 'middle' => 'Mae', 'last' => 'Villanueva', 'payroll_persona' => 'PAY-01', 'pay_type' => 'semi_monthly'],
        ['key' => 'actor.driver', 'role' => 'driver', 'email' => 'aquino.renato@gmail.com', 'department' => 'WH', 'position' => 'Driver', 'first' => 'Renato', 'middle' => 'Santos', 'last' => 'Aquino'],
        ['key' => 'actor.finance-reviewer', 'role' => 'finance_officer', 'email' => 'dizon.alyssa@gmail.com', 'department' => 'FIN', 'position' => 'Accounting Staff', 'first' => 'Alyssa', 'middle' => 'Mae', 'last' => 'Dizon'],
        ['key' => 'actor.purchasing-reviewer', 'role' => 'purchasing_officer', 'email' => 'ramos.mark@gmail.com', 'department' => 'PUR', 'position' => 'Purchasing Staff', 'first' => 'Mark', 'middle' => 'Anthony', 'last' => 'Ramos'],
        ['key' => 'actor.maintenance-tech-2', 'role' => 'maintenance_tech', 'email' => 'castro.joseph@gmail.com', 'department' => 'MAINT', 'position' => 'Maintenance Technician', 'first' => 'Joseph', 'middle' => 'Miguel', 'last' => 'Castro'],
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

    /** @var array<string, int> */
    private const DEMO_EXTRA_HEADCOUNTS = [
        'EXEC' => 1, 'HR' => 1, 'FIN' => 2, 'PROD' => 15, 'QC' => 3,
        'WH' => 4, 'PUR' => 1, 'PPC' => 2, 'MAINT' => 4, 'MOLD' => 1,
        'IMPEX' => 1, 'SALES' => 1, 'CS' => 2, 'ADMIN' => 1, 'IT' => 1,
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
        private readonly ProfileUpdateRequestService $profileUpdates,
        private readonly SalaryAdjustmentService $salaryAdjustments,
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
        if (strlen($password) < 12) {
            throw new RuntimeException('Set DEMO_ACCOUNT_PASSWORD to a shared demo-only password of at least 12 characters.');
        }

        $profiles = $this->plannedProfiles($context);
        $expectedCount = array_sum(self::HEADCOUNTS[$context->options->scale])
            + ($context->options->scale === 'demo' ? array_sum(self::DEMO_EXTRA_HEADCOUNTS) : 0);
        if (count($profiles) !== $expectedCount) {
            throw new RuntimeException("The {$context->options->scale} workforce catalog resolved to ".count($profiles)." employees; expected {$expectedCount}.");
        }

        $employeesByKey = $this->seedEmployees($context, $profiles);
        $this->seedRoleAccounts($context, $profiles, $employeesByKey, $password);
        $this->seedSensitiveProfileDetails($context, $profiles, $employeesByKey);
        $this->seedMissingCompensation($context, $profiles, $employeesByKey);
        $this->archiveNonRosterEmployees($context, $employeesByKey);
        $this->assignDepartmentHeads($context, $employeesByKey);
    }

    /** @param list<array<string, mixed>> $profiles
     * @param array<string, Employee> $employeesByKey
     */
    private function seedSensitiveProfileDetails(DemoSeedContext $context, array $profiles, array $employeesByKey): void
    {
        $hrReviewerId = $context->records()->id('workforce.user.role.hr_officer');
        $financeReviewerId = $context->records()->id('workforce.user.role.finance_officer');
        $systemAdminId = $context->records()->id('workforce.user.role.system_admin');
        $hrReviewer = User::query()->find((int) $hrReviewerId);
        $financeReviewer = User::query()->find((int) $financeReviewerId);
        $systemAdmin = User::query()->find((int) $systemAdminId);
        if (! $hrReviewer instanceof User || ! $financeReviewer instanceof User || ! $systemAdmin instanceof User) {
            throw new RuntimeException('Demo HR, Finance and System Administrator actors are required to seed sensitive workforce placeholders.');
        }

        $asOf = $context->options->asOf->setTimezone('Asia/Manila');
        $context->withoutNotifications(function () use ($context, $profiles, $employeesByKey, $hrReviewer, $financeReviewer, $systemAdmin, $asOf): void {
            foreach ($profiles as $profile) {
                $employee = $employeesByKey[$profile['key']];
                $employee->refresh();

                $governmentIds = $this->syntheticGovernmentIds($employee);
                $governmentChanges = [];
                foreach ($governmentIds as $field => $placeholder) {
                    if ($employee->getAttribute($field) === null || trim((string) $employee->getAttribute($field)) === '') {
                        $governmentChanges[$field] = $placeholder;
                    }
                }
                if ($governmentChanges !== []) {
                    $context->at($asOf, fn () => $context->asUser($hrReviewer, fn () => $this->employees->update($employee, $governmentChanges)));
                }

                $employee->refresh();
                $plannedBank = $this->plannedBankDetails($employee);
                $bankChanges = [];
                foreach ($plannedBank as $field => $placeholder) {
                    $current = trim((string) ($employee->getAttribute($field) ?? ''));
                    $isLegacyPlaceholder = $field === 'bank_name'
                        ? $current === 'DEMO BANK - NOT FOR PAYMENT'
                        : preg_match('/\ADEMO-ACCT-[0-9]{8}\z/', $current) === 1;
                    if ($current === '' || $isLegacyPlaceholder) {
                        $bankChanges[$field] = $placeholder;
                    }
                }

                $legacyMarker = 'DEMO-WORKFORCE-BANK:'.$employee->getKey();
                $readableNote = 'Bank details update for employee '.$employee->employee_no;
                $request = ProfileUpdateRequest::query()
                    ->where('employee_id', $employee->getKey())
                    ->whereIn('note', [$legacyMarker, $readableNote])
                    ->latest('id')
                    ->get()
                    ->first(function (ProfileUpdateRequest $candidate) use ($plannedBank): bool {
                        $changes = (array) $candidate->changes;
                        if ($changes === []) {
                            return false;
                        }

                        foreach ($changes as $field => $value) {
                            if (! array_key_exists($field, $plannedBank) || $value !== $plannedBank[$field]) {
                                return false;
                            }
                        }

                        return true;
                    });
                if ($request === null && $bankChanges !== []) {
                    $requester = $employee->user;
                    if (! $requester instanceof User || in_array((int) $requester->id, [(int) $hrReviewer->id, (int) $financeReviewer->id], true)) {
                        $requester = $systemAdmin;
                    }
                    $submittedAt = $this->bankRequestSubmittedAt($employee, $asOf);
                    $request = $context->at($submittedAt, fn () => $context->asUser($requester, fn () => $this->profileUpdates->submit(
                        $employee,
                        $requester,
                        $bankChanges,
                        $readableNote,
                    )));
                }

                if ($request instanceof ProfileUpdateRequest) {
                    $requestChanges = (array) $request->changes;
                    foreach ($requestChanges as $field => $value) {
                        if (! array_key_exists($field, $plannedBank) || $value !== $plannedBank[$field]) {
                            throw new RuntimeException("Seed-owned bank profile request for {$employee->email} differs from its planned synthetic bank values.");
                        }
                    }
                    if ($request->status === 'pending') {
                        $hrReviewedAt = $this->bankRequestSubmittedAt($employee, $asOf)->addDay();
                        $request = $context->at(min($hrReviewedAt, $asOf), fn () => $context->asUser($hrReviewer, fn () => $this->profileUpdates->approve(
                            $request,
                            $hrReviewer,
                            'Approved synthetic payroll bank details.',
                        )));
                    }
                    if ($request->status === 'pending_finance') {
                        $financeReviewedAt = $this->bankRequestSubmittedAt($employee, $asOf)->addDays(2);
                        $request = $context->at(min($financeReviewedAt, $asOf), fn () => $context->asUser($financeReviewer, fn () => $this->profileUpdates->financeApprove(
                            $request,
                            $financeReviewer,
                            'Approved synthetic payroll bank details.',
                        )));
                    }
                    if ($request->status !== 'approved') {
                        throw new RuntimeException("Seed-owned bank profile request for {$employee->email} did not reach approved status.");
                    }
                    $context->records()->remember('workforce.profile-update.bank.'.$employee->getKey(), $request);
                }

                $employee->refresh();
                foreach ($plannedBank as $field => $placeholder) {
                    if ($employee->getAttribute($field) === null && ! isset($bankChanges[$field])) {
                        throw new RuntimeException("Seed-owned employee {$employee->email} has an unresolved {$field} value after profile approval.");
                    }
                    if (isset($bankChanges[$field]) && $employee->getAttribute($field) !== $placeholder) {
                        throw new RuntimeException("Seed-owned employee {$employee->email} has an unapplied synthetic {$field} value.");
                    }
                }
                $context->at($asOf, fn () => app(OnboardingService::class)->recompute($employee));
            }
        });
    }

    /** @return array{sss_no:string,philhealth_no:string,pagibig_no:string,tin:string} */
    private function syntheticGovernmentIds(Employee $employee): array
    {
        $id = (int) $employee->getKey();
        if ($id < 1 || $id > 9_999_999) {
            throw new RuntimeException("Employee {$employee->email} has an unsupported ID for synthetic DEMO government placeholders.");
        }
        $sevenDigits = sprintf('%07d', $id);
        $nineDigits = sprintf('%09d', $id);

        return [
            'sss_no' => '999'.$sevenDigits,
            'philhealth_no' => '999'.$nineDigits,
            'pagibig_no' => '998'.$nineDigits,
            'tin' => '997'.$nineDigits,
        ];
    }

    private function bankRequestSubmittedAt(Employee $employee, CarbonImmutable $asOf): CarbonImmutable
    {
        $hireDate = $employee->date_hired instanceof \DateTimeInterface
            ? CarbonImmutable::instance($employee->date_hired)->setTimezone('Asia/Manila')->startOfDay()
            : CarbonImmutable::parse((string) $employee->date_hired, 'Asia/Manila')->startOfDay();
        $latestSubmission = $asOf->subDays(2);
        if ($hireDate->greaterThan($latestSubmission)) {
            throw new RuntimeException("Employee {$employee->email} was hired too recently to record both required bank approvals before the demo as-of date.");
        }

        return $latestSubmission;
    }

    /** @param list<array<string, mixed>> $profiles
     * @param array<string, Employee> $employeesByKey
     */
    private function seedMissingCompensation(DemoSeedContext $context, array $profiles, array $employeesByKey): void
    {
        $requester = User::query()->find((int) $context->records()->id('workforce.user.role.hr_officer'));
        if (! $requester instanceof User) {
            throw new RuntimeException('Demo HR actor is required to request compensation for seed-owned employees with missing pay.');
        }

        $asOf = $context->options->asOf->setTimezone('Asia/Manila');
        $submittedAt = $asOf->subDays(2);
        $context->withoutNotifications(function () use ($context, $profiles, $employeesByKey, $requester, $asOf, $submittedAt): void {
            foreach ($profiles as $profile) {
                $employee = $employeesByKey[$profile['key']]->fresh();
                $current = $employee->monthlyEquivalentSalary();
                if ($current !== null && bccomp((string) $current, '0.00', 2) > 0) {
                    continue;
                }

                $reason = 'DEMO-WORKFORCE-COMPENSATION:'.$employee->getKey();
                $compensation = $this->plannedDemoCompensation($profile);
                $effectiveDate = $employee->date_hired instanceof \DateTimeInterface
                    ? CarbonImmutable::instance($employee->date_hired)->toDateString()
                    : (string) $employee->date_hired;
                $adjustment = SalaryAdjustment::query()
                    ->where('employee_id', $employee->getKey())
                    ->where('reason', $reason)
                    ->first();

                if ($adjustment === null) {
                    if (SalaryAdjustment::query()
                        ->where('employee_id', $employee->getKey())
                        ->where('status', 'pending')
                        ->exists()) {
                        throw new RuntimeException("Employee {$employee->email} has an unrelated pending salary adjustment; demo compensation will not override it.");
                    }

                    $adjustment = $context->at($submittedAt, fn () => $context->asUser($requester, fn () => $this->salaryAdjustments->request(
                        $employee,
                        [
                            ...$compensation,
                            'effective_date' => $effectiveDate,
                            'reason' => $reason,
                        ],
                        $requester,
                    )));
                } elseif ($adjustment->to_basic_monthly_salary !== ($compensation['to_basic_monthly_salary'] ?? null)
                    || $adjustment->to_semi_monthly_rate !== ($compensation['to_semi_monthly_rate'] ?? null)
                    || $adjustment->effective_date?->toDateString() !== $effectiveDate) {
                    throw new RuntimeException("Seed-owned compensation request for {$employee->email} differs from its planned demo rate.");
                }

                if ($adjustment->status?->value === 'rejected') {
                    throw new RuntimeException("Seed-owned compensation request for {$employee->email} was rejected and must be resolved before reseeding.");
                }

                while (($nextApproval = $adjustment->approvalRecords()->where('action', 'pending')->orderBy('step_order')->first()) !== null) {
                    $roleSlug = (string) $nextApproval->role_slug;
                    $reviewerId = $context->records()->id('workforce.user.role.'.$roleSlug);
                    $reviewer = $reviewerId === null ? null : User::query()->find((int) $reviewerId);
                    if (! $reviewer instanceof User) {
                        throw new RuntimeException("Salary approval role {$roleSlug} has no demo account to complete {$employee->email}'s compensation request.");
                    }

                    $reviewedAt = $submittedAt->addDays((int) $nextApproval->step_order);
                    if ($reviewedAt->greaterThan($asOf)) {
                        $reviewedAt = $asOf;
                    }
                    $adjustment = $context->at($reviewedAt, fn () => $context->asUser($reviewer, fn () => $this->salaryAdjustments->approve(
                        $adjustment,
                        $reviewer,
                        'Approved the synthetic demo pay band for payroll coverage.',
                    )));
                }

                $employee->refresh();
                if ($adjustment->status?->value !== 'approved' || $adjustment->applied_at === null
                    || $employee->monthlyEquivalentSalary() === null
                    || bccomp((string) $employee->monthlyEquivalentSalary(), '0.00', 2) <= 0) {
                    throw new RuntimeException("Salary approval did not apply a positive planned demo rate for {$employee->email}.");
                }
            }
        });
    }

    /** @param array<string, mixed> $profile
     * @return array{to_basic_monthly_salary?:string,to_semi_monthly_rate?:string}
     */
    private function plannedDemoCompensation(array $profile): array
    {
        $monthlyEquivalent = $this->plannedDemoMonthlyEquivalent($profile);
        if ($profile['pay_type'] === PayType::Monthly->value) {
            return ['to_basic_monthly_salary' => $monthlyEquivalent];
        }
        if ($profile['pay_type'] === PayType::SemiMonthly->value) {
            return ['to_semi_monthly_rate' => bcdiv($monthlyEquivalent, '2.00', 2)];
        }

        throw new RuntimeException("Employee {$profile['email_address']} has an unsupported pay type for demo compensation.");
    }

    /** @param array<string, mixed> $profile */
    private function plannedDemoMonthlyEquivalent(array $profile): string
    {
        $position = mb_strtolower((string) $profile['position']);
        $department = (string) $profile['department'];
        $persona = (string) ($profile['payroll_persona'] ?? '');

        if ($persona === 'PAY-01') {
            $amount = 16824;
        } elseif (preg_match('/chairman|president|vice president/', $position) === 1) {
            $amount = $profile['nationality'] === 'Japanese' ? 120000 : 95000;
        } elseif (str_contains($position, 'manager')) {
            $amount = 60000;
        } elseif (preg_match('/head|supervisor/', $position) === 1) {
            $amount = 42000;
        } elseif (in_array($department, ['QC', 'MAINT', 'MOLD', 'IT'], true)
            || preg_match('/technician|inspector/', $position) === 1) {
            $amount = 24000;
        } elseif (in_array($department, ['WH', 'ADMIN'], true)
            || preg_match('/operator|driver|admin staff/', $position) === 1) {
            $amount = 18000;
        } else {
            $amount = 28000;
        }

        return number_format($amount, 2, '.', '');
    }

    /** @param array<string, mixed> $profile
     * @return array{basic_monthly_salary?:string,semi_monthly_rate?:string}
     */
    private function plannedDemoCompensationForCreate(array $profile): array
    {
        $monthlyEquivalent = $this->plannedDemoMonthlyEquivalent($profile);
        if ($profile['pay_type'] === PayType::Monthly->value) {
            return ['basic_monthly_salary' => $monthlyEquivalent];
        }
        if ($profile['pay_type'] === PayType::SemiMonthly->value) {
            return ['semi_monthly_rate' => bcdiv($monthlyEquivalent, '2.00', 2)];
        }

        throw new RuntimeException("Employee {$profile['email_address']} has an unsupported pay type for demo compensation.");
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

        if ($context->options->scale === 'demo') {
            foreach (self::DEMO_EXTRA_HEADCOUNTS as $departmentCode => $count) {
                $positionTitles = self::FILLER_POSITIONS[$departmentCode] ?? null;
                if ($positionTitles === null) {
                    throw new RuntimeException("No employee position catalog is defined for department {$departmentCode}.");
                }

                for ($ordinal = 1; $ordinal <= $count; $ordinal++) {
                    $key = sprintf('staff.demo-extra.%s.%04d', strtolower($departmentCode), $ordinal);
                    $profiles[] = $this->profile($context, $key, [
                        'department' => $departmentCode,
                        'position' => $positionTitles[($ordinal - 1) % count($positionTitles)],
                        'nationality' => 'Filipino',
                        'email' => sprintf('demo-extra-%s-%04d@people.ogami-demo.test', strtolower($departmentCode), $ordinal),
                        'pay_type' => null,
                    ]);
                }
            }
        }

        $profiles = $this->applyRosterNames($profiles);

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
            'email_address' => (string) $attributes['email'],
            'emergency_contact_name' => $faker->name(),
            'emergency_contact_relation' => $faker->randomElement(['Parent', 'Spouse', 'Sibling']),
            'role_slug' => $attributes['role_slug'] ?? null,
            'role_email' => $attributes['role_email'] ?? null,
            'actor_key' => $attributes['actor_key'] ?? null,
            'payroll_persona' => $attributes['payroll_persona'] ?? null,
        ];
    }

    /** @param list<array<string, mixed>> $profiles
     * @return list<array<string, mixed>>
     */
    private function applyRosterNames(array $profiles): array
    {
        $names = EmployeeRosterNames::all();
        if (count($profiles) > count($names)) {
            throw new RuntimeException('The employee name catalog does not cover the planned workforce size.');
        }

        $usedEmails = [];
        foreach ($profiles as $index => &$profile) {
            $profile['legacy_email'] = (string) $profile['email_address'];
            [$first, $middle, $last, $suffix] = $this->parseRosterName($names[$index]);
            $base = Str::slug(Str::ascii($last), '.').'.'.Str::slug(Str::ascii($first), '.');
            $emailLocal = $base;
            $duplicate = 1;
            while (isset($usedEmails[$emailLocal.'@gmail.com'])) {
                $duplicate++;
                $emailLocal = $base.'.'.$duplicate;
            }
            $email = strtolower($emailLocal.'@gmail.com');
            $usedEmails[$email] = true;

            $profile['first_name'] = $first;
            $profile['middle_name'] = $middle;
            $profile['last_name'] = $last;
            $profile['suffix'] = $suffix;
            $profile['email'] = $email;
            $profile['email_address'] = $email;
            $profile['mobile_number'] = sprintf('09000000%03d', $index + 1);
            $profile['emergency_contact_phone'] = sprintf('09000001%03d', $index + 1);
            if (is_string($profile['role_slug'])) {
                $profile['role_email'] = $email;
            }
        }
        unset($profile);

        return $profiles;
    }

    /** @return array{string,string,string,?string} */
    private function parseRosterName(string $source): array
    {
        $suffix = null;
        if (str_contains($source, ',')) {
            [$surname, $given] = array_map('trim', explode(',', $source, 2));
            $surnameParts = preg_split('/\s+/', $surname) ?: [];
            if (strtoupper((string) end($surnameParts)) === 'JR') {
                array_pop($surnameParts);
                $suffix = 'Jr.';
            }
            $givenParts = preg_split('/\s+/', $given) ?: [];
            if (in_array(strtoupper((string) end($givenParts)), ['JR.', 'SR.', 'II', 'III'], true)) {
                $suffix = ucfirst(strtolower((string) array_pop($givenParts)));
            }
            $last = implode(' ', $surnameParts);
        } else {
            $parts = preg_split('/\s+/', trim($source)) ?: [];
            if (in_array(strtoupper((string) end($parts)), ['JR.', 'SR.', 'II', 'III'], true)) {
                $suffix = ucfirst(strtolower((string) array_pop($parts)));
            }
            $compoundSurnames = ['DELA CRUZ', 'DE LA CRUZ', 'DE JESUS', 'DE MESA', 'DELOS REYES', 'DELOS SANTOS', 'STO NINO'];
            $last = (string) array_pop($parts);
            if (count($parts) >= 1) {
                $candidate = strtoupper((string) end($parts).' '.$last);
                if (in_array($candidate, $compoundSurnames, true)) {
                    $last = array_pop($parts).' '.$last;
                }
            }
            $givenParts = $parts;
        }

        if (! isset($givenParts) || $givenParts === [] || trim($last) === '') {
            throw new RuntimeException("Could not parse supplied employee name [{$source}].");
        }

        $first = array_shift($givenParts);
        $middle = implode(' ', $givenParts);

        return [
            Str::title(mb_strtolower((string) $first)),
            $middle !== '' ? Str::title(mb_strtolower($middle)) : '',
            Str::title(mb_strtolower($last)),
            $suffix,
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
                                if ($existing === null && $profile['legacy_email'] !== $profile['email_address']) {
                                    $existing = Employee::withTrashed()->where('email', $profile['legacy_email'])->first();
                                }
                                if ($existing?->trashed()) {
                                    throw new RuntimeException("Seed-owned employee {$profile['email_address']} is archived; restore it through the employee lifecycle before reseeding.");
                                }

                                $attributes = [
                                    'first_name' => $profile['first_name'],
                                    'middle_name' => $profile['middle_name'],
                                    'last_name' => $profile['last_name'],
                                    'suffix' => $profile['suffix'],
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
                                ];
                                if ($existing === null) {
                                    $employee = $context->at($profile['date_hired'], fn (): Employee => $this->employees->create([
                                        ...$attributes,
                                        ...$this->plannedDemoCompensationForCreate($profile),
                                        'shift_id' => $this->shiftFor($profile, $shiftIds),
                                    ]));
                                } else {
                                    $changes = array_filter($attributes, static function (mixed $value, string $field) use ($existing): bool {
                                        $actual = $existing->getAttribute($field);
                                        if ($actual instanceof \BackedEnum) {
                                            $actual = $actual->value;
                                        } elseif ($actual instanceof \DateTimeInterface) {
                                            $actual = $actual->format('Y-m-d');
                                        }

                                        return (string) ($actual ?? '') !== (string) ($value ?? '');
                                    }, ARRAY_FILTER_USE_BOTH);
                                    $employee = $changes === []
                                        ? $existing
                                        : $context->at($context->options->asOf, fn (): Employee => $this->employees->update($existing, $changes));
                                }

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
                $roleSlug = is_string($profile['role_slug']) ? $profile['role_slug'] : 'employee';
                $role = Role::query()->where('slug', $roleSlug)->first();
                if (! $role) {
                    throw new RuntimeException("Role {$roleSlug} is missing; run RolePermissionSeeder before demo layers.");
                }
                $employee = $employeesByKey[$profile['key']];
                $email = (string) $profile['email_address'];
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

                if (is_string($profile['role_slug'])) {
                    $number = ($roleAccountNumber[$profile['role_slug']] ?? 0) + 1;
                    $roleAccountNumber[$profile['role_slug']] = $number;
                    $roleKey = 'workforce.user.role.'.$profile['role_slug'].($number === 1 ? '' : '.'.$number);
                    $context->records()->remember($roleKey, $user);
                }
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

    /** @param array<string, Employee> $employeesByKey */
    private function archiveNonRosterEmployees(DemoSeedContext $context, array $employeesByKey): void
    {
        if ($context->options->scale === 'small') {
            return;
        }

        $rosterIds = array_values(array_unique(array_map(
            static fn (Employee $employee): int => (int) $employee->id,
            $employeesByKey,
        )));
        if ($rosterIds === []) {
            throw new RuntimeException('Cannot archive employees because the planned roster resolved to no records.');
        }

        $context->withoutNotifications(function () use ($context, $rosterIds): void {
            $context->at($context->options->asOf, function () use ($rosterIds): void {
                Employee::query()
                    ->whereNotIn('id', $rosterIds)
                    ->orderBy('id')
                    ->chunkById(50, function ($employees): void {
                        foreach ($employees as $employee) {
                            $this->employees->delete($employee);
                        }
                    });
            });
        });
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
        $barangays = [
            'Dasmariñas' => ['Langkaan I', 'Paliparan III', 'Salawag', 'Sampaloc I', 'San Agustin I'],
            'General Trias' => ['Buenavista I', 'Manggahan', 'Navarro', 'San Francisco', 'Santiago'],
            'Imus' => ['Anabu I-A', 'Buhay na Tubig', 'Malagasang I-A', 'Medicion I-A', 'Tanzang Luma I'],
            'Carmona' => ['Cabilang Baybay', 'Lantic', 'Mabuhay', 'Maduya', 'Milagrosa'],
            'Biñan' => ['Canlalay', 'Malaban', 'San Antonio', 'Santo Tomas', 'Zapote'],
            'Santa Rosa' => ['Balibago', 'Dita', 'Don Jose', 'Pulong Santa Cruz', 'Tagapo'],
            'Calamba' => ['Bucal', 'Canlubang', 'Halang', 'Parian', 'Pansol'],
            'Santo Tomas' => ['San Bartolome', 'San Miguel', 'San Roque', 'Santa Anastacia', 'Santiago'],
            'Lipa' => ['Balintawak', 'Marawoy', 'Sabang', 'San Carlos', 'Tambo'],
            'Muntinlupa' => ['Alabang', 'Bayanan', 'Cupang', 'Putatan', 'Tunasan'],
        ];

        return [
            'street' => 'Block '.$faker->numberBetween(1, 28).', Sample Village, Illustrative Road',
            'barangay' => $faker->randomElement($barangays[$locality['city']]),
            'city' => $locality['city'],
            'province' => $locality['province'],
            'zip' => $locality['zip'],
        ];
    }

    /** @return array{bank_name:string,bank_account_no:string} */
    private function plannedBankDetails(Employee $employee): array
    {
        $employeeId = (int) $employee->getKey();
        $bankIndex = ($employeeId - 1) % count(self::SYNTHETIC_BANK_NAMES);

        return [
            'bank_name' => self::SYNTHETIC_BANK_NAMES[$bankIndex],
            // Numeric, deterministic synthetic value; not an issued account or government ID.
            'bank_account_no' => sprintf('%012d', 700000000000 + $employeeId),
        ];
    }

    /** @param array<string, mixed> $profile */
    private function assertEmployeeMatchesProfile(Employee $employee, array $profile, int $departmentId, int $positionId): void
    {
        $expected = [
            'first_name' => $profile['first_name'],
            'middle_name' => $profile['middle_name'],
            'last_name' => $profile['last_name'],
            'suffix' => $profile['suffix'],
            'email' => $profile['email_address'],
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
