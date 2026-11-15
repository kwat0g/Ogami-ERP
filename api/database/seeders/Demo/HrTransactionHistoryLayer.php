<?php

declare(strict_types=1);

namespace Database\Seeders\Demo;

use App\Common\DemoData\DemoSeedContext;
use App\Common\DemoData\DemoSeeder;
use App\Common\Models\ApprovalRecord;
use App\Common\Services\SettingsService;
use App\Modules\Attendance\Models\Attendance;
use App\Modules\Attendance\Enums\HolidayType;
use App\Modules\Attendance\Models\Holiday;
use App\Modules\Attendance\Models\OvertimeRequest;
use App\Modules\Attendance\Models\Shift;
use App\Modules\Attendance\Services\AttendanceService;
use App\Modules\Attendance\Services\HolidayService;
use App\Modules\Attendance\Services\OvertimeService;
use App\Modules\Attendance\Services\ShiftAssignmentService;
use App\Modules\Auth\Models\User;
use App\Modules\HR\Models\Department;
use App\Modules\HR\Models\Employee;
use App\Modules\Leave\Enums\LeaveRequestStatus;
use App\Modules\Leave\Models\LeaveRequest;
use App\Modules\Leave\Models\LeaveType;
use App\Modules\Leave\Services\LeaveRequestService;
use App\Modules\Loans\Enums\LoanStatus;
use App\Modules\Loans\Enums\LoanType;
use App\Modules\Loans\Models\EmployeeLoan;
use App\Modules\Loans\Services\LoanService;
use App\Modules\Payroll\Models\PayrollPeriod;
use App\Modules\Payroll\Services\PayrollPeriodService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use RuntimeException;

/** Service-created attendance, leave, overtime and employee loan examples. */
final class HrTransactionHistoryLayer extends DemoSeeder
{
    private const ATTENDANCE_DAYS = 8;
    private const EMPLOYEE_CHUNK = 50;

    public function __construct(
        private readonly AttendanceService $attendance,
        private readonly HolidayService $holidays,
        private readonly OvertimeService $overtime,
        private readonly ShiftAssignmentService $shiftAssignments,
        private readonly LeaveRequestService $leaveRequests,
        private readonly LoanService $loans,
        private readonly PayrollPeriodService $payrollPeriods,
        private readonly SettingsService $settings,
    ) {}

    public function key(): string
    {
        return 'hr-transactions';
    }

    /** @return list<string> */
    public function modules(): array
    {
        return ['Attendance', 'Leave', 'Loans', 'HR', 'Dashboard'];
    }

    /** @return list<string> */
    public function dependencies(): array
    {
        return ['workforce-identities', 'hr-leave-balances'];
    }

    protected function seedDemoData(DemoSeedContext $context): void
    {
        $paidLeave = LeaveType::query()
            ->where('code', 'VL')
            ->where('is_active', true)
            ->where('is_paid', true)
            ->where('requires_document', false)
            ->first();
        if (! $paidLeave instanceof LeaveType) {
            throw new RuntimeException('HR transactions require an active, paid, document-free leave type such as the app-defined VL.');
        }

        $asOf = $context->options->asOf->setTimezone('Asia/Manila')->startOfDay();
        $historyDates = $this->recentWorkingDates($asOf, self::ATTENDANCE_DAYS);
        $overtimeEnabled = $this->settings->get('attendance.auto_ot_detect.enabled');
        $overtimeThreshold = $this->settings->get('attendance.auto_ot_detect.threshold_minutes');
        if (! is_bool($overtimeEnabled) || ! is_numeric($overtimeThreshold) || (int) $overtimeThreshold < 0) {
            throw new RuntimeException('HR transactions require valid app settings for automatic overtime detection.');
        }
        if (! $overtimeEnabled) {
            throw new RuntimeException('HR transactions require attendance.auto_ot_detect.enabled=true to create overtime through AttendanceService.');
        }

        $context->withoutNotifications(function () use ($context, $asOf, $historyDates, $paidLeave, $overtimeThreshold): void {
            $this->seedAttendanceHistory($context, $historyDates, (int) $overtimeThreshold);
            $this->seedPayrollMonthAttendance($context, $asOf, (int) $overtimeThreshold);
            $this->seedHistoricalHolidayAttendance($context, $asOf);
            $this->decidePersonaOvertime($context, $historyDates);
            $this->seedPaidLeaveExamples($context, $asOf, $paidLeave);
            $this->seedLoanExamples($context, $asOf);
            $this->seedOctoberPayrollPeriods($context, $asOf);
        });
    }

