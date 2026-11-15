import { useEffect, useState } from 'react';
import { useNavigate, useLocation } from 'react-router-dom';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useForm, useWatch } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { z } from 'zod';
import toast from 'react-hot-toast';
import { AxiosError } from 'axios';
import { adjustmentsApi } from '@/api/payroll/adjustments';
import { payrollsApi } from '@/api/payroll/payrolls';
import { Button } from '@/components/ui/Button';
import { Input } from '@/components/ui/Input';
import { Select } from '@/components/ui/Select';
import { Textarea } from '@/components/ui/Textarea';
import { PageHeader } from '@/components/layout/PageHeader';
import { applyServerValidationErrors, onFormInvalid } from '@/lib/formErrors';
import type { ApiValidationError } from '@/types';

import { useFormSafety } from '@/hooks/useFormSafety';
import { FormDraftBanner } from '@/components/ui/FormDraftBanner';
import { FormActions } from '@/components/ui/FormActions';
import { reconcileRealtimeMutationResponse } from '@/lib/realtime/invalidationMap';
const schema = z.object({
 original_payroll_id: z.string().min(1, 'Source payroll is required'),
 type: z.string().min(1, 'Adjustment type is required'),
 amount: z.string().min(1, 'Amount is required'),
 reason: z.string().min(5, 'Reason is required (min 5 chars)').max(1000),
 source_leave_request_id: z.string().optional(),
 target_payroll_period_id: z.string().optional(),
}).superRefine((values, context) => {
 if (values.type === 'unpaid_absence' && !values.source_leave_request_id) {
  context.addIssue({ code: 'custom', path: ['source_leave_request_id'], message: 'Approved unpaid leave is required' });
 }
 if (values.type === 'unpaid_absence' && !values.target_payroll_period_id) {
  context.addIssue({ code: 'custom', path: ['target_payroll_period_id'], message: 'Target payroll cutoff is required' });
 }
});

type FormValues = z.infer<typeof schema>;

