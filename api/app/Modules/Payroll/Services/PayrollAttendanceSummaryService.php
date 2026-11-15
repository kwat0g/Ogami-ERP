<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Services;

use App\Modules\Attendance\Enums\AttendanceStatus;
use App\Modules\Attendance\Models\Attendance;
use App\Modules\Payroll\Models\Payroll;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

final class PayrollAttendanceSummaryService
{
    /**
     * Attendance recorded inside the payroll period, with the same work and
     * lateness definitions used by the payroll detail resource.
     *
     * @return array{
     *   dtr_record_count: int,
     *   present_days: int,
     *   late_days: int,
     *   absent_days: int,
     *   half_days: int,
     *   leave_days: int,
     *   holiday_days: int,
     *   rest_days: int,
     *   total_regular_hours: string,
     *   total_overtime_hours: string,
     *   total_night_diff_hours: string,
     *   total_tardiness_minutes: int,
     *   total_undertime_minutes: int,
     *   records: array<int, array<string, mixed>>
     * }
     */
    public function forPayroll(Payroll $payroll): array
    {
        $payroll->loadMissing('period');
        $period = $payroll->period;

        /** @var Collection<int, Attendance> $records */
        $records = $period
            ? Attendance::query()
                ->where('employee_id', $payroll->employee_id)
                ->whereBetween('date', [$period->period_start, $period->period_end])
                ->orderBy('date')
                ->get([
                    'date', 'time_in', 'time_out', 'regular_hours', 'overtime_hours',
                    'night_diff_hours', 'tardiness_minutes', 'undertime_minutes', 'status',
                ])
            : collect();

        $sumHours = static fn (string $field): string => $records->reduce(
            static fn (string $sum, Attendance $attendance): string => bcadd($sum, (string) $attendance->{$field}, 2),
            '0.00',
        );

        return [
            'dtr_record_count' => $records->count(),
            'present_days' => $records->filter(fn (Attendance $attendance): bool =>
                (float) $attendance->regular_hours > 0
                && ! in_array($attendance->status, [AttendanceStatus::Absent, AttendanceStatus::OnLeave], true)
            )->count(),
            'late_days' => $records->filter(fn (Attendance $attendance): bool =>
                $attendance->tardiness_minutes > 0 || $attendance->status === AttendanceStatus::Late
            )->count(),
            'absent_days' => $records->where('status', AttendanceStatus::Absent)->count(),
            'half_days' => $records->where('status', AttendanceStatus::Halfday)->count(),
            'leave_days' => $records->where('status', AttendanceStatus::OnLeave)->count(),
            'holiday_days' => $records->where('status', AttendanceStatus::Holiday)->count(),
            'rest_days' => $records->where('status', AttendanceStatus::RestDay)->count(),
            'total_regular_hours' => $sumHours('regular_hours'),
            'total_overtime_hours' => $sumHours('overtime_hours'),
            'total_night_diff_hours' => $sumHours('night_diff_hours'),
            'total_tardiness_minutes' => (int) $records->sum('tardiness_minutes'),
            'total_undertime_minutes' => (int) $records->sum('undertime_minutes'),
            'records' => $records->map(function (Attendance $attendance): array {
                $status = $attendance->status instanceof AttendanceStatus
                    ? $attendance->status->value
                    : (string) $attendance->status;

                return [
                    'date' => $attendance->date?->toDateString(),
                    'time_in' => $attendance->time_in?->toIso8601String(),
                    'time_out' => $attendance->time_out?->toIso8601String(),
                    'status' => $status,
                    'status_label' => $attendance->status instanceof AttendanceStatus
                        ? $attendance->status->label()
                        : Str::headline($status),
                    'regular_hours' => (string) $attendance->regular_hours,
                    'overtime_hours' => (string) $attendance->overtime_hours,
                    'night_diff_hours' => (string) $attendance->night_diff_hours,
                    'tardiness_minutes' => (int) $attendance->tardiness_minutes,
                    'undertime_minutes' => (int) $attendance->undertime_minutes,
                ];
            })->values()->all(),
        ];
    }
}
