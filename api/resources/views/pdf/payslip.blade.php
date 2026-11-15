@php
/**
 * Payslip — A5 portrait. Two payslips fit on a duplex A4 sheet.
 *
 * Inputs (Blade):
 *   $payroll      Payroll model (with employee.department, employee.position, period, deductionDetails)
 *   $employee     Employee model
 *   $period       PayrollPeriod model
 *   $companyName  string
 *   $companyAddress string
 *   $companyTin   string
 *   $generated    array from PdfRenderService: ['by' => string, 'by_user' => ?User, 'at' => CarbonImmutable, 'at_text' => string]
 *   $details      Collection<PayrollDeductionDetail>
 *   $attendanceSummary array from PayrollAttendanceSummaryService
 */
$money = fn ($v) => '₱ ' . number_format((float) $v, 2);
$periodLabel = $period->period_start?->format('M j') . ' – ' . $period->period_end?->format('M j, Y');
$attendanceRecords = collect($attendanceSummary['records'] ?? []);
$deductionLines = $details->reject(fn ($detail) => $detail->deduction_type?->value === 'adjustment');
$adjustmentLines = $details->filter(fn ($detail) => $detail->deduction_type?->value === 'adjustment');
$deductionTypes = $deductionLines->map(fn ($detail) => $detail->deduction_type?->value)->all();
$deductionFallbacks = collect([
  ['types' => ['sss'], 'label' => 'SSS employee share', 'amount' => $payroll->sss_ee],
  ['types' => ['philhealth'], 'label' => 'PhilHealth employee share', 'amount' => $payroll->philhealth_ee],
  ['types' => ['pagibig'], 'label' => 'Pag-IBIG employee share', 'amount' => $payroll->pagibig_ee],
  ['types' => ['withholding_tax'], 'label' => 'Withholding tax', 'amount' => $payroll->withholding_tax],
  ['types' => ['loan', 'cash_advance'], 'label' => 'Loan and cash advance deductions', 'amount' => $payroll->loan_deductions],
  ['types' => ['other'], 'label' => 'Other deductions', 'amount' => $payroll->other_deductions],
])->filter(fn ($line) => (float) $line['amount'] > 0
  && count(array_intersect($line['types'], $deductionTypes)) === 0);
$earningsBeforeTimeDeductions = (float) $payroll->basic_pay
  + (float) $payroll->leave_pay
  + (float) $payroll->overtime_pay
  + (float) $payroll->night_diff_pay
  + (float) $payroll->holiday_pay;
