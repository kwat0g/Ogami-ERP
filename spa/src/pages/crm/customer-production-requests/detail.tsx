import { useState } from 'react';
import { Link, useParams } from 'react-router-dom';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import toast from 'react-hot-toast';
import { customerProductionRequestsApi } from '@/api/crm/customerProductionRequests';
import { Button } from '@/components/ui/Button';
import { Chip } from '@/components/ui/Chip';
import { EmptyState } from '@/components/ui/EmptyState';
import { Input } from '@/components/ui/Input';
import { PageHeader } from '@/components/layout/PageHeader';
import { Panel } from '@/components/ui/Panel';
import { SkeletonDetail } from '@/components/ui/Skeleton';
import { Textarea } from '@/components/ui/Textarea';
import { usePermission } from '@/hooks/usePermission';
import { formatDateIso } from '@/lib/formatDate';
import { formatQuantity } from '@/lib/formatNumber';

const today = () => new Intl.DateTimeFormat('en-CA', { timeZone: 'Asia/Manila', year: 'numeric', month: '2-digit', day: '2-digit' }).format(new Date());
const plusDays = (days: number) => { const date = new Date(`${today()}T00:00:00Z`); date.setUTCDate(date.getUTCDate() + days); return date.toISOString().slice(0, 10); };

export default function CustomerProductionRequestDetailPage() {
  const { id = '' } = useParams<{ id: string }>();
  const queryClient = useQueryClient();
  const { can } = usePermission();
  const [plannedStart, setPlannedStart] = useState(today());
  const [plannedEnd, setPlannedEnd] = useState(plusDays(1));
  const [readyDate, setReadyDate] = useState(plusDays(7));
  const [notes, setNotes] = useState('');
  const [declineReason, setDeclineReason] = useState('');
  const refresh = async () => {
    await Promise.all([
      queryClient.invalidateQueries({ queryKey: ['crm', 'customer-production-requests'] }),
      queryClient.invalidateQueries({ queryKey: ['crm', 'customer-production-request', id] }),
      queryClient.invalidateQueries({ queryKey: ['mrp', 'plans'] }),
    ]);
  };
  const query = useQuery({ queryKey: ['crm', 'customer-production-request', id], queryFn: () => customerProductionRequestsApi.show(id), enabled: !!id });
  const plan = useMutation({
    mutationFn: () => customerProductionRequestsApi.plan(id, { planned_start: plannedStart, planned_end: plannedEnd }),
    onSuccess: async () => { toast.success('Exact-quantity stock production MRP plan created.'); await refresh(); },
    onError: (error: Error & { response?: { data?: { message?: string } } }) => toast.error(error.response?.data?.message ?? 'Unable to start production planning.'),
  });
  const offer = useMutation({
    mutationFn: () => customerProductionRequestsApi.offer(id, { offered_ready_date: readyDate, notes: notes.trim() || undefined }),
    onSuccess: async () => { toast.success('Ready-date offer sent to the customer.'); await refresh(); },
    onError: (error: Error & { response?: { data?: { message?: string } } }) => toast.error(error.response?.data?.message ?? 'Unable to offer this ready date.'),
  });
  const decline = useMutation({
    mutationFn: () => customerProductionRequestsApi.decline(id, declineReason.trim()),
    onSuccess: async () => { toast.success('Production request declined.'); await refresh(); },
    onError: (error: Error & { response?: { data?: { message?: string } } }) => toast.error(error.response?.data?.message ?? 'Unable to decline this request.'),
  });

  if (query.isLoading) return <div><PageHeader title="Production request" backTo="/crm/customer-production-requests" backLabel="Requests" /><SkeletonDetail /></div>;
  if (query.isError || !query.data) return <div><PageHeader title="Production request" backTo="/crm/customer-production-requests" backLabel="Requests" /><div className="p-5"><EmptyState icon="alert-circle" title="Unable to load this request" action={<Button onClick={() => query.refetch()}>Retry</Button>} /></div></div>;
  const request = query.data;
  const pending = plan.isPending || offer.isPending || decline.isPending;

  return <div>
    <PageHeader title={request.request_number} subtitle={`${request.customer?.name ?? 'Customer'} · ${request.status_label ?? request.status.replaceAll('_', ' ')}`} backTo="/crm/customer-production-requests" backLabel="Requests" />
    <div className="max-w-5xl space-y-4 p-4 sm:p-5">
      <Panel title={request.product?.name ?? 'Product'} actions={<Chip variant={request.status === 'ready_to_order' || request.status === 'converted' ? 'success' : ['declined', 'cancelled', 'expired'].includes(request.status) ? 'danger' : 'warning'}>{request.status_label ?? request.status.replaceAll('_', ' ')}</Chip>}>
        <dl className="grid gap-4 text-sm sm:grid-cols-3">
          <div><dt className="text-muted">Part number</dt><dd className="mt-1 font-mono">{request.product?.part_number ?? '—'}</dd></div>
          <div><dt className="text-muted">Exact requested quantity</dt><dd className="mt-1 font-mono">{formatQuantity(request.requested_quantity)} {request.product?.unit_of_measure || 'pieces'}</dd></div>
          <div><dt className="text-muted">Customer need-by</dt><dd className="mt-1">{formatDateIso(request.requested_need_by_date, '—')}</dd></div>
          <div><dt className="text-muted">Date offered</dt><dd className="mt-1">{request.offered_ready_date ? formatDateIso(request.offered_ready_date, '—') : '—'}</dd></div>
          <div className="sm:col-span-2"><dt className="text-muted">Customer note</dt><dd className="mt-1 whitespace-pre-wrap">{request.customer_notes || '—'}</dd></div>
          {request.decision_notes && <div className="sm:col-span-3"><dt className="text-muted">Decision note</dt><dd className="mt-1 whitespace-pre-wrap">{request.decision_notes}</dd></div>}
        </dl>
      </Panel>

      {request.mrp_plan && <Panel title="MRP plan" actions={<Link className="text-sm text-accent underline" to={`/mrp/plans/${request.mrp_plan.id}`}>{request.mrp_plan.mrp_plan_no}</Link>}>
        <p className="text-sm">MRP is rooted in this customer request and plans exactly {formatQuantity(request.requested_quantity)} finished pieces. Material shortages and purchase requests are reviewed on the MRP plan.</p>
        {request.planning_work_order && <p className="mt-3 text-sm">Root work order: <Link className="font-mono text-accent underline" to={`/production/work-orders/${request.planning_work_order.id}`}>{request.planning_work_order.wo_number}</Link> · {formatQuantity(request.planning_work_order.quantity_target)} pieces · {request.planning_work_order.status.replaceAll('_', ' ')}</p>}
      </Panel>}

      {request.status === 'requested' && can('production.wo.create') && <Panel title="Start stock production planning">
        <p className="mb-4 text-sm text-muted">Creates a request-rooted MRP plan and exact-quantity planned work order. The customer does not create or edit MRP sources.</p>
        <div className="grid gap-3 sm:grid-cols-2"><Input label="Planned start" type="date" min={today()} value={plannedStart} onChange={(event) => setPlannedStart(event.target.value)} /><Input label="Planned end" type="date" min={plannedStart} value={plannedEnd} onChange={(event) => setPlannedEnd(event.target.value)} /></div>
        <Button className="mt-4" variant="primary" loading={plan.isPending} disabled={pending || !plannedStart || !plannedEnd} onClick={() => plan.mutate()}>Create MRP plan and draft work order</Button>
      </Panel>}

      {request.status === 'planning' && can('crm.sales_orders.confirm') && <Panel title="Offer a ready date">
        <p className="mb-4 text-sm text-muted">Review material readiness and production capacity in the linked MRP plan before making a date commitment to the customer.</p>
        <div className="grid gap-3 sm:grid-cols-2"><Input label="Ready-to-order date" type="date" min={today()} required value={readyDate} onChange={(event) => setReadyDate(event.target.value)} /><Textarea label="Message to customer (optional)" rows={3} maxLength={1000} value={notes} onChange={(event) => setNotes(event.target.value)} placeholder="Share any timing details…" /></div>
        <Button className="mt-4" variant="primary" loading={offer.isPending} disabled={pending || !readyDate} onClick={() => offer.mutate()}>Send date offer</Button>
      </Panel>}

      {['requested', 'planning', 'date_offered'].includes(request.status) && can('crm.sales_orders.confirm') && <Panel title="Decline request">
        <p className="mb-4 text-sm text-muted">Use this when Ogami cannot take on the requested production. The customer will receive the reason.</p>
        <Textarea label="Reason" required rows={3} maxLength={500} value={declineReason} onChange={(event) => setDeclineReason(event.target.value)} />
        <Button className="mt-3" variant="danger" loading={decline.isPending} disabled={pending || !declineReason.trim()} onClick={() => { if (window.confirm('Decline this customer production request?')) decline.mutate(); }}>Decline request</Button>
      </Panel>}

      {request.status === 'accepted' && request.planning_work_order && <div className="rounded-lg border border-info bg-info-bg p-4 text-sm text-info-fg">The customer accepted the offered date. PPC can now review and confirm the linked work order: <Link className="font-mono underline" to={`/production/work-orders/${request.planning_work_order.id}`}>{request.planning_work_order.wo_number}</Link>.</div>}
      {request.status === 'ready_to_order' && <div className="rounded-lg border border-success bg-success-bg p-4 text-sm text-success-fg">Finished quantity passed outgoing QC and is held for the customer until {request.hold_expires_at ? new Date(request.hold_expires_at).toLocaleString() : 'the hold expires'}.</div>}
    </div>
  </div>;
}
