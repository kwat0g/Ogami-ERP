<?php

declare(strict_types=1);

namespace Tests\Feature\Demo;

use App\Common\DemoData\DemoSeedLayerRegistry;
use App\Common\DemoData\DemoSeedOptions;
use App\Common\DemoData\DemoSeedRunner;
use App\Modules\Attendance\Enums\AttendanceStatus;
use App\Modules\Attendance\Enums\OvertimeStatus;
use App\Modules\Attendance\Models\Attendance;
use App\Modules\Attendance\Models\OvertimeRequest;
use App\Modules\Leave\Enums\LeaveRequestStatus;
use App\Modules\Leave\Models\LeaveRequest;
use App\Modules\Loans\Enums\LoanStatus;
use App\Modules\Loans\Models\EmployeeLoan;
use App\Modules\Payroll\Models\Payroll;
use App\Modules\Payroll\Models\PayrollPeriod;
use Carbon\CarbonImmutable;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\Demo\HrTransactionHistoryLayer;
use Database\Seeders\Demo\LeaveBalanceInitializationLayer;
use Database\Seeders\Demo\OrganizationReferenceDataLayer;
use Database\Seeders\Demo\PayrollAttendanceHistoryLayer;
use Database\Seeders\Demo\WorkforceIdentityLayer;
use Database\Seeders\HolidaySeeder;
use Database\Seeders\LeaveTypeSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SettingsSeeder;
use Database\Seeders\ShiftSeeder;
use Database\Seeders\WorkflowSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class HrTransactionHistoryLayerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([
            RolePermissionSeeder::class,
            SettingsSeeder::class,
            ChartOfAccountsSeeder::class,
            ShiftSeeder::class,
            LeaveTypeSeeder::class,
            HolidaySeeder::class,
            WorkflowSeeder::class,
        ]);

        $connection = (string) config('database.default');
        $host = (string) config("database.connections.{$connection}.host");
        config([
            'demo.credentials.password' => 'Demo-Only-Pass-2026!',
            'demo.seeding.enabled' => true,
            'demo.seeding.database_allowlist' => ['ogami_test'],
            'demo.seeding.allowed_hosts' => [$host],
            // Keep this test independent from the global registry integration.
            'demo.layers' => [
                OrganizationReferenceDataLayer::class,
                WorkforceIdentityLayer::class,
                LeaveBalanceInitializationLayer::class,
                HrTransactionHistoryLayer::class,
                PayrollAttendanceHistoryLayer::class,
            ],
        ]);
    }

    public function test_it_seeds_service_created_hr_transactions_and_replays_without_duplicates(): void
    {
        $options = new DemoSeedOptions(
            asOf: CarbonImmutable::parse('2026-11-16', 'Asia/Manila'),
            scale: 'small',
            seed: 20261116,
            only: 'hr-transactions',
        );

        $ordered = app(DemoSeedLayerRegistry::class)->ordered($options->only);
        $this->assertSame(
            ['organization', 'workforce-identities', 'hr-leave-balances', 'hr-transactions'],
            array_map(static fn ($layer): string => $layer->key(), $ordered),
        );

        app(DemoSeedRunner::class)->run($options);

        $payrollHistory = Attendance::query()->where('remarks', 'like', 'Payroll history attendance %');
        $this->assertGreaterThan(20, $payrollHistory->count());
        $this->assertGreaterThan(0, $payrollHistory->whereIn('status', [
            AttendanceStatus::Present->value,
            AttendanceStatus::Late->value,
            AttendanceStatus::Absent->value,
        ])->count());

        $attendance = Attendance::query()->where(function ($query): void {
            $query->where('remarks', 'like', 'Shift punches recorded for employee %')
                ->orWhere('remarks', 'like', 'Holiday shift punches recorded for employee %');
        });
        $this->assertGreaterThan(100, $attendance->count());
        $payrollHistory = Attendance::query()->where('remarks', 'like', 'Payroll history attendance %');
        $this->assertGreaterThan(20, $payrollHistory->count());
        $this->assertGreaterThan(0, $payrollHistory->whereIn('status', [
            AttendanceStatus::Present->value,
            AttendanceStatus::Late->value,
            AttendanceStatus::Absent->value,
        ])->count());
        foreach ([AttendanceStatus::Present, AttendanceStatus::Late, AttendanceStatus::Absent] as $status) {
            $this->assertGreaterThan(0, Attendance::query()
                ->where(function ($query): void {
                    $query->where('remarks', 'like', 'Shift punches recorded for employee %')
                        ->orWhere('remarks', 'like', 'Holiday shift punches recorded for employee %');
                })
                ->where('status', $status->value)
                ->count());
        }

        foreach (OvertimeStatus::cases() as $status) {
            $this->assertGreaterThan(0, OvertimeRequest::query()->where('status', $status->value)->count());
        }

        $leaveReasons = [
            'Vacation leave requested for a family commitment.',
            'Annual leave requested for planned personal travel.',
            'Vacation leave requested for a family event.',
            'Vacation leave requested during scheduled production support.',
        ];
        $leaves = LeaveRequest::query()->whereIn('reason', $leaveReasons);
        foreach ([LeaveRequestStatus::PendingDept, LeaveRequestStatus::PendingHr, LeaveRequestStatus::Approved, LeaveRequestStatus::Rejected, LeaveRequestStatus::Cancelled] as $status) {
            $this->assertGreaterThan(0, LeaveRequest::query()
                ->whereIn('reason', $leaveReasons)
                ->where('status', $status->value)
                ->count());
        }

        $loanPurposes = [
            'Company loan for home repairs.',
            'Cash advance for an urgent household expense.',
            'Cash advance for planned household expenses.',
        ];
        $loans = EmployeeLoan::query()->whereIn('purpose', $loanPurposes);
        foreach ([LoanStatus::Pending, LoanStatus::Active, LoanStatus::Rejected] as $status) {
            $this->assertGreaterThan(0, EmployeeLoan::query()
                ->whereIn('purpose', $loanPurposes)
                ->where('status', $status->value)
                ->count());
        }
        $this->assertSame(0, PayrollPeriod::query()->count());
        $this->assertSame(0, Payroll::query()->count());

        $beforeReplay = [
            'payroll_history' => Attendance::query()->where('remarks', 'like', 'Payroll history attendance %')->count(),
            'payroll_history_loan' => EmployeeLoan::query()->where('purpose', 'Payroll history company-loan request for October 2026.')->count(),
            'attendance' => Attendance::query()->where(function ($query): void {
                $query->where('remarks', 'like', 'Shift punches recorded for employee %')
                    ->orWhere('remarks', 'like', 'Holiday shift punches recorded for employee %');
            })->count(),
            'overtime' => OvertimeRequest::query()->count(),
            'leave_requests' => LeaveRequest::query()->whereIn('reason', $leaveReasons)->count(),
            'loans' => EmployeeLoan::query()->whereIn('purpose', $loanPurposes)->count(),
        ];

        app(DemoSeedRunner::class)->run($options);

        $this->assertSame($beforeReplay, [
            'payroll_history' => Attendance::query()->where('remarks', 'like', 'Payroll history attendance %')->count(),
            'payroll_history_loan' => EmployeeLoan::query()->where('purpose', 'Payroll history company-loan request for October 2026.')->count(),
            'attendance' => Attendance::query()->where(function ($query): void {
                $query->where('remarks', 'like', 'Shift punches recorded for employee %')
                    ->orWhere('remarks', 'like', 'Holiday shift punches recorded for employee %');
            })->count(),
            'overtime' => OvertimeRequest::query()->count(),
            'leave_requests' => LeaveRequest::query()->whereIn('reason', $leaveReasons)->count(),
            'loans' => EmployeeLoan::query()->whereIn('purpose', $loanPurposes)->count(),
        ]);
    }
}
