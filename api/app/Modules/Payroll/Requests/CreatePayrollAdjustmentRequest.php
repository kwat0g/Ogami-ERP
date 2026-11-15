<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Requests;

use App\Modules\Leave\Models\LeaveRequest;
use App\Modules\Payroll\Enums\PayrollAdjustmentType;
use App\Modules\Payroll\Models\Payroll;
use App\Modules\Payroll\Models\PayrollPeriod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreatePayrollAdjustmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('payroll.adjustments.create') ?? false;
    }

    public function rules(): array
    {
        return [
            'original_payroll_id' => ['required', 'string'],
            'type' => ['required', Rule::in(PayrollAdjustmentType::values())],
            'amount' => ['required', 'numeric', 'decimal:0,2', 'min:0.01', 'max:9999999.99'],
            'reason' => ['required', 'string', 'min:5', 'max:1000'],
            'source_leave_request_id' => ['required_if:type,unpaid_absence', 'string', 'prohibited_unless:type,unpaid_absence'],
            'target_payroll_period_id' => ['required_if:type,unpaid_absence', 'string', 'prohibited_unless:type,unpaid_absence'],
        ];
    }

    public function validatedData(): array
    {
        $data = $this->validated();
        $payrollId = Payroll::tryDecodeHash((string) $data['original_payroll_id']);
        abort_if($payrollId === null, 422, 'Invalid payroll reference.');
        $data['original_payroll_id'] = $payrollId;

        if ($data['type'] === PayrollAdjustmentType::UnpaidAbsence->value) {
            $leaveRequestId = LeaveRequest::tryDecodeHash((string) $data['source_leave_request_id']);
            abort_if($leaveRequestId === null, 422, 'Invalid leave request reference.');
            $targetPeriodId = PayrollPeriod::tryDecodeHash((string) $data['target_payroll_period_id']);
            abort_if($targetPeriodId === null, 422, 'Invalid target payroll period reference.');
            $data['source_leave_request_id'] = $leaveRequestId;
            $data['target_payroll_period_id'] = $targetPeriodId;
        }

        return $data;
    }
}