@endphp
<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8" />
  <title>Payslip · {{ $employee->employee_no }} · {{ $period->period_start?->format('Y-m-d') }}</title>
  <style>
    @page { size: A4 portrait; margin: 12mm; }
    body  { font-family: 'Helvetica Neue', 'Helvetica', 'DejaVu Sans', sans-serif; font-size: 9px; color: #334155; }
    .mono { font-family: 'Courier New', Courier, 'DejaVu Sans Mono', monospace; }
    h1 { font-size: 14px; font-weight: bold; margin: 0 0 6px; letter-spacing: 0.05em; text-transform: uppercase; color: #0f172a; }
    h2 { font-size: 10px; font-weight: bold; margin: 12px 0 5px; letter-spacing: 0.05em; text-transform: uppercase; color: #64748b; border-bottom: 1px solid #e2e8f0; padding-bottom: 3px; }
    .header { border-bottom: 2px solid #f1f5f9; padding-bottom: 10px; margin-bottom: 14px; }
    .header .company { font-weight: bold; font-size: 13px; color: #0f172a; text-transform: uppercase; }
    .header .meta    { color: #64748b; font-size: 9.5px; margin-top: 3px; }
    .grid { width: 100%; border-collapse: collapse; }
    .grid td { padding: 3px 0; vertical-align: top; }
    .grid .label   { color: #94a3b8; width: 35%; font-size: 9px; text-transform: uppercase; letter-spacing: 0.05em; font-weight: bold; }
    .grid .value   { font-weight: bold; color: #0f172a; }

    table.tab { width: 100%; border-collapse: collapse; margin-top: 5px; }
    table.tab th, table.tab td { padding: 4px 6px; font-size: 9px; }
    table.tab th { text-align: left; color: #ffffff; background: #0f172a; font-size: 9px; text-transform: uppercase; letter-spacing: 0.05em; font-weight: bold; border: none; }
    table.tab td { border-bottom: 1px solid #e2e8f0; color: #334155; }
    table.tab td.amt { text-align: right; font-weight: bold; }
    table.tab thead { display: table-header-group; }
    table.tab tr { page-break-inside: avoid; }
    table.attendance th, table.attendance td { padding: 3px 4px; font-size: 7.5px; }
    table.attendance td { white-space: nowrap; }
    .note { margin-top: 5px; color: #64748b; font-size: 8px; line-height: 1.35; }
    .summary-grid { width: 100%; border-collapse: collapse; margin-top: 4px; }
    .summary-grid td { width: 25%; padding: 4px 6px; border-bottom: 1px solid #e2e8f0; }
    .summary-grid .label { color: #64748b; font-size: 8px; }
    .summary-grid .value { display: block; margin-top: 2px; font-weight: bold; color: #0f172a; }
    .total-row td { border-top: 1px solid #cbd5e1; font-weight: bold; }

    .net { margin-top: 14px; padding: 10px 12px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; display: table; width: 100%; }
    .net .lbl { display: table-cell; width: 60%; font-weight: bold; text-transform: uppercase; letter-spacing: 0.05em; font-size: 10px; color: #0f172a; }
    .net .amt { display: table-cell; text-align: right; font-size: 15px; font-weight: bold; font-family: 'Courier New', 'DejaVu Sans Mono', monospace; color: #0f172a; }

    .footer { position: fixed; bottom: 6mm; left: 12mm; right: 12mm; font-size: 8px; color: #94a3b8; text-align: center; border-top: 1px solid #e2e8f0; padding-top: 6px; }
    .watermark { position: fixed; top: 40%; left: 0; right: 0; text-align: center; transform: rotate(-30deg); font-size: 70px; color: rgba(15, 23, 42, 0.03); font-weight: bold; letter-spacing: 0.1em; z-index: 0; }
    .container { position: relative; z-index: 1; }
  </style>
</head>
<body>
  <div class="watermark">CONFIDENTIAL</div>
  <div class="container">

    <div class="header">
      <div class="company">{{ $companyName }}</div>
      <div class="meta">{{ $companyAddress }} · TIN {{ $companyTin }}</div>
    </div>

    <h1>Payslip</h1>
    <table class="grid">
      <tr><td class="label">Employee</td><td class="value">{{ $employee->full_name }}</td></tr>
      <tr><td class="label">Employee No.</td><td class="value mono">{{ $employee->employee_no }}</td></tr>
      <tr><td class="label">Department</td><td class="value">{{ $employee->department?->name ?? '—' }}</td></tr>
      <tr><td class="label">Position</td><td class="value">{{ $employee->position?->title ?? '—' }}</td></tr>
      <tr><td class="label">Pay Type</td><td class="value">{{ ucfirst($payroll->pay_type) }}</td></tr>
      <tr><td class="label">Period</td><td class="value mono">{{ $periodLabel }}</td></tr>
      <tr><td class="label">Payroll Date</td><td class="value mono">{{ $period->payroll_date?->format('M j, Y') }}</td></tr>
      <tr><td class="label">Status</td><td class="value">{{ $period->status?->label() ?? 'Published' }}{{ $period->disbursement_status === 'disbursed' ? ' · Disbursed' : '' }}</td></tr>
    </table>

    <h2>Earnings</h2>
    <table class="tab">
      <thead>
        <tr><th>Item</th><th class="amt" style="text-align:right">Amount</th></tr>
      </thead>
      <tbody>
        <tr><td>Basic Pay</td><td class="amt mono">{{ $money($payroll->basic_pay) }}</td></tr>
        @if ((float) $payroll->leave_pay > 0)
          <tr><td>Leave Pay</td><td class="amt mono">{{ $money($payroll->leave_pay) }}</td></tr>
        @endif
        @if ((float) $payroll->overtime_pay > 0)
          <tr><td>Overtime Pay</td><td class="amt mono">{{ $money($payroll->overtime_pay) }}</td></tr>
        @endif
        @if ((float) $payroll->night_diff_pay > 0)
          <tr><td>Night Differential</td><td class="amt mono">{{ $money($payroll->night_diff_pay) }}</td></tr>
        @endif
        @if ((float) $payroll->holiday_pay > 0)
          <tr><td>Holiday Premium</td><td class="amt mono">{{ $money($payroll->holiday_pay) }}</td></tr>
        @endif
        <tr class="total-row"><td>Earnings before attendance deductions</td><td class="amt mono">{{ $money($earningsBeforeTimeDeductions) }}</td></tr>
        <tr><td>Tardiness deduction · {{ $attendanceSummary['total_tardiness_minutes'] ?? 0 }} min</td><td class="amt mono">{{ $money(-1 * (float) $payroll->tardiness_deduction) }}</td></tr>
        <tr><td>Undertime deduction · {{ $attendanceSummary['total_undertime_minutes'] ?? 0 }} min</td><td class="amt mono">{{ $money(-1 * (float) $payroll->undertime_deduction) }}</td></tr>
        <tr class="total-row"><td>Gross pay after attendance deductions</td><td class="amt mono">{{ $money($payroll->gross_pay) }}</td></tr>
      </tbody>
    </table>

    <h2>Attendance summary</h2>
    <table class="summary-grid">
      <tr>
        <td><span class="label">Recorded DTR days</span><span class="value mono">{{ $attendanceSummary['dtr_record_count'] ?? 0 }}</span></td>
        <td><span class="label">Days worked</span><span class="value mono">{{ $payroll->days_worked ?? '0.0' }}</span></td>
        <td><span class="label">Present / late</span><span class="value mono">{{ $attendanceSummary['present_days'] ?? 0 }} / {{ $attendanceSummary['late_days'] ?? 0 }}</span></td>
        <td><span class="label">Absent / leave</span><span class="value mono">{{ $attendanceSummary['absent_days'] ?? 0 }} / {{ $attendanceSummary['leave_days'] ?? 0 }}</span></td>
      </tr>
      <tr>
        <td><span class="label">Regular hours</span><span class="value mono">{{ $attendanceSummary['total_regular_hours'] ?? '0.00' }}</span></td>
        <td><span class="label">Overtime hours</span><span class="value mono">{{ $attendanceSummary['total_overtime_hours'] ?? '0.00' }}</span></td>
        <td><span class="label">Night differential hours</span><span class="value mono">{{ $attendanceSummary['total_night_diff_hours'] ?? '0.00' }}</span></td>
        <td><span class="label">Late / undertime minutes</span><span class="value mono">{{ $attendanceSummary['total_tardiness_minutes'] ?? 0 }} / {{ $attendanceSummary['total_undertime_minutes'] ?? 0 }}</span></td>
      </tr>
    </table>
    <p class="note">Only recorded DTR entries are included. Missing dates are not counted as absences.</p>

    <h2>Daily attendance</h2>
    <table class="tab attendance">
      <thead>
        <tr>
          <th>Date</th><th>Status</th><th>Time in</th><th>Time out</th>
          <th style="text-align:right">Regular</th><th style="text-align:right">OT</th>
          <th style="text-align:right">Night diff</th><th style="text-align:right">Late min</th>
          <th style="text-align:right">Under min</th>
        </tr>
      </thead>
      <tbody>
        @forelse ($attendanceRecords as $record)
          <tr>
            <td class="mono">{{ $record['date'] ? \Carbon\Carbon::parse($record['date'])->format('M j') : '—' }}</td>
            <td>{{ $record['status_label'] ?? '—' }}</td>
            <td class="mono">{{ $record['time_in'] ? \Carbon\Carbon::parse($record['time_in'])->format('g:i A') : '—' }}</td>
            <td class="mono">{{ $record['time_out'] ? \Carbon\Carbon::parse($record['time_out'])->format('g:i A') : '—' }}</td>
            <td class="amt mono">{{ $record['regular_hours'] }}</td>
            <td class="amt mono">{{ $record['overtime_hours'] }}</td>
            <td class="amt mono">{{ $record['night_diff_hours'] }}</td>
            <td class="amt mono">{{ $record['tardiness_minutes'] }}</td>
            <td class="amt mono">{{ $record['undertime_minutes'] }}</td>
          </tr>
        @empty
          <tr><td colspan="9" style="color:#64748b">No attendance rows were recorded for this pay period.</td></tr>
        @endforelse
      </tbody>
    </table>

    <h2>Deductions</h2>
    <table class="tab">
      <thead>
        <tr><th>Item</th><th class="amt" style="text-align:right">Amount</th></tr>
      </thead>
      <tbody>
        @if ($deductionLines->isEmpty() && $deductionFallbacks->isEmpty())
          <tr><td colspan="2" style="color:#A1A1AA">No deductions for this period.</td></tr>
        @endif
        @foreach ($deductionLines as $d)
          <tr>
            <td>{{ $d->description ?? $d->deduction_type?->label() }}</td>
            <td class="amt mono">{{ $money($d->amount) }}</td>
          </tr>
        @endforeach
        @foreach ($deductionFallbacks as $line)
          <tr>
            <td>{{ $line['label'] }}</td>
            <td class="amt mono">{{ $money($line['amount']) }}</td>
          </tr>
        @endforeach
        <tr><td><strong>Total Deductions</strong></td><td class="amt mono"><strong>{{ $money($payroll->total_deductions) }}</strong></td></tr>
      </tbody>
    </table>

    @if ($adjustmentLines->isNotEmpty() || abs((float) $payroll->adjustment_amount) > 0.001)
      <h2>Adjustments</h2>
      <table class="tab">
        @foreach ($adjustmentLines as $d)
          <tr>
            <td>{{ $d->description ?? $d->deduction_type?->label() }}</td>
            <td class="amt mono">{{ $money($d->amount) }}</td>
          </tr>
        @endforeach
        <tr class="total-row">
          <td>Net adjustment ({{ (float) $payroll->adjustment_amount > 0 ? 'added to pay' : ((float) $payroll->adjustment_amount < 0 ? 'recovery' : 'no net change') }})</td>
          <td class="amt mono">{{ $money($payroll->adjustment_amount) }}</td>
        </tr>
      </table>
    @endif

    <p class="note">Tardiness and undertime are deducted before gross pay. Payroll deductions are subtracted from gross pay; the signed adjustment is then applied to net pay.</p>

    <div class="net">
      <div class="lbl">Net Pay</div>
      <div class="amt">{{ $money($payroll->net_pay) }}</div>
    </div>

    <table style="width:100%; margin-top:32px; border-collapse:collapse; font-size:9pt;">
      <tr>
        <td style="width:50%; vertical-align:bottom; padding:0 8px;">
          <div style="height:32px; border-bottom:1px solid #444;">&nbsp;</div>
          <div style="margin-top:4px; text-align:center; font-weight:normal;">{{ $preparedBy ?? '—' }}</div>
          <div style="text-align:center; color:#777; font-size:8pt;">Prepared by · HR</div>
        </td>
        <td style="width:50%; vertical-align:bottom; padding:0 8px;">
          <div style="height:32px; border-bottom:1px solid #444;">&nbsp;</div>
          <div style="margin-top:4px; text-align:center; font-weight:normal;">{{ $verifiedBy ?? '—' }}</div>
          <div style="text-align:center; color:#777; font-size:8pt;">Verified by · Finance</div>
        </td>
      </tr>
    </table>

    @if ($period->disbursement_status === 'disbursed')
    {{-- ADV1 — Salary Disbursement Certification Footer --}}
    <table style="width:100%; margin-top:24px; border-collapse:collapse; border-top:0.5px solid #D4D4D8;">
      <tr>
        <td colspan="2" style="padding-top:8px; font-size:9px; letter-spacing:0.04em; text-transform:uppercase; color:#52525B; font-weight:bold;">
          Disbursement Certification
        </td>
      </tr>
      <tr>
        <td style="padding-top:4px; font-size:8px; color:#71717A;">
          I certify that the above payroll has been processed and disbursed.
        </td>
      </tr>
    </table>
    <table style="width:100%; margin-top:16px; border-collapse:collapse; font-size:8pt;">
      <tr>
        <td style="width:50%; vertical-align:bottom; padding:0 8px;">
          <div style="height:28px; border-bottom:0.5px solid #A1A1AA;">&nbsp;</div>
          <div style="margin-top:4px; text-align:center; font-weight:normal; color:#52525B;">Prepared by</div>
          <div style="text-align:center; color:#A1A1AA; font-size:7pt;">Date: ___________</div>
        </td>
        <td style="width:50%; vertical-align:bottom; padding:0 8px;">
          <div style="height:28px; border-bottom:0.5px solid #A1A1AA;">&nbsp;</div>
          <div style="margin-top:4px; text-align:center; font-weight:normal; color:#52525B;">Finance Officer</div>
          <div style="text-align:center; color:#A1A1AA; font-size:7pt;">Date: ___________</div>
        </td>
      </tr>
    </table>
    @endif

  </div>
  <div class="footer">
    Generated by {{ $generated['by'] ?? 'system' }} on {{ ($generated['at'] ?? now())->format('M j, Y · g:i a') }} · Confidential
  </div>
</body>
</html>