export default function CreatePayrollAdjustmentPage() {
 const navigate = useNavigate();
 const qc = useQueryClient();
 const location = useLocation() as { state?: { original_payroll_id?: string; employee?: { full_name?: string; employee_no?: string } } };
 const [submitting, setSubmitting] = useState(false);
 const [payrollSearch, setPayrollSearch] = useState('');
 const { data: adjustmentOptions } = useQuery({
 queryKey: ['payroll-adjustments', 'options'],
 queryFn: () => adjustmentsApi.options(),
 });
 const { data: payrolls } = useQuery({
 queryKey: ['payrolls', 'adjustment-source', payrollSearch],
 queryFn: () => payrollsApi.list({ per_page: 25, search: payrollSearch || undefined }),
 });

  const form = useForm<FormValues>({
 resolver: zodResolver(schema),
 defaultValues: {
 original_payroll_id: location.state?.original_payroll_id ?? '',
 type: '',
 },
 });
 const { register, handleSubmit, setError, setValue, control, formState: { errors } } = form;
 const selectedType = useWatch({ control, name: 'type' });
 const originalPayrollId = useWatch({ control, name: 'original_payroll_id' });
 const selectedLeaveRequestId = useWatch({ control, name: 'source_leave_request_id' });
 const {
  data: unpaidAbsenceOptions,
  isFetching: unpaidAbsenceOptionsLoading,
  isError: unpaidAbsenceOptionsError,
  refetch: refetchUnpaidAbsenceOptions,
 } = useQuery({
  queryKey: ['payroll-adjustments', 'unpaid-absence-options', originalPayrollId, selectedLeaveRequestId],
  queryFn: () => adjustmentsApi.unpaidAbsenceOptions({
   original_payroll_id: originalPayrollId,
   leave_request_id: selectedLeaveRequestId || undefined,
  }),
  enabled: selectedType === 'unpaid_absence' && !!originalPayrollId,
 });

 useEffect(() => {
  setValue('source_leave_request_id', '');
  setValue('target_payroll_period_id', '');
 }, [originalPayrollId, setValue]);

 useEffect(() => {
  if (selectedType !== 'unpaid_absence') {
   setValue('source_leave_request_id', '');
   setValue('target_payroll_period_id', '');
  }
 }, [selectedType, setValue]);

 const mutation = useMutation({
 mutationFn: (data: FormValues) => {
  const payload = { ...data };
  if (payload.type !== 'unpaid_absence') {
   delete payload.source_leave_request_id;
   delete payload.target_payroll_period_id;
  }
  return adjustmentsApi.create(payload as Parameters<typeof adjustmentsApi.create>[0]);
 },
 onSuccess: (created) => {
 reconcileRealtimeMutationResponse(
  qc,
  { entityType: 'payroll_adjustment', entityId: created.id },
  ['payroll-adjustment', created.id],
  created,
 );
 toast.success('Adjustment submitted for approval.');
  safety.allowNavigationAfterSave();
navigate('/payroll/adjustments');
 },
 onError: (error: AxiosError<ApiValidationError>) => {
 if (!applyServerValidationErrors(error, setError, 'Failed to submit adjustment.')) {
 toast.error(error.response?.data?.message ?? 'Failed to submit adjustment.');
 }
 },
 });
 const safety = useFormSafety({ form, saved: mutation.isSuccess });

 const onSubmit = (data: FormValues) => {
 setSubmitting(true);
 mutation.mutate(data, { onSettled: () => setSubmitting(false) });
 };

 const empName = location.state?.employee?.full_name;
 const empNo = location.state?.employee?.employee_no;

 return (
 <div>
 <PageHeader title="Raise Payroll Adjustment" backTo="/payroll/adjustments" backLabel="Adjustments" />
      <FormDraftBanner safety={safety} />
 <form onSubmit={handleSubmit(onSubmit, onFormInvalid<FormValues>())} className="min-w-0 w-full px-5 py-4">
 {empName && (
 <div className="mb-4 p-3 bg-surface border border-default rounded-md text-xs">
 <div className="text-muted uppercase tracking-wider mb-1">Source payroll</div>
 <div className="font-medium">{empName}</div>
 {empNo && <div className="font-mono text-muted">{empNo}</div>}
 </div>
 )}

 <fieldset className="mb-8">
 <legend className="text-xs uppercase tracking-wider text-muted font-medium mb-4">Adjustment</legend>
 <div className="grid grid-cols-2 gap-3">

 <Select searchable onSearch={(value) => setPayrollSearch(value)} title="Search employee name or number"
 label="Source payroll"
 required
 {...register('original_payroll_id')}
 error={errors.original_payroll_id?.message}
 disabled={!!location.state?.original_payroll_id}
 >
 <option value="">— Select payroll —</option>
 {(payrolls?.data ?? []).map((payroll) => (
 <option key={payroll.id} value={payroll.id}>
 {payroll.employee?.full_name ?? 'Unknown employee'} ({payroll.employee?.employee_no ?? '—'}) · {payroll.period_start ?? '—'} to {payroll.period_end ?? '—'}
 </option>
 ))}
 </Select>
 <Select label="Type" required {...register('type')} error={errors.type?.message}>
 <option value="">— Select —</option>
 {(adjustmentOptions?.types ?? []).map((type) => <option key={type.value} value={type.value}>{type.label}</option>)}
 </Select>
 {selectedType === 'unpaid_absence' && (
  <>
   <Select
    label="Approved unpaid leave"
    helper={unpaidAbsenceOptionsError
     ? 'Could not load leave requests.'
     : !originalPayrollId
      ? 'Select a source payroll first.'
      : unpaidAbsenceOptions && unpaidAbsenceOptions.leave_requests.length === 0
       ? 'No approved unpaid leave found for this employee.'
       : 'Only approved unpaid leave for the source employee is listed.'}
    loading={unpaidAbsenceOptionsLoading}
    required
    {...register('source_leave_request_id')}
    error={errors.source_leave_request_id?.message}
   >
    <option value="">— Select leave request —</option>
    {(unpaidAbsenceOptions?.leave_requests ?? []).map((leave) => (
     <option key={leave.id} value={leave.id}>
      {leave.leave_request_no} · {leave.start_date} to {leave.end_date} · {leave.days} day(s)
     </option>
    ))}
   </Select>
   <Select
    label="Target payroll cutoff"
    helper={!selectedLeaveRequestId
     ? 'Choose the leave request first.'
     : unpaidAbsenceOptions && unpaidAbsenceOptions.target_periods.length === 0
      ? 'No draft or computed cutoff overlaps these dates and includes this employee.'
      : 'The deduction applies only to this cutoff.'}
    loading={unpaidAbsenceOptionsLoading}
    disabled={!selectedLeaveRequestId}
    required
    {...register('target_payroll_period_id')}
    error={errors.target_payroll_period_id?.message}
   >
    <option value="">— Select cutoff —</option>
    {(unpaidAbsenceOptions?.target_periods ?? []).map((period) => (
     <option key={period.id} value={period.id}>
      {period.label} · {period.status}
     </option>
    ))}
   </Select>
   {unpaidAbsenceOptionsError && (
    <div role="alert" className="col-span-2 flex items-center gap-2 text-xs text-danger-fg">
     <span>Unpaid leave options could not be loaded.</span>
     <Button type="button" variant="secondary" size="xs" onClick={() => void refetchUnpaidAbsenceOptions()}>
      Retry options
     </Button>
    </div>
   )}
  </>
 )}
 <Input
 label={selectedType === 'unpaid_absence' ? 'Deduction amount for this cutoff' : 'Amount'}
 {...register('amount')}
 error={errors.amount?.message}
 prefix="₱"
 placeholder="0.00"
 required
 className="font-mono"
 />
 </div>
 </fieldset>

 <fieldset className="mb-8">
 <legend className="text-xs uppercase tracking-wider text-muted font-medium mb-4">Justification</legend>
 <Textarea
 label="Reason"
 {...register('reason')}
 error={errors.reason?.message}
 rows={5}
 placeholder="Explain what was wrong with the original payroll and what the adjustment corrects."
 required
 />
 <p className="text-xs text-muted mt-2">
 {selectedType === 'unpaid_absence'
  ? 'Enter the approved deduction amount for the selected cutoff. It will be queued for approval and will apply only when that payroll is computed or recomputed.'
  : 'The adjustment will be queued for approval. Once approved, it applies automatically to the next non-finalized period.'}
 </p>
 </fieldset>

 <FormActions>
 <Button type="button" variant="secondary" onClick={() => navigate('/payroll/adjustments')}
 disabled={submitting || mutation.isPending}>
 Cancel
 </Button>
 <Button type="submit" variant="primary"
 disabled={submitting || mutation.isPending} loading={mutation.isPending}>
 {mutation.isPending ? 'Submitting…' : 'Submit adjustment'}
 </Button>
 </FormActions>
 </form>
 </div>
 );
}
