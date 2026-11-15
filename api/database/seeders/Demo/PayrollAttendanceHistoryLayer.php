<?php

declare(strict_types=1);

namespace Database\Seeders\Demo;

use App\Common\DemoData\DemoSeedContext;
use App\Common\DemoData\DemoSeeder;
use App\Modules\Attendance\Enums\OvertimeStatus;
use App\Modules\Attendance\Models\Attendance;
use App\Modules\Attendance\Models\OvertimeRequest;
use App\Modules\Attendance\Models\Shift;
use App\Modules\Attendance\Services\AttendanceService;
use App\Modules\Attendance\Services\HolidayService;
use App\Modules\Attendance\Services\OvertimeService;
use App\Modules\Attendance\Services\ShiftAssignmentService;
use App\Modules\Auth\Models\User;
use App\Modules\HR\Models\Employee;
use App\Modules\Loans\Enums\LoanStatus;
use App\Modules\Loans\Enums\LoanType;
use App\Modules\Loans\Models\EmployeeLoan;
use App\Modules\Loans\Services\LoanService;
use App\Common\Services\SettingsService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use RuntimeException;

/** App-service-created monthly DTR and approved overtime history for payroll examples. */
final class PayrollAttendanceHistoryLayer extends DemoSeeder
{
    private const HISTORY_YEAR = 2026;
    private const EMPLOYEE_CHUNK = 40;
    private const EMPLOYEE_LIMITS = ['small' => 25, 'demo' => 80, 'full' => 100];

    public function __construct(
        private readonly AttendanceService $attendance,
        private readonly HolidayService $holidays,
        private readonly OvertimeService $overtime,
        private readonly ShiftAssignmentService $shiftAssignments,
        private readonly LoanService $loans,
        private readonly SettingsService $settings,
    ) {}

    public function key(): string
    {
        return 'payroll-attendance-history';
    }

    /** @return list<string> */
    public function modules(): array
    {
        return ['Attendance', 'Payroll', 'Dashboard'];
    }

    /** @return list<string> */
    public function dependencies(): array
    {
        return [];
    }

    protected function seedDemoData(DemoSeedContext $context): void
    {
        $asOf = $context->options->asOf->setTimezone('Asia/Manila')->startOfDay();
        if ($asOf->year < self::HISTORY_YEAR) {
            return;
        }

        $lastMonth = $asOf->year === self::HISTORY_YEAR ? min(12, $asOf->month - 1) : 12;
        if ($lastMonth < 1) {
            return;
        }

        $threshold = $this->settings->get('attendance.auto_ot_detect.threshold_minutes');
        if (! is_numeric($threshold) || (int) $threshold < 0) {
            throw new RuntimeException('Payroll attendance history requires a valid app overtime-detection threshold.');
        }
        $approver = User::query()->where('is_active', true)->get()
            ->first(static fn (User $user): bool => $user->hasPermission('attendance.ot.approve'));
        if (! $approver instanceof User) {
            throw new RuntimeException('Payroll attendance history requires an active overtime approver.');
        }

        $context->withoutNotifications(function () use ($context, $asOf, $lastMonth, $approver, $threshold): void {
            $employeeIds = Employee::query()->active()->orderBy('id')
                ->limit(self::EMPLOYEE_LIMITS[$context->options->scale] ?? self::EMPLOYEE_LIMITS['demo'])
                ->pluck('id');

            Employee::query()->active()->whereIn('id', $employeeIds)->with(['shiftAssignments.shift'])->orderBy('id')
                ->chunkById(self::EMPLOYEE_CHUNK, function (Collection $employees) use ($context, $asOf, $lastMonth, $approver, $threshold): void {
                    foreach ($employees as $employee) {
                        for ($month = 1; $month <= $lastMonth; $month++) {
                            // September is protected by already-approved payroll periods.
                            if ($month === 9 || ($employee->date_hired && $employee->date_hired->year === self::HISTORY_YEAR && $employee->date_hired->month > $month)) {
                                continue;
                            }

                            $candidate = $this->availableWorkday($employee, $month, $asOf);
                            if ($candidate === null) {
                                continue;
                            }

                            [$date, $existing] = $candidate;
                            $pattern = $this->pattern((int) $employee->id, $month);
                            $marker = sprintf('Payroll history attendance %s for %s.', $pattern, $date->toDateString());
                            if ($existing instanceof Attendance) {
                                if ((string) $existing->remarks !== $marker) {
                                    continue;
                                }
                                $attendance = $existing;
                            } else {
                                $shift = $this->shiftAssignments->current($employee, $date);
                                if (! $shift instanceof Shift) {
                                    continue;
                                }
                                [$timeIn, $timeOut] = $this->punches($date, $shift, $pattern, (int) $threshold);
                                $recordedAt = $timeOut ?? $this->shiftEnd($date, $shift);
                                $attendance = $context->at($recordedAt, fn (): Attendance => $this->attendance->create([
                                    'employee_id' => (int) $employee->id,
                                    'date' => $date->toDateString(),
                                    'shift_id' => (int) $shift->id,
                                    'time_in' => $timeIn?->toDateTimeString(),
                                    'time_out' => $timeOut?->toDateTimeString(),
                                    'remarks' => $marker,
                                ]));
                            }

                            if ($pattern === 'overtime' && (int) $approver->employee_id !== (int) $employee->id) {
                                $this->approveHistoryOvertime($context, $attendance, $approver, $asOf);
                            }
                        }
                    }
                });

            $this->seedHistoricalLoanRequest($context, $asOf);
        });
    }

