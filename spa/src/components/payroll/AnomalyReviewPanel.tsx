/** Task A9 — Payroll Anomaly Review panel embedded on payroll period detail. */
import { useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import toast from 'react-hot-toast';
import { AxiosError } from 'axios';
import { LuTriangleAlert, LuCircleCheck } from '@/lib/icons';
import { payrollAnomaliesApi } from '@/api/payroll/anomalies';
import { Button } from '@/components/ui/Button';
import { Chip } from '@/components/ui/Chip';
import { EmptyState } from '@/components/ui/EmptyState';
import { Modal } from '@/components/ui/Modal';
import { Panel } from '@/components/ui/Panel';
import { SkeletonTable } from '@/components/ui/Skeleton';
import { Textarea } from '@/components/ui/Textarea';
import { LinkButton } from '@/components/ui/LinkButton';
import { usePermission } from '@/hooks/usePermission';
import { useQueryRecordTarget } from '@/hooks/useQueryRecordTarget';
import { formatDateTime } from '@/lib/formatDate';
import { invalidateRealtimeChange } from '@/lib/realtime/invalidationMap';
import type { PayrollAnomalyFlag, PayrollAnomalyType, PayrollPeriodStatus } from '@/types/payroll';

interface AnomalyPage {
 data: PayrollAnomalyFlag[];
 meta: { total: number };
}

const TYPE_VARIANT: Record<PayrollAnomalyType, 'danger' | 'warning' | 'info'> = {
 large_change: 'warning',
 excessive_ot: 'warning',
 high_deduction: 'danger',
 first_payroll: 'info',
 zero_pay: 'danger',
 open_attendance: 'warning',
 unpaid_absence: 'warning',
};

interface Props {
 periodId: string;
 periodStatus: PayrollPeriodStatus;
 /** Kept optional for older call sites; creator identity does not block a permitted review. */
 makerIds?: Array<string | null | undefined>;
}

export function AnomalyReviewPanel({ periodId, periodStatus }: Props) {
 const queryClient = useQueryClient();
 const { can } = usePermission();
 const [showResolved, setShowResolved] = useState(false);
 const [remarks, setRemarks] = useState('');
 const [showBulkReview, setShowBulkReview] = useState(false);
 const [bulkRemarks, setBulkRemarks] = useState('');

 const { data, isLoading, isFetching, isError } = useQuery({
 queryKey: ['payroll', periodId, 'anomalies', { resolved: showResolved }],
 queryFn: () => payrollAnomaliesApi.list(periodId, { is_resolved: showResolved, per_page: 100 }),
 enabled: can('payroll.anomalies.review'),
 });
 const flags = data?.data ?? [];
 const resolveActionTarget = useQueryRecordTarget(flags, { isFetching, isError });
 const resolveTarget = resolveActionTarget.target;
 const targetResolved = resolveTarget?.is_resolved === true;

 const resolve = useMutation({
 mutationFn: () => payrollAnomaliesApi.resolve(resolveActionTarget.targetId!, remarks),
 onSuccess: (resolvedFlag) => {
 toast.success('Anomaly marked as reviewed');
  queryClient.setQueryData<AnomalyPage>(
   ['payroll', periodId, 'anomalies', { resolved: false }],
   (page) => page ? {
    ...page,
    data: page.data.filter((flag) => flag.id !== resolvedFlag.id),
    meta: { ...page.meta, total: Math.max(0, page.meta.total - 1) },
   } : page,
  );
  void invalidateRealtimeChange(
   queryClient,
   { entityType: 'payroll_anomaly', entityId: periodId },
   [['payroll-period', periodId]],
  );
 resolveActionTarget.close();
 setRemarks('');
 },
 onError: (e) => {
 void queryClient.invalidateQueries({ queryKey: ['payroll', periodId, 'anomalies'] });
 const msg = e instanceof AxiosError ? e.response?.data?.message : undefined;
 toast.error(msg ?? 'Failed to resolve anomaly');
 },
 });

 const resolveAll = useMutation({
  mutationFn: () => payrollAnomaliesApi.resolveAll(periodId, bulkRemarks.trim()),
  onSuccess: ({ resolved_count: resolvedCount }) => {
   toast.success(`${resolvedCount} anomaly ${resolvedCount === 1 ? 'finding' : 'findings'} marked as reviewed`);
   queryClient.setQueryData<AnomalyPage>(
    ['payroll', periodId, 'anomalies', { resolved: false }],
    (page) => page ? { ...page, data: [], meta: { ...page.meta, total: 0 } } : page,
   );
   void queryClient.invalidateQueries({ queryKey: ['payroll', periodId, 'anomalies'] });
   void invalidateRealtimeChange(
    queryClient,
    { entityType: 'payroll_anomaly', entityId: periodId },
    [['payroll-period', periodId]],
   );
   setShowBulkReview(false);
   setBulkRemarks('');
  },
  onError: (e) => {
   void queryClient.invalidateQueries({ queryKey: ['payroll', periodId, 'anomalies'] });
   const msg = e instanceof AxiosError ? e.response?.data?.message : undefined;
   toast.error(msg ?? 'Failed to review all anomalies');
  },
 });

 if (!can('payroll.anomalies.review')) {
  return (
   <Panel title="Anomaly review">
    <p role="note" className="text-sm text-warning-fg">
     Your role cannot review payroll anomalies. Ask an authorized reviewer to review the findings.
    </p>
   </Panel>
  );
 }

 const total = data?.meta.total ?? 0;
 const hasUnresolved = !showResolved && total > 0;
 const blockedReason = !['computed', 'approved'].includes(periodStatus)
   ? 'Anomalies can only be reviewed while the period is computed or approved.'
   : null;

 return (
 <Panel
 title="Anomaly review"
 meta={total > 0 ? `${total} ${showResolved ? 'resolved' : 'unresolved'}` : undefined}
 noPadding
 >
 <div className="px-3 pt-2 pb-2 flex flex-col gap-2 text-xs sm:flex-row sm:items-center sm:justify-between">
 <span className="text-muted">
 {showResolved
 ? 'Showing resolved anomalies (audit trail).'
 : 'Unresolved anomalies must be reviewed before payroll can be approved or finalized.'}
 </span>
 <div className="flex flex-wrap items-center justify-between gap-2">
 {!showResolved && total > 0 && (
  <Button
   variant="secondary"
   size="sm"
   onClick={() => setShowBulkReview(true)}
   disabled={Boolean(blockedReason) || resolveAll.isPending || isFetching || isError}
  >
   Mark all as reviewed ({total})
  </Button>
 )}
 <LinkButton tone="muted" onClick={() => setShowResolved((v) => !v)}>
 {showResolved ? 'Show unresolved' : 'Show resolved'}
 </LinkButton>
 </div>
 </div>

 {isLoading && !data && <div className="px-3 pb-3"><SkeletonTable columns={1} rows={3} /></div>}
 {isError && (
 <div className="px-3 pb-3">
 <EmptyState icon="alert-circle" title="Failed to load anomalies" />
 </div>
 )}

 {!showResolved && hasUnresolved && blockedReason && (
 <p role="note" className="px-3 pb-2 text-xs text-warning-fg">{blockedReason}</p>
 )}

 {data && flags.length === 0 && (
 <div className="px-3 pb-3">
 <EmptyState
 icon="check-circle"
 title={showResolved ? 'No resolved anomalies yet' : 'No anomalies flagged'}
 description={
 showResolved
 ? 'Resolved anomalies appear here for audit trail.'
 : 'No unresolved anomalies remain for this payroll period.'
 }
 />
 </div>
 )}

 {flags.length > 0 && (
 <ul className="divide-y divide-subtle">
 {flags.map((f) => {
 const Icon = f.is_resolved ? LuCircleCheck : LuTriangleAlert;
 const Detail = (k: string) => (f.details as Record<string, unknown>)[k];
 const previous = Detail('previous');
 const current = Detail('current');
 return (
 <li key={f.id} className="px-3 py-2.5 flex items-start gap-3">
 <Icon
 size={14}
 className={`mt-0.5 shrink-0 ${
 f.is_resolved ? 'text-success-fg' : f.flag_type === 'zero_pay' || f.flag_type === 'high_deduction' ? 'text-danger-fg' : 'text-warning-fg'
 }`}
 />
 <div className="flex-1 min-w-0">
 <div className="flex items-center gap-2 flex-wrap">
 <Chip variant={TYPE_VARIANT[f.flag_type]}>{f.flag_type_label ?? f.flag_type}</Chip>
 {f.employee && (
 <span className="text-sm text-primary">
 <span className="font-mono text-muted">{f.employee.employee_no}</span>{' '}
 {f.employee.name}
 </span>
 )}
 {f.is_resolved && <Chip variant="success">resolved</Chip>}
 </div>
 {(previous != null || current != null) && (
 <p className="text-2xs text-muted font-mono tabular-nums mt-0.5">
 {previous != null && <>previous: <span className="text-primary">{String(previous)}</span> </>}
 {current != null && <>· current: <span className="text-primary">{String(current)}</span></>}
 </p>
 )}
 {f.is_resolved && f.resolution_remarks && (
 <p className="text-xs text-muted mt-0.5">
 <span className="text-subtle">Remarks:</span> {f.resolution_remarks}
 </p>
 )}
 {f.flag_type === 'first_payroll' && (
  <p className="mt-0.5 text-xs text-muted">
   {typeof f.details.explanation === 'string'
    ? f.details.explanation
    : 'The earlier detector checked only the same half of the month. This may be a false positive if the employee has payroll for the other half; verify payroll history before accepting it.'}
  </p>
 )}
 {f.is_resolved && f.resolved_at && (
 <p className="text-2xs text-subtle font-mono tabular-nums mt-0.5">
 Resolved {formatDateTime(f.resolved_at)}
 {f.resolved_by ? ` · ${f.resolved_by.name}` : ''}
 </p>
 )}
 </div>
 {!f.is_resolved && !blockedReason && (
 <Button
 variant="secondary"
 size="sm"
 onClick={() => { resolveActionTarget.open(f); setRemarks(''); }}
 >
 Mark as reviewed
 </Button>
 )}
 </li>
 );
 })}
 </ul>
 )}

 {resolveActionTarget.targetId && (
 <Modal isOpen onClose={resolveActionTarget.close} size="sm" title="Resolve anomaly">
 <div className="px-5 py-4 space-y-3">
 {resolveActionTarget.isChecking && (
 <p role="status" className="text-sm text-muted">Checking the latest anomaly status. Your remarks are kept.</p>
 )}
 {resolveActionTarget.isUnverified && (
 <p role="alert" className="text-sm text-danger-fg">The latest status could not be checked. Your remarks are kept; retry the refresh before resolving.</p>
 )}
 {blockedReason && (
 <p role="alert" className="text-sm text-warning-fg">{blockedReason}</p>
 )}
 {!resolveActionTarget.isChecking && !resolveActionTarget.isUnverified && (resolveActionTarget.isUnavailable || targetResolved) && (
 <p role="alert" className="text-sm text-warning-fg">
 This anomaly is no longer unresolved. The list has refreshed. Your remarks are kept; close this dialog to return to the updated list.
 </p>
 )}
 {resolveActionTarget.isFresh && resolveTarget && !targetResolved && (
 <p className="text-sm text-muted">
 Confirm review for <strong>{resolveTarget.flag_type_label ?? resolveTarget.flag_type}</strong>
 {resolveTarget.employee ? <> on <span className="font-mono">{resolveTarget.employee.employee_no}</span> {resolveTarget.employee.name}</> : null}.
 </p>
 )}
 <Textarea
 label="Remarks"
 value={remarks}
 onChange={(e) => setRemarks(e.target.value)}
 placeholder="Reason for accepting this value (verified with employee, override approved by HR head, etc.)"
 rows={4}
 />
 <div className="flex items-center justify-end gap-2 pt-2 border-t border-default">
 <Button variant="secondary" size="sm" onClick={resolveActionTarget.close}>Cancel</Button>
 <Button
 variant="primary"
 size="sm"
 onClick={() => {
  if (!blockedReason && resolveActionTarget.isFresh && resolveTarget && !targetResolved && remarks.trim().length >= 3) {
   resolve.mutate();
  }
 }}
 disabled={
  resolve.isPending ||
  Boolean(blockedReason) ||
  !resolveActionTarget.isFresh ||
  !resolveTarget ||
  targetResolved ||
  remarks.trim().length < 3
 }
 loading={resolve.isPending}
 >
 Mark reviewed
 </Button>
 </div>
 </div>
 </Modal>
 )}

 {showBulkReview && (
  <Modal isOpen onClose={() => { if (!resolveAll.isPending) setShowBulkReview(false); }} size="md" title="Mark all anomalies as reviewed">
   <div className="px-5 py-4 space-y-3">
    <p className="text-sm text-primary">
     This will mark all <strong>{total}</strong> currently unresolved findings for this payroll period as reviewed, including findings that are not visible on this page. The same reason will be saved to each finding.
    </p>
    <p className="text-xs text-warning-fg">
     Review the findings and supporting records first. Marking a finding as reviewed records your decision; it does not change the payroll amounts or correct the underlying data.
    </p>
    {blockedReason && <p role="alert" className="text-sm text-warning-fg">{blockedReason}</p>}
    <Textarea
     label="Reason for reviewing all findings"
     value={bulkRemarks}
     onChange={(e) => setBulkRemarks(e.target.value)}
     placeholder="Describe what was checked and why the findings are accepted."
     rows={4}
    />
    <div className="flex items-center justify-end gap-2 pt-2 border-t border-default">
     <Button variant="secondary" size="sm" onClick={() => setShowBulkReview(false)} disabled={resolveAll.isPending}>Cancel</Button>
     <Button
      variant="primary"
      size="sm"
      onClick={() => resolveAll.mutate()}
     disabled={resolveAll.isPending || Boolean(blockedReason) || isFetching || isError || total === 0 || bulkRemarks.trim().length < 3}
      loading={resolveAll.isPending}
     >
      Mark {total} as reviewed
     </Button>
    </div>
   </div>
  </Modal>
 )}
 </Panel>
 );
}
