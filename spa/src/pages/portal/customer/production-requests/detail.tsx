import { useState } from 'react';
import { Link, useNavigate, useParams } from 'react-router-dom';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import toast from 'react-hot-toast';
import { customerPortalApi } from '@/api/b2b/customer';
import { Button } from '@/components/ui/Button';
import { Chip } from '@/components/ui/Chip';
import { EmptyState } from '@/components/ui/EmptyState';
import { PageHeader } from '@/components/layout/PageHeader';
import { Panel } from '@/components/ui/Panel';
import { SkeletonDetail } from '@/components/ui/Skeleton';
import { Textarea } from '@/components/ui/Textarea';
import { formatDateIso } from '@/lib/formatDate';
import { formatQuantity } from '@/lib/formatNumber';

export default function CustomerProductionRequestDetailPage() {
  const { id = '' } = useParams<{ id: string }>();
  const navigate = useNavigate();
  const queryClient = useQueryClient();
  const [reason, setReason] = useState('');
  const query = useQuery({ queryKey: ['portal', 'customer', 'production-request', id], queryFn: () => customerPortalApi.getProductionRequest(id), enabled: !!id });
  const decide = useMutation({
    mutationFn: (decision: 'accept' | 'decline' | 'cancel') => customerPortalApi.decideProductionRequest(id, decision, reason.trim() || undefined),
    onSuccess: (request) => {
      toast.success(request.status === 'accepted' ? 'Ready-date offer accepted.' : request.status === 'declined' ? 'Ready-date offer declined.' : 'Production request cancelled.');
      queryClient.invalidateQueries({ queryKey: ['portal', 'customer', 'production-requests'] });
      queryClient.setQueryData(['portal', 'customer', 'production-request', id], request);
      setReason('');
    },
    onError: (error: Error & { response?: { data?: { message?: string } } }) => toast.error(error.response?.data?.message ?? 'Unable to update this request.'),
  });

  if (query.isLoading) return <div><PageHeader title="Production request" backTo="/portal/customer/production-requests" backLabel="Requests" /><SkeletonDetail /></div>;
  if (query.isError || !query.data) return <div><PageHeader title="Production request" backTo="/portal/customer/production-requests" backLabel="Requests" /><div className="p-5"><EmptyState icon="alert-circle" title="Unable to load this request" action={<Button onClick={() => query.refetch()}>Retry</Button>} /></div></div>;
  const request = query.data;

  return <div>
    <PageHeader title={request.request_number} subtitle="Production request" backTo="/portal/customer/production-requests" backLabel="Requests" />
    <div className="max-w-4xl space-y-4 p-4 sm:p-5">
      <Panel title={request.product?.name ?? 'Product'} actions={<Chip variant={request.status === 'ready_to_order' || request.status === 'converted' ? 'success' : ['declined', 'cancelled', 'expired'].includes(request.status) ? 'danger' : 'warning'}>{request.status_label ?? request.status.replaceAll('_', ' ')}</Chip>}>
        <dl className="grid gap-4 text-sm sm:grid-cols-2">
          <div><dt className="text-muted">Part number</dt><dd className="mt-1 font-mono">{request.product?.part_number ?? '—'}</dd></div>
          <div><dt className="text-muted">Quantity requested</dt><dd className="mt-1 font-mono">{formatQuantity(request.requested_quantity)} {request.product?.unit_of_measure || 'pieces'}</dd></div>
          <div><dt className="text-muted">Your need-by date</dt><dd className="mt-1">{formatDateIso(request.requested_need_by_date, '—')}</dd></div>
          <div><dt className="text-muted">Ogami’s offered ready date</dt><dd className="mt-1">{request.offered_ready_date ? formatDateIso(request.offered_ready_date, '—') : 'Not offered yet'}</dd></div>
          <div className="sm:col-span-2"><dt className="text-muted">Your notes</dt><dd className="mt-1 whitespace-pre-wrap">{request.customer_notes || '—'}</dd></div>
          {request.decision_notes && <div className="sm:col-span-2"><dt className="text-muted">Ogami’s note</dt><dd className="mt-1 whitespace-pre-wrap">{request.decision_notes}</dd></div>}
          {request.hold_expires_at && request.status === 'ready_to_order' && <div className="sm:col-span-2 rounded-md border border-warning bg-warning-bg p-3 text-warning-fg"><dt className="font-medium">Reserved until</dt><dd className="mt-1">{new Date(request.hold_expires_at).toLocaleString()}</dd></div>}
        </dl>
      </Panel>

      {request.status === 'ready_to_order' && <div className="rounded-lg border border-success bg-success-bg p-4 text-sm text-success-fg">
        Ogami has completed the requested quantity and passed it through outgoing quality inspection. The exact quantity is held for you until the time above.
        <div className="mt-3"><Link className="inline-flex min-h-11 items-center rounded-md bg-accent px-4 text-sm font-medium text-accent-fg hover:bg-accent-hover" to={`/portal/customer/orders/new?production_request_id=${encodeURIComponent(request.id)}`}>Place order for {formatQuantity(request.requested_quantity)} pieces</Link></div>
      </div>}

      {request.capabilities.can_accept_offer && <Panel title="Ready-date offer">
        <p className="mb-4 text-sm text-muted">Accept to let Ogami proceed with the planned work order, or decline if this date no longer works for you.</p>
        <Textarea label="Note for Ogami (optional)" rows={3} maxLength={500} value={reason} onChange={(event) => setReason(event.target.value)} placeholder="Add a note about your decision…" />
        <div className="mt-4 flex flex-wrap gap-2"><Button variant="primary" loading={decide.isPending} disabled={decide.isPending} onClick={() => decide.mutate('accept')}>Accept ready date</Button><Button variant="secondary" loading={decide.isPending} disabled={decide.isPending} onClick={() => decide.mutate('decline')}>Decline offer</Button></div>
      </Panel>}

      {request.capabilities.can_cancel && <Panel title="Cancel this request">
        <p className="mb-4 text-sm text-muted">You can cancel before accepting the offered date. Planned work will be safely cancelled where possible.</p>
        <Textarea label="Reason (optional)" rows={2} maxLength={500} value={reason} onChange={(event) => setReason(event.target.value)} placeholder="Tell Ogami why you no longer need this production…" />
        <Button className="mt-3" variant="danger" loading={decide.isPending} disabled={decide.isPending} onClick={() => { if (window.confirm('Cancel this production request?')) decide.mutate('cancel'); }}>Cancel production request</Button>
      </Panel>}

      {request.converted_sales_order && <Panel title="Sales Order"><p className="text-sm">This request was converted to Sales Order <Link className="text-accent underline" to={`/portal/customer/orders/${request.converted_sales_order.id}`}>{request.converted_sales_order.so_number}</Link>.</p></Panel>}

      {request.events?.length ? <Panel title="Request history"><ol className="space-y-3">{request.events.map((event, index) => <li key={`${event.action}-${event.created_at}-${index}`} className="border-l-2 border-default pl-3 text-sm"><p className="font-medium">{event.action.replaceAll('_', ' ')}</p><p className="mt-1 text-xs text-muted">{event.created_at ? new Date(event.created_at).toLocaleString() : '—'}</p></li>)}</ol></Panel> : null}
      <Button variant="ghost" onClick={() => navigate('/portal/customer/production-requests')}>Back to production requests</Button>
    </div>
  </div>;
}