    private function seedHistoricalLoanRequest(DemoSeedContext $context, CarbonImmutable $asOf): void
    {
        $createdAt = CarbonImmutable::parse('2026-10-26 09:15:00', 'Asia/Manila');
        if ($asOf->lte($createdAt)) {
            return;
        }

        $purpose = 'Payroll history company-loan request for October 2026.';
        if (EmployeeLoan::query()->where('purpose', $purpose)->exists()) {
            return;
        }

        $employee = Employee::query()->active()
            ->whereHas('user', static fn ($query) => $query->where('is_active', true)
                ->whereHas('role', static fn ($role) => $role->where('slug', 'employee')))
            ->whereNotIn('id', EmployeeLoan::query()->select('employee_id')->whereIn('status', [
                LoanStatus::Pending->value,
                LoanStatus::Active->value,
                LoanStatus::WriteOffPending->value,
            ]))
            ->orderBy('id')
            ->first();
        if (! $employee instanceof Employee || ! $employee->user instanceof User) {
            throw new RuntimeException('October payroll loan history requires an active employee account without another open loan.');
        }

        $limits = $this->loans->limitsFor($employee, LoanType::CompanyLoan);
        $principal = min(5000.0, (float) $limits['principal_max']);
        if ($principal <= 0) {
            throw new RuntimeException("App loan policy returned no positive principal limit for {$employee->employee_no}.");
        }

        $periods = max(1, min(6, (int) $limits['max_pay_periods']));
        $context->at($createdAt, fn (): EmployeeLoan => $context->asUser($employee->user, fn (): EmployeeLoan => $this->loans->request(
            (int) $employee->id,
            LoanType::CompanyLoan,
            [
                'principal' => number_format($principal, 2, '.', ''),
                'pay_periods' => $periods,
                'purpose' => $purpose,
            ],
        )));
    }

    /** @return array{CarbonImmutable, Attendance|null}|null */
    private function availableWorkday(Employee $employee, int $month, CarbonImmutable $asOf): ?array
    {
        $start = CarbonImmutable::create(self::HISTORY_YEAR, $month, 5 + ((int) $employee->id % 9), 0, 0, 0, 'Asia/Manila');
        $holidayDates = $this->holidays->datesBetween($start, $start->addDays(20));

        for ($offset = 0; $offset < 21; $offset++) {
            $date = $start->addDays($offset);
            if ($date->month !== $month || $date->gte($asOf) || $date->dayOfWeekIso === 7
                || isset($holidayDates[$date->toDateString()])
                || ! $this->shiftAssignments->isWorkingDay($employee, $date)) {
                continue;
            }

            $existing = Attendance::withTrashed()
                ->where('employee_id', $employee->id)
                ->whereDate('date', $date->toDateString())
                ->first();
            if ($existing === null || str_starts_with((string) $existing->remarks, 'Payroll history attendance ')) {
                return [$date, $existing];
            }
        }

        return null;
    }

    private function pattern(int $employeeId, int $month): string
    {
        $bucket = (($employeeId * 17) + ($month * 29)) % 100;

        return match (true) {
            $bucket < 3 => 'absent',
            $bucket < 11 => 'late',
            $bucket < 17 => 'undertime',
            $bucket < 22 => 'overtime',
            default => 'present',
        };
    }

    /** @return array{CarbonImmutable|null, CarbonImmutable|null} */
    private function punches(CarbonImmutable $date, Shift $shift, string $pattern, int $threshold): array
    {
        $start = CarbonImmutable::parse($date->toDateString().' '.(string) $shift->start_time, 'Asia/Manila');
        $end = $this->shiftEnd($date, $shift);
        $grace = max(0, (int) ($shift->grace_minutes ?? 0));

        if ($pattern === 'absent') {
            return [null, null];
        }

        $in = $pattern === 'late' ? $start->addMinutes($grace + 15) : $start;
        $out = match ($pattern) {
            'undertime' => $end->subMinutes(60),
            'overtime' => $end->addMinutes(max(30, $threshold + 30)),
            default => $end,
        };

        return [$in, $out];
    }

    private function shiftEnd(CarbonImmutable $date, Shift $shift): CarbonImmutable
    {
        $start = CarbonImmutable::parse($date->toDateString().' '.(string) $shift->start_time, 'Asia/Manila');
        $end = CarbonImmutable::parse($date->toDateString().' '.(string) $shift->end_time, 'Asia/Manila');

        return $end->lte($start) ? $end->addDay() : $end;
    }

    private function approveHistoryOvertime(DemoSeedContext $context, Attendance $attendance, User $approver, CarbonImmutable $asOf): void
    {
        $request = OvertimeRequest::query()
            ->where('employee_id', $attendance->employee_id)
            ->whereDate('date', $attendance->date->toDateString())
            ->first();
        if (! $request instanceof OvertimeRequest || $request->status !== OvertimeStatus::Pending) {
            return;
        }

        $actionAt = CarbonImmutable::parse($attendance->date->toDateString(), 'Asia/Manila')->addDay()->setTime(10, 0);
        for ($offset = 0; $offset < 7 && ($actionAt->dayOfWeekIso === 7 || ($this->holidays->datesBetween($actionAt, $actionAt)[$actionAt->toDateString()] ?? false)); $offset++) {
            $actionAt = $actionAt->addDay();
        }
        if ($actionAt->gte($asOf)) {
            return;
        }

        $context->at($actionAt, fn (): OvertimeRequest => $this->overtime->approve(
            $request,
            $approver,
            'Historical attendance and overtime were reviewed against the assigned shift.',
        ));
    }
}
