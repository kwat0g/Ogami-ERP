import { useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import { useParams } from 'react-router-dom';
import { downloadAuthenticatedFile } from '@/api/download';
import { selfServiceApi } from '@/api/self-service';
import { PageHeader } from '@/components/layout/PageHeader';
import { Button } from '@/components/ui/Button';
import { Chip } from '@/components/ui/Chip';
import { DataTable, NumCell, type Column } from '@/components/ui/DataTable';
import { EmptyState } from '@/components/ui/EmptyState';
import { Panel } from '@/components/ui/Panel';
import { SkeletonTable } from '@/components/ui/Skeleton';
import { formatInt, formatPeso, formatQuantity } from '@/lib/formatNumber';
import { formatDate, formatTime } from '@/lib/formatDate';
import type { Payroll, PayrollAttendanceRecord } from '@/types/payroll';

const attendanceColumns: Column<PayrollAttendanceRecord>[] = [
 {
  key: 'date',
  header: 'Date',
  primary: true,
  cell: (record) => <NumCell className="font-medium">{formatDate(record.date)}</NumCell>,
 },
 {
  key: 'status',
  header: 'Status',
  cell: (record) => <span className="text-sm text-primary">{record.status_label}</span>,
 },
 {
  key: 'time_in',
  header: 'Time in',
  cell: (record) => <NumCell>{formatTime(record.time_in, '—')}</NumCell>,
 },
 {
  key: 'time_out',
  header: 'Time out',
  cell: (record) => <NumCell>{formatTime(record.time_out, '—')}</NumCell>,
 },
 {
  key: 'regular_hours',
  header: 'Regular hrs',
  align: 'right',
  cell: (record) => <NumCell>{formatQuantity(record.regular_hours)}</NumCell>,
 },
 {
  key: 'overtime_hours',
  header: 'OT hrs',
  align: 'right',
  cell: (record) => <NumCell>{formatQuantity(record.overtime_hours)}</NumCell>,
 },
 {
  key: 'night_diff_hours',
  header: 'Night diff hrs',
  align: 'right',
  cell: (record) => <NumCell>{formatQuantity(record.night_diff_hours)}</NumCell>,
 },
 {
  key: 'tardiness_minutes',
  header: 'Late min',
  align: 'right',
  cell: (record) => <NumCell>{formatInt(record.tardiness_minutes)}</NumCell>,
 },
 {
  key: 'undertime_minutes',
  header: 'Undertime min',
  align: 'right',
  cell: (record) => <NumCell>{formatInt(record.undertime_minutes)}</NumCell>,
 },
];

export default function SelfServicePayslipDetailPage() {
 const { id } = useParams<{ id: string }>();
 const [isDownloading, setIsDownloading] = useState(false);
 const { data: payroll, isLoading, isError, refetch } = useQuery({
  queryKey: ['my-payslip', id],
  queryFn: () => selfServiceApi.payslip(id!),
  enabled: !!id,
 });

 const download = async () => {
  if (!id || isDownloading) return;
  setIsDownloading(true);
  try {
   await downloadAuthenticatedFile(selfServiceApi.payslipUrl(id), {
    errorMessage: 'Could not download this payslip. Check your connection and try again.',
   });
  } finally {
   setIsDownloading(false);
  }
 };

 const periodLabel = payroll?.period_start && payroll.period_end
  ? `${formatDate(payroll.period_start)} – ${formatDate(payroll.period_end)}`
  : 'Pay period';

 return (
  <div>
   <PageHeader
    title="Payslip details"
    subtitle={periodLabel}
    backTo="/self-service/payslips"
    backLabel="All payslips"
    actions={id ? (
     <Button variant="primary" size="sm" loading={isDownloading} onClick={() => void download()}>
      Download PDF
     </Button>
    ) : undefined}
   />
   <div className="px-5 py-4 space-y-4 max-w-7xl">
    {isLoading && !payroll && <SkeletonTable columns={6} rows={6} />}

    {isError && !payroll && (
     <EmptyState
      icon="alert-circle"
      title="Payslip unavailable"
      description="This payslip could not be loaded. Check your connection or return to the payslip list and try again."
      action={<Button variant="secondary" onClick={() => void refetch()}>Retry</Button>}
     />
    )}

    {payroll && <PayslipBreakdown payroll={payroll} />}
   </div>
  </div>
 );
}

function PayslipBreakdown({ payroll }: { payroll: Payroll }) {
 const attendance = payroll.attendance_summary;
 const deductions = payroll.deduction_details ?? [];
 const regularDeductions = deductions.filter((item) => item.deduction_type !== 'adjustment');
 const adjustmentItems = deductions.filter((item) => item.deduction_type === 'adjustment');
 const fallbackDeductions = [
  { types: ['sss'], label: 'SSS employee share', amount: payroll.sss_ee },
  { types: ['philhealth'], label: 'PhilHealth employee share', amount: payroll.philhealth_ee },
  { types: ['pagibig'], label: 'Pag-IBIG employee share', amount: payroll.pagibig_ee },
  { types: ['withholding_tax'], label: 'Withholding tax', amount: payroll.withholding_tax },
  { types: ['loan', 'cash_advance'], label: 'Loan and cash advance deductions', amount: payroll.loan_deductions },
  { types: ['other'], label: 'Other deductions', amount: payroll.other_deductions },
 ].filter((fallback) => Number(fallback.amount) > 0
  && !regularDeductions.some((item) => fallback.types.includes(item.deduction_type)));
 const earningsBeforeTimeDeductions = [
  payroll.basic_pay,
  payroll.leave_pay,
  payroll.overtime_pay,
  payroll.night_diff_pay,
  payroll.holiday_pay,
 ].reduce((sum, amount) => sum + Math.round(Number(amount || 0) * 100), 0) / 100;
 const adjustmentAmount = Number(payroll.adjustment_amount || 0);
 const adjustmentLabel = adjustmentAmount > 0
  ? 'Net adjustment added to pay'
  : adjustmentAmount < 0
   ? 'Net adjustment recovery'
   : 'Net adjustment';

 const attendanceMetrics = [
  { label: 'Recorded DTR days', value: formatInt(attendance?.dtr_record_count ?? 0) },
  { label: 'Days worked', value: formatQuantity(payroll.days_worked, '0') },
  { label: 'Present days', value: formatInt(attendance?.present_days ?? 0) },
  { label: 'Late days', value: formatInt(attendance?.late_days ?? 0) },
  { label: 'Absent days', value: formatInt(attendance?.absent_days ?? 0) },
  { label: 'Half days', value: formatInt(attendance?.half_days ?? 0) },
  { label: 'Leave days', value: formatInt(attendance?.leave_days ?? 0) },
  { label: 'Holiday days', value: formatInt(attendance?.holiday_days ?? 0) },
  { label: 'Rest days', value: formatInt(attendance?.rest_days ?? 0) },
  { label: 'Regular hours', value: formatQuantity(attendance?.total_regular_hours ?? '0') },
  { label: 'Overtime hours', value: formatQuantity(attendance?.total_overtime_hours ?? '0') },
  { label: 'Night differential hours', value: formatQuantity(attendance?.total_night_diff_hours ?? '0') },
  { label: 'Late minutes', value: formatInt(attendance?.total_tardiness_minutes ?? 0) },
  { label: 'Undertime minutes', value: formatInt(attendance?.total_undertime_minutes ?? 0) },
 ];

 return (
  <>
   <Panel
    title="Pay summary"
    meta={payroll.period_status_label ? <Chip variant={payroll.period_disbursement_status === 'disbursed' ? 'success' : 'info'}>{payroll.period_status_label}</Chip> : undefined}
   >
    <div className="grid gap-6 md:grid-cols-[minmax(0,1fr)_minmax(16rem,0.8fr)] md:items-center">
     <div>
      <p className="text-sm font-medium text-secondary">Net pay</p>
      <p className="mt-1 font-mono text-3xl font-semibold tabular-nums text-primary">{formatPeso(payroll.net_pay)}</p>
      <p className="mt-2 text-sm text-muted">
       {payroll.period_payroll_date ? `Pay date ${formatDate(payroll.period_payroll_date)}` : 'Published payroll'}
      </p>
     </div>
     <dl className="space-y-2 border-t border-default pt-4 md:border-l md:border-t-0 md:pl-6 md:pt-0">
      <AmountRow label="Gross pay after attendance deductions" amount={payroll.gross_pay} />
      <AmountRow label="Payroll deductions" amount={payroll.total_deductions} />
      <AmountRow label={adjustmentLabel} amount={payroll.adjustment_amount} />
     </dl>
    </div>
   </Panel>

   <div className="grid gap-4 xl:grid-cols-2">
    <Panel title="Earnings and attendance deductions">
     <div className="space-y-2">
      <AmountRow label="Basic pay" amount={payroll.basic_pay} />
      <AmountRow label="Leave pay" amount={payroll.leave_pay} />
      <AmountRow label="Overtime pay" amount={payroll.overtime_pay} helper={`${formatQuantity(attendance?.total_overtime_hours ?? '0')} hours`} />
      <AmountRow label="Night differential" amount={payroll.night_diff_pay} helper={`${formatQuantity(attendance?.total_night_diff_hours ?? '0')} hours`} />
      <AmountRow label="Holiday premium" amount={payroll.holiday_pay} />
      <AmountRow label="Earnings before late and undertime" amount={earningsBeforeTimeDeductions.toFixed(2)} emphasis />
      <div className="border-t border-default pt-2" />
      <AmountRow label="Tardiness deduction" amount={payroll.tardiness_deduction} helper={`${formatInt(attendance?.total_tardiness_minutes ?? 0)} minutes recorded`} />
      <AmountRow label="Undertime deduction" amount={payroll.undertime_deduction} helper={`${formatInt(attendance?.total_undertime_minutes ?? 0)} minutes recorded`} />
      <AmountRow label="Gross pay after time deductions" amount={payroll.gross_pay} emphasis />
     </div>
    </Panel>

    <Panel title="Payroll deductions" meta="Employee share">
     <div className="space-y-2">
      {regularDeductions.map((item, index) => (
       <AmountRow
        key={`${item.deduction_type}-${item.description ?? index}`}
        label={item.description || item.deduction_type_label}
        amount={item.amount}
       />
      ))}
      {fallbackDeductions.map((item) => (
       <AmountRow key={`summary-${item.label}`} label={item.label} amount={item.amount} />
      ))}
      {regularDeductions.length === 0 && fallbackDeductions.length === 0 && (
       <p className="text-sm text-muted">No itemized deductions recorded for this pay period.</p>
      )}
      <AmountRow label="Total payroll deductions" amount={payroll.total_deductions} emphasis />

      {(adjustmentItems.length > 0 || adjustmentAmount !== 0) && (
       <div className="mt-4 border-t border-default pt-3">
        <h3 className="mb-2 text-sm font-semibold text-primary">Payroll adjustments</h3>
        {adjustmentItems.map((item, index) => (
         <AmountRow
          key={`adjustment-${index}`}
          label={item.description || item.deduction_type_label}
          amount={item.amount}
         />
        ))}
        <AmountRow label={adjustmentLabel} amount={payroll.adjustment_amount} emphasis />
        <p className="mt-2 text-xs text-muted">Adjustment lines show their recorded amounts; the signed net adjustment above is what changes your pay.</p>
       </div>
      )}
     </div>
    </Panel>
   </div>

   <Panel title="Attendance summary" meta="Recorded DTR within this pay period">
    <dl className="grid grid-cols-2 gap-x-5 gap-y-4 sm:grid-cols-3 lg:grid-cols-5">
     {attendanceMetrics.map((metric) => (
      <div key={metric.label}>
       <dt className="text-xs text-muted">{metric.label}</dt>
       <dd className="mt-1 font-mono text-sm font-medium tabular-nums text-primary">{metric.value}</dd>
      </div>
     ))}
    </dl>
    <p className="mt-4 border-t border-default pt-3 text-xs text-muted">
     Only recorded DTR entries are shown. Missing dates are not counted as absences.
    </p>
   </Panel>

   <Panel title="Daily attendance" meta={`${attendance?.dtr_record_count ?? 0} recorded ${attendance?.dtr_record_count === 1 ? 'day' : 'days'}`} scrollBody>
    <DataTable
     columns={attendanceColumns}
     data={attendance?.records ?? []}
     tableKey={`payslip-attendance-${payroll.id}`}
     density="compact"
     emptyState={<p className="px-4 py-5 text-sm text-muted">No attendance rows were recorded for this pay period.</p>}
    />
   </Panel>

   <p className="px-1 text-xs text-muted">
    Tardiness and undertime are deducted before gross pay. Payroll deductions are subtracted from gross pay, then the signed adjustment is applied to reach net pay.
   </p>
  </>
 );
}

function AmountRow({
 label,
 amount,
 helper,
 emphasis = false,
}: {
 label: string;
 amount: string | number | null | undefined;
 helper?: string;
 emphasis?: boolean;
}) {
 return (
  <div className="flex items-start justify-between gap-4">
   <div className="min-w-0">
    <p className={emphasis ? 'text-sm font-semibold text-primary' : 'text-sm text-secondary'}>{label}</p>
    {helper && <p className="mt-0.5 text-xs text-muted">{helper}</p>}
   </div>
   <span className={emphasis ? 'shrink-0 font-mono text-sm font-semibold tabular-nums text-primary' : 'shrink-0 font-mono text-sm tabular-nums text-primary'}>
    {formatPeso(amount)}
   </span>
  </div>
 );
}