    private function seedOctoberPayrollPeriods(DemoSeedContext $context, CarbonImmutable $asOf): void
    {
        if ((int) $asOf->format('Y') !== 2026 || $asOf->lt(CarbonImmutable::parse('2026-11-01', 'Asia/Manila'))) {
            return;
        }

        $creator = User::query()->where('is_active', true)->get()
            ->first(static fn (User $user): bool => $user->hasPermission('payroll.periods.create'));
        if (! $creator instanceof User) {
            throw new RuntimeException('October payroll demo periods require an active user with payroll.periods.create.');
        }

        foreach ([
            ['start' => '2026-10-01', 'end' => '2026-10-15'],
            ['start' => '2026-10-16', 'end' => '2026-10-31'],
        ] as $range) {
            $existing = PayrollPeriod::query()
                ->whereDate('period_start', $range['start'])
                ->whereDate('period_end', $range['end'])
                ->where('is_thirteenth_month', false)
                ->first();
            if ($existing instanceof PayrollPeriod) {
                continue;
            }

            $createdAt = CarbonImmutable::parse($range['end'], 'Asia/Manila')->addDay()->setTime(9, 0);
            $context->at($createdAt, fn () => $context->asUser($creator, fn () => $this->payrollPeriods->create([
                'period_start' => $range['start'],
                'period_end' => $range['end'],
                'payroll_date' => $range['end'],
            ], $creator)));
        }
    }

    /** Add a small, service-backed payroll persona sample across 2026 pay months. */
    private function seedPayrollMonthAttendance(DemoSeedContext $context, CarbonImmutable $asOf, int $overtimeThreshold): void
    {
        $scenarios = [
            ['persona' => 'pay-01', 'pattern' => 'present'],
            ['persona' => 'pay-02', 'pattern' => 'late'],
            ['persona' => 'pay-03', 'pattern' => 'undertime'],
            ['persona' => 'pay-04', 'pattern' => 'absent'],
        ];

        foreach ($scenarios as $scenario) {
            $employee = $this->employeeForPersonaOrFallback($context, $scenario['persona']);
            for ($month = 1; $month <= min(10, (int) $asOf->format('n')); $month++) {
                if ($month === 9) {
                    continue; // Existing September periods are approved; preserve their DTR inputs.
                }
                $date = CarbonImmutable::create((int) $asOf->format('Y'), $month, 10, 0, 0, 0, 'Asia/Manila');
                if ($date->gte($asOf) || ($employee->date_hired && $date->lt($employee->date_hired))) {
                    continue;
                }
                $date = $this->payrollSampleWorkday($employee, $date);
                if ($date === null) {
                    continue;
                }

                $marker = sprintf('Payroll persona %s sample for %s.', $scenario['pattern'], $date->toDateString());
                $existing = Attendance::withTrashed()->where('employee_id', $employee->id)->whereDate('date', $date->toDateString())->first();
                if ($existing !== null) {
                    if ((string) $existing->remarks !== $marker) {
                        throw new RuntimeException("Cannot seed payroll persona attendance for {$employee->employee_no} on {$date->toDateString()}: a non-matching row already exists.");
                    }
                    continue;
                }

                $employee->loadMissing('shiftAssignments.shift');
                $shift = $this->shiftAssignments->current($employee, $date);
                if (! $shift instanceof Shift) {
                    throw new RuntimeException("Payroll persona {$employee->employee_no} has no shift on {$date->toDateString()}.");
                }
                $start = $this->shiftTime($date, (string) $shift->start_time);
                $end = $this->shiftTime($date, (string) $shift->end_time);
                if ($end->lte($start)) {
                    $end = $end->addDay();
                }
                $grace = max(0, (int) ($shift->grace_minutes ?? 0));
                $timeIn = $scenario['pattern'] === 'absent' ? null : $start;
                $timeOut = $scenario['pattern'] === 'absent' ? null : $end;
                if ($scenario['pattern'] === 'late') {
                    $timeIn = $start->addMinutes($grace + 20);
                } elseif ($scenario['pattern'] === 'undertime') {
                    $timeOut = $end->subMinutes(60);
                }
                if ($scenario['persona'] === 'pay-02' && $month === 10) {
                    $timeOut = $end->addMinutes($overtimeThreshold + 30);
                }

                $context->at($timeOut ?? $end, fn (): Attendance => $this->attendance->create([
                    'employee_id' => (int) $employee->id,
                    'date' => $date->toDateString(),
                    'shift_id' => (int) $shift->id,
                    'time_in' => $timeIn?->toDateTimeString(),
                    'time_out' => $timeOut?->toDateTimeString(),
                    'remarks' => $marker,
                ]));
            }
        }
    }

