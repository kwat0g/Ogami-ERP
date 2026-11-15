<?php

declare(strict_types=1);

namespace Tests\Feature\Payroll;

use App\Common\Exceptions\BusinessRuleException;
use App\Common\Services\SettingsService;
use App\Modules\Attendance\Enums\AttendanceStatus;
use App\Modules\Attendance\Models\Attendance;
use App\Modules\Auth\Models\User;
use App\Modules\HR\Models\Employee;
use App\Modules\Payroll\Enums\PayrollAnomalyType;
use App\Modules\Payroll\Events\PayrollAnomalyChanged;
use App\Modules\Payroll\Models\Payroll;
use App\Modules\Payroll\Models\PayrollAnomalyFlag;
use App\Modules\Payroll\Models\PayrollPeriod;
use App\Modules\Payroll\Services\PayrollAnomalyService;
use App\Modules\Payroll\Services\PayrollPeriodService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PayrollAnomalyPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_persisted_deduction_ratio_controls_anomaly_detection(): void
    {
        $period = PayrollPeriod::factory()->create([
            'period_start' => '2026-07-01',
            'period_end' => '2026-07-15',
            'payroll_date' => '2026-07-17',
            'is_first_half' => true,
        ]);
        $payroll = Payroll::factory()->create([
            'payroll_period_id' => $period->id,
            'gross_pay' => 1000,
            'total_deductions' => 600,
            'net_pay' => 400,
        ]);

        app(SettingsService::class)->set('payroll.anomaly.deduction_ratio', 0.70, 'payroll');
        app(PayrollAnomalyService::class)->detect($period);
        $this->assertFalse($this->hasFlag($payroll, PayrollAnomalyType::HighDeduction));

        app(SettingsService::class)->set('payroll.anomaly.deduction_ratio', 0.50, 'payroll');
        app(PayrollAnomalyService::class)->detect($period);
        $this->assertTrue($this->hasFlag($payroll, PayrollAnomalyType::HighDeduction));
        $this->assertDatabaseHas('event_outbox', [
            'event_type' => PayrollAnomalyChanged::class,
        ]);
    }

    public function test_thirteenth_month_detection_skips_regular_period_comparisons(): void
    {
        $employee = Employee::factory()->create();
        $period = PayrollPeriod::factory()->create([
            'period_start' => '2026-12-01',
            'period_end' => '2026-12-31',
            'payroll_date' => '2026-12-20',
            'is_thirteenth_month' => true,
        ]);
        $payroll = Payroll::factory()->create([
            'payroll_period_id' => $period->id,
            'employee_id' => $employee->id,
            'gross_pay' => 1000,
            'total_deductions' => 200,
            'net_pay' => 800,
        ]);
        Attendance::create([
            'employee_id' => $employee->id,
            'date' => '2026-12-10',
            'time_in' => '2026-12-10 08:00:00',
            'status' => AttendanceStatus::Present->value,
        ]);

        app(PayrollAnomalyService::class)->detect($period);

        $this->assertFalse($this->hasFlag($payroll, PayrollAnomalyType::FirstPayroll));
        $this->assertFalse($this->hasFlag($payroll, PayrollAnomalyType::OpenAttendance));
    }

    public function test_thirteenth_month_detection_does_not_require_regular_only_thresholds(): void
    {
        $period = PayrollPeriod::factory()->create([
            'period_start' => '2026-12-01',
            'period_end' => '2026-12-31',
            'payroll_date' => '2026-12-20',
            'is_thirteenth_month' => true,
        ]);
        $payroll = Payroll::factory()->create([
            'payroll_period_id' => $period->id,
            'gross_pay' => 1000,
            'total_deductions' => 200,
            'net_pay' => 800,
        ]);

        app(SettingsService::class)->set('payroll.anomaly.net_change_ratio', 'invalid', 'payroll');
        app(SettingsService::class)->set('payroll.anomaly.overtime_hours', 'invalid', 'payroll');

        app(PayrollAnomalyService::class)->detect($period);

        $this->assertFalse($this->hasFlag($payroll, PayrollAnomalyType::HighDeduction));
    }

    public function test_detection_persists_a_fingerprinted_evaluation_for_the_period(): void
    {
        $period = PayrollPeriod::factory()->create([
            'period_start' => '2026-07-01',
            'period_end' => '2026-07-15',
            'payroll_date' => '2026-07-17',
            'is_first_half' => true,
        ]);
        Payroll::factory()->create([
            'payroll_period_id' => $period->id,
            'gross_pay' => 1000,
            'total_deductions' => 100,
            'net_pay' => 900,
        ]);

        app(PayrollAnomalyService::class)->detect($period);

        $evaluation = DB::table('payroll_anomaly_evaluations')
            ->where('payroll_period_id', $period->id)
            ->latest('id')
            ->first();
        $this->assertNotNull($evaluation);
        $this->assertSame('passed', $evaluation->status);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $evaluation->input_fingerprint);
        $this->assertNotNull($evaluation->evaluated_at);
    }

    public function test_changed_payroll_inputs_make_the_saved_evaluation_stale(): void
    {
        $period = PayrollPeriod::factory()->create([
            'period_start' => '2026-07-01',
            'period_end' => '2026-07-15',
            'payroll_date' => '2026-07-17',
            'is_first_half' => true,
        ]);
        $payroll = Payroll::factory()->create([
            'payroll_period_id' => $period->id,
            'gross_pay' => 1000,
            'total_deductions' => 100,
            'net_pay' => 900,
        ]);
        $service = app(PayrollAnomalyService::class);
        $service->detect($period);
        $service->assertCurrentEvaluation($period);

        $payroll->update(['net_pay' => '850.00']);

        $this->expectException(BusinessRuleException::class);
        $this->expectExceptionMessage('stale');
        $service->assertCurrentEvaluation($period);
    }

    private function hasFlag(Payroll $payroll, PayrollAnomalyType $type): bool
    {
        return PayrollAnomalyFlag::query()
            ->where('payroll_id', $payroll->id)
            ->where('flag_type', $type->value)
            ->exists();
    }

    public function test_finalize_refuses_when_anomaly_detection_failed(): void
    {
        $approver = User::factory()->create();
        $period = PayrollPeriod::factory()->create([
            'status' => 'approved',
            'anomaly_detection_failed' => true,
        ]);
        $period->forceFill(['approved_by' => $approver->id, 'approved_at' => now()])->save();

        $this->expectException(BusinessRuleException::class);
        $this->expectExceptionMessage('anomaly detection failed');

        app(PayrollPeriodService::class)->finalize($period, User::factory()->create());
    }
}
