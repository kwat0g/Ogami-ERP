<?php

declare(strict_types=1);

namespace Tests\Feature\Payroll;

use App\Common\Exceptions\BusinessRuleException;
use App\Modules\Auth\Models\User;
use App\Modules\HR\Models\Employee;
use App\Modules\Leave\Enums\LeaveRequestStatus;
use App\Modules\Leave\Models\LeaveRequest;
use App\Modules\Leave\Models\LeaveType;
use App\Modules\Payroll\Enums\PayrollAdjustmentStatus;
use App\Modules\Payroll\Enums\PayrollAnomalyType;
use App\Modules\Payroll\Enums\PayrollPeriodStatus;
use App\Modules\Payroll\Models\Payroll;
use App\Modules\Payroll\Models\PayrollAnomalyFlag;
use App\Modules\Payroll\Models\PayrollPeriod;
use App\Modules\Payroll\Services\PayrollAdjustmentService;
use App\Modules\Payroll\Services\PayrollAnomalyService;
use App\Modules\Payroll\Services\PayrollPeriodService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class UnpaidAbsenceAdjustmentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        Mail::fake();
    }

    public function test_unpaid_absence_adjustment_is_bound_to_approved_leave_and_exact_target_period(): void
    {
        $employee = Employee::factory()->create();
        $origin = PayrollPeriod::factory()->create([
            'status' => PayrollPeriodStatus::Finalized->value,
            'period_start' => '2026-10-16',
            'period_end' => '2026-10-31',
        ]);
        $originalPayroll = Payroll::factory()->create([
            'payroll_period_id' => $origin->id,
            'employee_id' => $employee->id,
        ]);
        $target = PayrollPeriod::factory()->create([
            'status' => PayrollPeriodStatus::Draft->value,
            'period_start' => '2026-11-01',
            'period_end' => '2026-11-15',
            'is_first_half' => true,
        ]);
        $leave = $this->approvedUnpaidLeave($employee, '2026-11-05', '2026-11-06');
        $maker = User::factory()->withRole('system_admin')->create();

        $adjustment = app(PayrollAdjustmentService::class)->create([
            'original_payroll_id' => $originalPayroll->id,
            'type' => 'unpaid_absence',
            'amount' => '1250.00',
            'reason' => 'Approved unpaid leave for November 5 and 6.',
            'source_leave_request_id' => $leave->id,
            'target_payroll_period_id' => $target->id,
        ], $maker);

        $this->assertSame(PayrollAdjustmentStatus::Pending, $adjustment->status);
        $this->assertSame($origin->id, $adjustment->payroll_period_id);
        $this->assertSame($leave->id, $adjustment->source_leave_request_id);
        $this->assertSame($target->id, $adjustment->target_payroll_period_id);
    }

    public function test_http_creation_decodes_hash_ids_and_returns_the_source_and_target(): void
    {
        $employee = Employee::factory()->create();
        $origin = PayrollPeriod::factory()->create([
            'status' => PayrollPeriodStatus::Finalized->value,
            'period_start' => '2026-10-16',
            'period_end' => '2026-10-31',
        ]);
        $originalPayroll = Payroll::factory()->create([
            'payroll_period_id' => $origin->id,
            'employee_id' => $employee->id,
        ]);
        $target = PayrollPeriod::factory()->create([
            'period_start' => '2026-11-01',
            'period_end' => '2026-11-15',
            'is_first_half' => true,
        ]);
        $leave = $this->approvedUnpaidLeave($employee, '2026-11-05', '2026-11-06');
        $maker = User::factory()->withRole('system_admin')->create();

        $response = $this->actingAs($maker)
            ->postJson('/api/v1/payroll-adjustments', [
                'original_payroll_id' => $originalPayroll->hash_id,
                'type' => 'unpaid_absence',
                'amount' => '1250.00',
                'reason' => 'Approved unpaid leave for November 5 and 6.',
                'source_leave_request_id' => $leave->hash_id,
                'target_payroll_period_id' => $target->hash_id,
            ])
            ->assertCreated()
            ->assertJsonPath('data.source_leave_request.id', $leave->hash_id)
            ->assertJsonPath('data.target_period.id', $target->hash_id);

        $adjustmentId = $response->json('data.id');
        $this->actingAs($maker)
            ->getJson('/api/v1/payroll-adjustments/'.$adjustmentId)
            ->assertOk()
            ->assertJsonPath('data.source_leave_request.id', $leave->hash_id)
            ->assertJsonPath('data.target_period.id', $target->hash_id);

        $this->actingAs($maker)
            ->getJson('/api/v1/payroll-adjustments')
            ->assertOk()
            ->assertJsonPath('data.0.source_leave_request.id', $leave->hash_id)
            ->assertJsonPath('data.0.target_period.id', $target->hash_id);
    }

    public function test_unpaid_absence_options_are_scoped_to_the_source_employee_and_overlapping_cutoffs(): void
    {
        $employee = Employee::factory()->create();
        $origin = PayrollPeriod::factory()->create(['status' => PayrollPeriodStatus::Finalized->value]);
        $originalPayroll = Payroll::factory()->create([
            'payroll_period_id' => $origin->id,
            'employee_id' => $employee->id,
        ]);
        $target = PayrollPeriod::factory()->create([
            'period_start' => '2026-11-01',
            'period_end' => '2026-11-15',
            'is_first_half' => true,
        ]);
        $this->approvedUnpaidLeave($employee, '2026-11-05', '2026-11-06');
        $maker = User::factory()->withRole('system_admin')->create();

        $this->actingAs($maker)
            ->getJson('/api/v1/payroll-adjustments/unpaid-absence-options?original_payroll_id='.$originalPayroll->hash_id)
            ->assertOk()
            ->assertJsonPath('data.leave_requests.0.leave_request_no', 'LR-'.strtoupper(substr(sha1('2026-11-05'.'2026-11-06'.$employee->id), 0, 10)));

        $this->actingAs($maker)
            ->getJson('/api/v1/payroll-adjustments/unpaid-absence-options?original_payroll_id='.$originalPayroll->hash_id.'&leave_request_id='.$this->approvedLeaveId($employee)->hash_id)
            ->assertOk()
            ->assertJsonPath('data.target_periods.0.id', $target->hash_id);
    }

    private function approvedLeaveId(Employee $employee): LeaveRequest
    {
        return LeaveRequest::query()->where('employee_id', $employee->id)->firstOrFail();
    }

    public function test_unpaid_absence_adjustment_rejects_a_leave_request_for_another_employee(): void
    {
        $employee = Employee::factory()->create();
        $otherEmployee = Employee::factory()->create();
        $origin = PayrollPeriod::factory()->create(['status' => PayrollPeriodStatus::Finalized->value]);
        $originalPayroll = Payroll::factory()->create([
            'payroll_period_id' => $origin->id,
            'employee_id' => $employee->id,
        ]);
        $target = PayrollPeriod::factory()->create([
            'period_start' => '2026-11-01',
            'period_end' => '2026-11-15',
            'is_first_half' => true,
        ]);
        $leave = $this->approvedUnpaidLeave($otherEmployee, '2026-11-05', '2026-11-06');
        $maker = User::factory()->withRole('system_admin')->create();

        $this->expectException(BusinessRuleException::class);
        $this->expectExceptionMessage('must belong to the source payroll employee');

        app(PayrollAdjustmentService::class)->create([
            'original_payroll_id' => $originalPayroll->id,
            'type' => 'unpaid_absence',
            'amount' => '1250.00',
            'reason' => 'Approved unpaid leave for November 5 and 6.',
            'source_leave_request_id' => $leave->id,
            'target_payroll_period_id' => $target->id,
        ], $maker);
    }

    public function test_missing_applied_unpaid_absence_adjustment_creates_a_blocking_anomaly(): void
    {
        $employee = Employee::factory()->create();
        $period = PayrollPeriod::factory()->create([
            'period_start' => '2026-01-01',
            'period_end' => '2026-01-15',
            'is_first_half' => true,
        ]);
        $payroll = Payroll::factory()->create([
            'payroll_period_id' => $period->id,
            'employee_id' => $employee->id,
        ]);
        $leave = $this->approvedUnpaidLeave($employee, '2026-01-06', '2026-01-07');

        app(PayrollAnomalyService::class)->detect($period);

        $flag = PayrollAnomalyFlag::query()
            ->where('payroll_id', $payroll->id)
            ->where('flag_type', PayrollAnomalyType::UnpaidAbsence->value)
            ->firstOrFail();
        $this->assertSame($leave->leave_request_no, $flag->details['leave_requests'][0]['leave_request_no']);
    }

    public function test_finalization_refuses_unpaid_leave_without_applied_adjustment_even_without_anomaly_flag(): void
    {
        $employee = Employee::factory()->create();
        $approver = User::factory()->create();
        $period = PayrollPeriod::factory()->create([
            'status' => PayrollPeriodStatus::Approved->value,
            'period_start' => '2026-01-01',
            'period_end' => '2026-01-15',
            'is_first_half' => true,
        ]);
        $period->forceFill(['approved_by' => $approver->id, 'approved_at' => now()])->save();
        Payroll::factory()->create([
            'payroll_period_id' => $period->id,
            'employee_id' => $employee->id,
            'computed_at' => '2026-01-16 08:00:00',
        ]);
        $this->approvedUnpaidLeave($employee, '2026-01-06', '2026-01-07');

        $this->expectException(BusinessRuleException::class);
        $this->expectExceptionMessage('unpaid leave request(s) have no applied payroll adjustment');

        app(PayrollPeriodService::class)->finalize($period, User::factory()->create());
    }

    private function approvedUnpaidLeave(Employee $employee, string $start, string $end): LeaveRequest
    {
        $leaveType = LeaveType::create([
            'name' => 'Leave Without Pay',
            'code' => 'LWOP-'.strtoupper(substr(sha1($start.$employee->id), 0, 5)),
            'default_balance' => '0.0',
            'is_paid' => false,
        ]);
        $leave = LeaveRequest::create([
            'leave_request_no' => 'LR-'.strtoupper(substr(sha1($start.$end.$employee->id), 0, 10)),
            'employee_id' => $employee->id,
            'leave_type_id' => $leaveType->id,
            'start_date' => $start,
            'end_date' => $end,
            'days' => '2.0',
            'reason' => 'Approved unpaid absence.',
        ]);
        $leave->forceFill(['status' => LeaveRequestStatus::Approved->value])->save();

        return $leave;
    }
}