    private function payrollSampleWorkday(Employee $employee, CarbonImmutable $date): ?CarbonImmutable
    {
        for ($offset = 0; $offset < 12; $offset++) {
            $candidate = $date->addDays($offset);
            if ($candidate->gte(now()->toImmutable()) || $candidate->dayOfWeekIso === 7) {
                continue;
            }
            if ($this->holidays->datesBetween($candidate, $candidate)[$candidate->toDateString()] ?? false) {
                continue;
            }
            if ($this->shiftAssignments->isWorkingDay($employee, $candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    /** @return list<CarbonImmutable> Oldest first, excluding Sundays and app holidays. */
    private function recentWorkingDates(CarbonImmutable $asOf, int $limit): array
    {
        $dates = [];
        $cursor = $asOf->subDay();
        $holidayDates = $this->holidays->datesBetween($cursor->subDays(20), $cursor);

        while (count($dates) < $limit) {
            if ($cursor->dayOfWeekIso !== 7 && ! isset($holidayDates[$cursor->toDateString()])) {
                $dates[] = $cursor;
            }
            $cursor = $cursor->subDay();
        }

        return array_reverse($dates);
    }

    /** @param list<CarbonImmutable> $dates */
    private function seedAttendanceHistory(DemoSeedContext $context, array $dates, int $overtimeThreshold): void
    {
        $personaEmployeeId = (int) $this->employeeForPersonaOrFallback($context, 'pay-02')->id;
        $historyDatesByPersona = [];
        foreach ([2, 4, 6] as $index) {
            $historyDatesByPersona[] = $dates[$index];
        }

        Employee::query()->active()->orderBy('id')->chunkById(self::EMPLOYEE_CHUNK, function (Collection $employees) use ($context, $dates, $personaEmployeeId, $historyDatesByPersona, $overtimeThreshold): void {
            // Resolve historical/effective assignments for this bounded chunk;
            // Employee no longer stores a direct shift_id column.
            $employees->load('shiftAssignments.shift');

            foreach ($employees as $ordinal => $employee) {
                foreach ($dates as $dayIndex => $date) {
                    if ($employee->date_hired && $date->lt($employee->date_hired)) {
                        continue;
                    }
                    $shift = $this->shiftAssignments->current($employee, $date);
                    if (! $shift instanceof Shift) {
                        throw new RuntimeException("Active employee {$employee->employee_no} has no effective shift assignment for {$date->toDateString()}.");
                    }
                    if (! $this->shiftAssignments->isWorkingDay($employee, $date)) {
                        continue;
                    }
                    $overtimeDay = (int) $employee->id === $personaEmployeeId
                        && in_array($date->toDateString(), array_map(static fn (CarbonImmutable $d): string => $d->toDateString(), $historyDatesByPersona), true);
                    $marker = sprintf('Shift punches recorded for employee %s on %s.', $employee->employee_no, $date->toDateString());
                    $existing = Attendance::withTrashed()->where('employee_id', $employee->id)->whereDate('date', $date->toDateString())->first();
                    if ($existing !== null) {
                        if (! str_starts_with((string) $existing->remarks, 'Shift punches recorded for employee ')) {
                            throw new RuntimeException("Cannot seed attendance for {$employee->employee_no} on {$date->toDateString()}: a non-seeded row already exists.");
                        }
                        $context->records()->remember('hr.attendance.'.$employee->employee_no.'.'.$date->toDateString(), $existing);
                        continue;
                    }

                    $start = $this->shiftTime($date, (string) $shift->start_time);
                    $end = $this->shiftTime($date, (string) $shift->end_time);
                    if ($end->lte($start)) {
                        $end = $end->addDay();
                    }

                    // A small deterministic minority are absent or late; the DTR service owns all resulting calculations.
                    $isAbsent = ! $overtimeDay && (((int) $employee->id + $dayIndex * 7) % 41) === 0;
                    $isLate = ! $isAbsent && (((int) $employee->id + $dayIndex * 11) % 19) === 0;
                    $isUndertime = ! $isAbsent && ! $isLate && (((int) $employee->id + $dayIndex * 13) % 47) === 0;
                    $timeIn = $isAbsent ? null : $start->copy();
                    $timeOut = $isAbsent ? null : $end->copy();

                    if ($isLate) {
                        $grace = max(0, (int) ($shift->grace_minutes ?? 0));
                        $timeIn = $timeIn->addMinutes($grace + 15);
                    }
                    if ($isUndertime) {
                        $timeOut = $timeOut->subMinutes(45);
                    }
                    if ($overtimeDay) {
                        $timeOut = $end->addMinutes($overtimeThreshold + 15);
                    }

                    $recordedAt = $timeOut ?? $end;
                    $attendance = $context->at($recordedAt, fn (): Attendance => $this->attendance->create([
                        'employee_id' => (int) $employee->id,
                        'date' => $date->toDateString(),
                        'shift_id' => (int) $shift->id,
                        'time_in' => $timeIn?->toDateTimeString(),
                        'time_out' => $timeOut?->toDateTimeString(),
                        'remarks' => $marker,
                    ]));
                    $context->records()->remember('hr.attendance.'.$employee->employee_no.'.'.$date->toDateString(), $attendance);
                }
            }
        });
    }

    /** @param list<CarbonImmutable> $historyDates */
    private function decidePersonaOvertime(DemoSeedContext $context, array $historyDates): void
    {
        $employee = $this->employeeForPersonaOrFallback($context, 'pay-02');
        $approver = $this->userFromRegistry($context, 'workforce.user.role.hr_officer');
        $records = OvertimeRequest::query()
            ->where('employee_id', $employee->id)
            ->whereIn('date', array_map(static fn (CarbonImmutable $date): string => $date->toDateString(), [$historyDates[2], $historyDates[4], $historyDates[6]]))
            ->orderBy('date')
            ->get();

        if ($records->count() !== 3) {
            throw new RuntimeException('The PAY-02 overtime scenario did not produce three app-detected requests; verify automatic OT policy and shift punches.');
        }

        foreach ($records as $index => $overtime) {
            $this->rememberStatusRecord($context, 'hr.overtime.'.['approved', 'rejected', 'pending'][$index], $overtime);
            if ($overtime->status?->value !== 'pending' || $index === 2) {
                continue;
            }

            $actionDate = $this->nextWorkingDate(CarbonImmutable::parse($overtime->date)->addDay());
            $actionAt = $actionDate->setTime(10, $index === 0 ? 5 : 15);
            $decided = $context->at($actionAt, fn (): OvertimeRequest => $index === 0
                ? $this->overtime->approve($overtime, $approver, 'Shift punches and the supervisor request were reviewed.')
                : $this->overtime->reject($overtime, $approver, 'The request was not approved against the recorded shift hours.'));
            $context->records()->remember('hr.overtime.'.($index === 0 ? 'approved' : 'rejected'), $decided);
        }
    }

    private function seedHistoricalHolidayAttendance(DemoSeedContext $context, CarbonImmutable $asOf): void
    {
        $usedEmployeeIds = [];
        foreach ([
            ['persona' => 'pay-05', 'type' => HolidayType::Regular],
            ['persona' => 'pay-06', 'type' => HolidayType::SpecialNonWorking],
        ] as $scenario) {
            $holiday = Holiday::query()
                ->where('type', $scenario['type']->value)
                ->whereDate('date', '<', $asOf->toDateString())
                ->orderByDesc('date')
                ->first();
            if (! $holiday instanceof Holiday) {
                continue;
            }

            $employee = $this->employeeForPersonaOrFallback($context, $scenario['persona'], $usedEmployeeIds);
            $usedEmployeeIds[] = (int) $employee->id;
            $date = CarbonImmutable::parse($holiday->date)->setTimezone('Asia/Manila');
            if ($employee->date_hired && $date->lt($employee->date_hired)) {
                continue;
            }
            $employee->load('shiftAssignments.shift');
            $shift = $this->shiftAssignments->current($employee, $date);
            if (! $shift instanceof Shift) {
                throw new RuntimeException("Active employee {$employee->employee_no} has no effective shift assignment for holiday {$date->toDateString()}.");
            }

            $marker = sprintf('Holiday shift punches recorded for employee %s on %s.', $employee->employee_no, $date->toDateString());
            $existing = Attendance::withTrashed()->where('employee_id', $employee->id)->whereDate('date', $date->toDateString())->first();
            if ($existing !== null) {
                if (! str_starts_with((string) $existing->remarks, 'Holiday shift punches recorded for employee ')) {
                    throw new RuntimeException("Cannot seed holiday attendance for {$employee->employee_no} on {$date->toDateString()}: a non-seeded row already exists.");
                }
                $context->records()->remember('hr.attendance.'.$employee->employee_no.'.'.$date->toDateString(), $existing);
                continue;
            }

            $start = $this->shiftTime($date, (string) $shift->start_time);
            $end = $this->shiftTime($date, (string) $shift->end_time);
            if ($end->lte($start)) {
                $end = $end->addDay();
            }
            $attendance = $context->at($end, fn (): Attendance => $this->attendance->create([
                'employee_id' => (int) $employee->id,
                'date' => $date->toDateString(),
                'shift_id' => (int) $shift->id,
                'time_in' => $start->toDateTimeString(),
                'time_out' => $end->toDateTimeString(),
                'remarks' => $marker,
            ]));
            $context->records()->remember('hr.attendance.'.$employee->employee_no.'.'.$date->toDateString(), $attendance);
        }
    }

    private function seedPaidLeaveExamples(DemoSeedContext $context, CarbonImmutable $asOf, LeaveType $paidLeave): void
    {
        $scenarios = [
            ['persona' => 'pay-01', 'key' => 'pending-dept', 'reason' => 'Vacation leave requested for a family commitment.', 'target' => LeaveRequestStatus::PendingDept],
            ['persona' => 'pay-03', 'key' => 'pending-hr', 'reason' => 'Annual leave requested for planned personal travel.', 'target' => LeaveRequestStatus::PendingHr],
            ['persona' => 'pay-04', 'key' => 'approved', 'reason' => 'Vacation leave requested for a family event.', 'target' => LeaveRequestStatus::Approved],
            ['persona' => 'pay-05', 'key' => 'rejected', 'reason' => 'Vacation leave requested during scheduled production support.', 'target' => LeaveRequestStatus::Rejected],
            ['persona' => 'pay-06', 'key' => 'cancelled', 'reason' => 'Vacation leave request withdrawn by the employee.', 'target' => LeaveRequestStatus::Cancelled],
        ];
        $futureStart = $this->nextWorkingDate($asOf->addDays(5));
        $departmentHeadByEmployee = [];
        $usedEmployeeIds = [];
        $hrOfficer = $this->userFromRegistry($context, 'workforce.user.role.hr_officer');

        foreach ($scenarios as $index => $scenario) {
            $employee = $this->employeeForPersonaOrFallback($context, $scenario['persona'], $usedEmployeeIds);
            $usedEmployeeIds[] = (int) $employee->id;
            $reason = (string) $scenario['reason'];
            $request = LeaveRequest::query()->where('employee_id', $employee->id)->where('reason', $reason)->first();
            if (! $request instanceof LeaveRequest) {
                $leaveDate = $this->nextWorkingDate($futureStart->addDays($index * 2));
                $submitAt = $asOf->setTime(9, 0)->addMinutes($index * 5);
                $request = $context->at($submitAt, fn (): LeaveRequest => $this->leaveRequests->submit((int) $employee->id, [
                    'leave_type_id' => (int) $paidLeave->id,
                    'start_date' => $leaveDate->toDateString(),
                    'end_date' => $leaveDate->toDateString(),
                    'reason' => $reason,
                ]));
            }

            $departmentHead = $request->status === LeaveRequestStatus::PendingDept
                ? ($departmentHeadByEmployee[$employee->department_id] ??= $this->departmentHeadFor($employee))
                : null;
            $target = $scenario['target'];
            if ($target !== LeaveRequestStatus::PendingDept && $request->status === LeaveRequestStatus::PendingDept) {
                if (! $departmentHead instanceof User) {
                    throw new RuntimeException("Leave request {$request->leave_request_no} has no eligible department-head approver.");
                }
                $request = $context->at($asOf->setTime(10, 0)->addMinutes($index * 5), fn (): LeaveRequest => $this->leaveRequests->approveDept(
                    $request,
                    $departmentHead,
                    'Department review complete.',
                ));
            }
            if ($target === LeaveRequestStatus::Approved && $request->status === LeaveRequestStatus::PendingHr) {
                $request = $context->at($asOf->setTime(11, 0), fn (): LeaveRequest => $this->leaveRequests->approveHR(
                    $request,
                    $hrOfficer,
                    'Paid leave approved under the app leave balance.',
                ));
            } elseif ($target === LeaveRequestStatus::Rejected && in_array($request->status, [LeaveRequestStatus::PendingDept, LeaveRequestStatus::PendingHr], true)) {
                $rejector = $request->status === LeaveRequestStatus::PendingDept ? $departmentHead : $hrOfficer;
                if (! $rejector instanceof User) {
                    throw new RuntimeException("Leave request {$request->leave_request_no} has no eligible rejector.");
                }
                $request = $context->at($asOf->setTime(11, 30), fn (): LeaveRequest => $this->leaveRequests->reject(
                    $request,
                    $rejector,
                    'The requested date conflicts with scheduled production coverage.',
                ));
            } elseif ($target === LeaveRequestStatus::Cancelled && in_array($request->status, [LeaveRequestStatus::PendingDept, LeaveRequestStatus::PendingHr], true)) {
                $employeeUser = $employee->user;
                if (! $employeeUser instanceof User) {
                    throw new RuntimeException("Leave requester {$employee->employee_no} has no user account.");
                }
                $request = $context->at($asOf->setTime(11, 45), fn (): LeaveRequest => $this->leaveRequests->cancel($request, $employeeUser));
            }

            if ($request->status !== $target) {
                throw new RuntimeException("Leave scenario {$scenario['key']} ended in {$request->status->value}; expected {$target->value}.");
            }
            $context->records()->remember('hr.leave.'.$scenario['key'], $request);
        }
    }

    private function seedLoanExamples(DemoSeedContext $context, CarbonImmutable $asOf): void
    {
        $scenarioDefinitions = [
            ['persona' => 'pay-02', 'key' => 'active-company-loan', 'purpose' => 'Company loan for home repairs.', 'type' => LoanType::CompanyLoan, 'target' => LoanStatus::Active],
            ['persona' => 'pay-03', 'key' => 'pending-cash-advance', 'purpose' => 'Cash advance for an urgent household expense.', 'type' => LoanType::CashAdvance, 'target' => LoanStatus::Pending],
            ['persona' => 'pay-04', 'key' => 'rejected-cash-advance', 'purpose' => 'Cash advance for planned household expenses.', 'type' => LoanType::CashAdvance, 'target' => LoanStatus::Rejected],
        ];
        $usedEmployeeIds = [];

        foreach ($scenarioDefinitions as $index => $definition) {
            $employee = $this->employeeForPersonaOrFallback($context, $definition['persona'], $usedEmployeeIds);
            $usedEmployeeIds[] = (int) $employee->id;
            $marker = (string) $definition['purpose'];
            $loan = EmployeeLoan::query()->where('employee_id', $employee->id)->where('purpose', $marker)->first();
            if (! $loan instanceof EmployeeLoan) {
                $limits = $this->loans->limitsFor($employee, $definition['type']);
                $principal = min(5000.0, (float) $limits['principal_max']);
                if ($principal <= 0) {
                    throw new RuntimeException("App loan policy returned no positive principal limit for {$employee->employee_no}.");
                }
                $periods = max(1, min(6, (int) $limits['max_pay_periods']));
                $employeeUser = $employee->user;
                if (! $employeeUser instanceof User) {
                    throw new RuntimeException("Loan applicant {$employee->employee_no} has no user account.");
                }
                $createdAt = $asOf->setTime(13, 0)->addMinutes($index * 10);
                $loan = $context->at($createdAt, fn (): EmployeeLoan => $context->asUser($employeeUser, fn (): EmployeeLoan => $this->loans->request(
                    (int) $employee->id,
                    $definition['type'],
                    [
                        'principal' => number_format($principal, 2, '.', ''),
                        'pay_periods' => $periods,
                        'purpose' => $marker,
                    ],
                )));
            }

            $target = $definition['target'];
            if ($target === LoanStatus::Rejected && $loan->status === LoanStatus::Pending) {
                $currentStep = ApprovalRecord::query()
                    ->where('approvable_type', $loan->getMorphClass())
                    ->where('approvable_id', $loan->getKey())
                    ->where('is_current', true)
                    ->where('action', 'pending')
                    ->orderBy('step_order')
                    ->first();
                if ($currentStep === null) {
                    throw new RuntimeException("Loan {$loan->loan_no} is pending but has no approval step to reject.");
                }
                $rejector = $this->loanApproverForRole($context, $employee, (string) $currentStep->role_slug);
                $loan = $context->at($asOf->setTime(14, 0), fn (): EmployeeLoan => $this->loans->reject(
                    $loan,
                    $rejector,
                    'The request exceeds the available advance limit for this employee.',
                ));
            } elseif ($target === LoanStatus::Active && $loan->status === LoanStatus::Pending) {
                $loan = $this->approveLoanThroughWorkflow($context, $loan, $employee, $asOf);
            }

            if ($loan->status !== $target) {
                throw new RuntimeException("Loan scenario {$definition['key']} ended in {$loan->status->value}; expected {$target->value}.");
            }
            $context->records()->remember('hr.loan.'.$definition['key'], $loan);
        }
    }

    private function approveLoanThroughWorkflow(DemoSeedContext $context, EmployeeLoan $loan, Employee $employee, CarbonImmutable $asOf): EmployeeLoan
    {
        for ($step = 0; $step < 5 && $loan->status === LoanStatus::Pending; $step++) {
            $pending = ApprovalRecord::query()
                ->where('approvable_type', $loan->getMorphClass())
                ->where('approvable_id', $loan->getKey())
                ->where('is_current', true)
                ->where('action', 'pending')
                ->orderBy('step_order')
                ->first();
            if ($pending === null) {
                throw new RuntimeException("Loan {$loan->loan_no} is pending but has no pending approval step.");
            }

            $approver = $this->loanApproverForRole($context, $employee, (string) $pending->role_slug);

            $actionAt = $asOf->setTime(15, 0)->addMinutes($step * 5);
            $loan = $context->at($actionAt, fn (): EmployeeLoan => $this->loans->approve(
                $loan,
                $approver,
                'Approved through the configured app workflow.',
            ));
        }

        return $loan;
    }

    private function departmentHeadFor(Employee $employee): User
    {
        $department = Department::query()->find((int) $employee->department_id);
        $head = User::query()
            ->where('is_active', true)
            ->whereHas('role', static fn ($query) => $query->where('slug', 'department_head'))
            ->whereHas('employee', static fn ($query) => $query->where('department_id', $employee->department_id))
            ->get()
            ->first(static fn (User $candidate): bool => $candidate->hasPermission('leave.approve_dept') && $candidate->hasPermission('loans.approve'));
        if (! $head instanceof User) {
            throw new RuntimeException("Department {$department?->code} has no active eligible department-head approver.");
        }

        return $head;
    }

    private function employeeForPersonaOrFallback(DemoSeedContext $context, string $persona, array $excludedIds = []): Employee
    {
        $registryKey = 'workforce.employee.persona.'.strtolower($persona);
        if ($context->records()->has($registryKey)) {
            $employee = Employee::query()->active()->find((int) $context->records()->id($registryKey));
            if ($employee instanceof Employee && ! in_array((int) $employee->id, $excludedIds, true)) {
                return $employee;
            }
        }

        $excludedRoles = ['system_admin', 'hr_officer', 'finance_officer', 'vice_president', 'department_head'];
        $employee = Employee::query()
            ->active()
            ->whereNotIn('id', $excludedIds === [] ? [0] : $excludedIds)
            ->whereHas('user', static function ($query) use ($excludedRoles): void {
                $query->where('is_active', true)
                    ->whereHas('role', static fn ($roleQuery) => $roleQuery->whereNotIn('slug', $excludedRoles));
            })
            ->orderBy('id')
            ->first();

        if (! $employee instanceof Employee) {
            throw new RuntimeException("No active non-approver employee is available for demo persona {$persona}.");
        }

        return $employee;
    }

    private function loanApproverForRole(DemoSeedContext $context, Employee $employee, string $role): User
    {
        return match ($role) {
            'department_head' => $this->departmentHeadFor($employee),
            'hr_officer' => $this->userFromRegistry($context, 'workforce.user.role.hr_officer'),
            'finance_officer' => $this->userFromRegistry($context, 'workforce.user.role.finance_officer'),
            'vice_president' => $this->userFromRegistry($context, 'workforce.user.role.vice_president'),
            default => throw new RuntimeException("Loan workflow role {$role} has no seeded demo approver."),
        };
    }

    private function userFromRegistry(DemoSeedContext $context, string $key): User
    {
        $user = User::query()->find((int) $context->records()->id($key));
        if (! $user instanceof User) {
            throw new RuntimeException("Required approval actor {$key} is missing.");
        }

        return $user;
    }

    private function nextWorkingDate(CarbonImmutable $date): CarbonImmutable
    {
        $holidayDates = $this->holidays->datesBetween($date, $date->addDays(10));
        while ($date->dayOfWeekIso === 7 || isset($holidayDates[$date->toDateString()])) {
            $date = $date->addDay();
        }

        return $date;
    }

    private function shiftTime(CarbonImmutable $date, string $time): CarbonImmutable
    {
        return CarbonImmutable::parse($date->toDateString().' '.$time, 'Asia/Manila');
    }

    private function rememberStatusRecord(DemoSeedContext $context, string $key, OvertimeRequest $request): void
    {
        if (! $context->records()->has($key)) {
            $context->records()->remember($key, $request);
        }
    }
}
