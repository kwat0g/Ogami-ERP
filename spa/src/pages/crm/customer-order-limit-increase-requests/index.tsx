import { useMemo, useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import toast from 'react-hot-toast';
import { customerOrderLimitIncreaseRequestsApi } from '@/api/crm/customerOrderLimitIncreaseRequests';
import { Button } from '@/components/ui/Button';
import { Chip, type ChipVariant } from '@/components/ui/Chip';
import { DataTable, type Column } from '@/components/ui/DataTable';
import { EmptyState } from '@/components/ui/EmptyState';
import { Modal, ModalFooter } from '@/components/ui/Modal';
import { PageHeader } from '@/components/layout/PageHeader';
import { Select } from '@/components/ui/Select';
import { Textarea } from '@/components/ui/Textarea';
import { SkeletonTable } from '@/components/ui/Skeleton';
import { formatDate } from '@/lib/formatDate';
import { formatQuantity } from '@/lib/formatNumber';
import type { CustomerOrderLimitIncreaseRequest } from '@/types/b2b';

const statuses: Record<CustomerOrderLimitIncreaseRequest['status'], { label: string; variant: ChipVariant }> = {
  requested: { label: 'Awaiting review', variant: 'warning' },
  approved: { label: 'Approved', variant: 'success' },
  rejected: { label: 'Declined', variant: 'danger' },
  cancelled: { label: 'Cancelled', variant: 'neutral' },
};

export default function CustomerOrderLimitIncreaseRequestsPage() {
  const [page, setPage] = useState(1);
  const [status, setStatus] = useState<CustomerOrderLimitIncreaseRequest['status'] | ''>('requested');
  const [selectedId, setSelectedId] = useState<string | null>(null);
  const [decisionNotes, setDecisionNotes] = useState('');
  const [error, setError] = useState('');
  const queryClient = useQueryClient();
  const query = useQuery({
    queryKey: ['crm', 'order-limit-increase-requests', page, status],
    queryFn: () => customerOrderLimitIncreaseRequestsApi.list(page, status || undefined),
  });
  const requests = query.data?.data ?? [];
  const selected = requests.find((request) => request.id === selectedId) ?? null;
  const action = useMutation({
    mutationFn: (decision: 'approve' | 'reject') => {
      if (!selected || !selected.available_actions.can_decide) throw new Error('This request is no longer open for review. Refresh and try again.');
      return customerOrderLimitIncreaseRequestsApi.decide(selected.id, decision, selected.version, decisionNotes.trim());
    },
    onSuccess: (_updated, decision) => {
      toast.success(decision === 'approve' ? 'Order limit updated.' : 'Request declined.');
      setSelectedId(null);
      setDecisionNotes('');
      setError('');
      void queryClient.invalidateQueries({ queryKey: ['crm', 'order-limit-increase-requests'] });
    },
    onError: (problem: Error & { response?: { data?: { message?: string } } }) => {
      setError(problem.response?.data?.message ?? problem.message ?? 'Unable to update this request. Your note has been kept.');
      void queryClient.invalidateQueries({ queryKey: ['crm', 'order-limit-increase-requests'] });
    },
  });
  const columns = useMemo<Column<CustomerOrderLimitIncreaseRequest>[]>(() => [
    { key: 'customer', header: 'Customer', className: 'min-w-40', cell: (request) => request.customer?.name ?? '—' },
    { key: 'product', header: 'Product', className: 'min-w-44', cell: (request) => <><span className="font-mono">{request.product.part_number}</span><div>{request.product.name}</div></> },
    { key: 'current', header: 'Current maximum', align: 'right', className: 'min-w-32', cell: (request) => <span className="font-mono tabular-nums">{formatQuantity(request.current_maximum_quantity)} {request.product.unit_of_measure || 'pcs'}</span> },
    { key: 'requested', header: 'Requested maximum', align: 'right', className: 'min-w-36', cell: (request) => <span className="font-mono tabular-nums">{formatQuantity(request.requested_maximum_quantity)} {request.product.unit_of_measure || 'pcs'}</span> },
    { key: 'notes', header: 'Customer note', className: 'min-w-48 max-w-72', cell: (request) => <span className="line-clamp-2 text-sm text-muted">{request.customer_notes || '—'}</span> },
    { key: 'status', header: 'Status', className: 'min-w-36', cell: (request) => <Chip variant={statuses[request.status].variant}>{statuses[request.status].label}</Chip> },
    { key: 'submitted', header: 'Submitted', className: 'min-w-32', cell: (request) => request.created_at ? formatDate(request.created_at) : '—' },
    { key: 'actions', header: 'Actions', align: 'right', className: 'min-w-36', cell: (request) => request.available_actions.can_decide
      ? <Button size="sm" variant="secondary" onClick={() => { setSelectedId(request.id); setDecisionNotes(''); setError(''); }}>Review</Button>
      : <span className="text-sm text-muted">—</span> },
  ], []);

  return <div>
    <PageHeader title="Order limit increase requests" subtitle="Review customer requests to raise per-order quantity limits." />
    <div className="flex flex-wrap items-end gap-3 px-4 pt-4 sm:px-5">
      <Select label="Status" value={status} onChange={(event) => { setStatus(event.target.value as typeof status); setPage(1); }} containerClassName="w-full max-w-xs">
        <option value="requested">Awaiting review</option>
        <option value="approved">Approved</option>
        <option value="rejected">Declined</option>
        <option value="cancelled">Cancelled</option>
        <option value="">All requests</option>
      </Select>
    </div>
    {query.isLoading ? <SkeletonTable columns={8} rows={5} /> : query.isError ? (
      <div className="p-5"><EmptyState icon="alert-circle" title="Unable to load requests" action={<Button onClick={() => void query.refetch()}>Retry</Button>} /></div>
    ) : requests.length === 0 ? (
      <div className="p-5"><EmptyState icon="package" title="No requests in this view" description="Customer limit increase requests will appear here for review." /></div>
    ) : <div className="p-4 sm:p-5"><DataTable columns={columns} data={requests} getRowId={(request) => request.id} /></div>}
    {query.data && query.data.meta.last_page > 1 && <div className="flex items-center justify-between border-t border-default px-4 py-3 text-sm">
      <span className="text-muted">Page {query.data.meta.current_page} of {query.data.meta.last_page} · {query.data.meta.total} requests</span>
      <div className="flex gap-2">
        <Button size="sm" variant="secondary" disabled={page <= 1} onClick={() => setPage((value) => Math.max(1, value - 1))}>Previous</Button>
        <Button size="sm" variant="secondary" disabled={page >= query.data.meta.last_page} onClick={() => setPage((value) => Math.min(query.data!.meta.last_page, value + 1))}>Next</Button>
      </div>
    </div>}
    <Modal isOpen={!!selected} onClose={() => { if (!action.isPending) setSelectedId(null); }} title="Review order limit request" size="md">
      {selected && <div className="space-y-4">
        <div className="rounded-md border border-default bg-surface p-4 text-sm">
          <p className="font-medium">{selected.customer?.name} · {selected.product.part_number}</p>
          <p className="mt-2">Current maximum: <strong>{formatQuantity(selected.current_maximum_quantity)} {selected.product.unit_of_measure || 'pcs'}</strong></p>
          <p>Requested maximum: <strong>{formatQuantity(selected.requested_maximum_quantity)} {selected.product.unit_of_measure || 'pcs'}</strong></p>
          {selected.customer_notes && <p className="mt-3 text-muted">Customer note: {selected.customer_notes}</p>}
        </div>
        <Textarea label="Review notes" required rows={3} maxLength={1000} value={decisionNotes} error={error || undefined}
          onChange={(event) => { setDecisionNotes(event.target.value); setError(''); }} placeholder="Explain the decision for the customer…" />
      </div>}
      <ModalFooter>
        <Button variant="secondary" disabled={!decisionNotes.trim() || action.isPending || !selected?.available_actions.can_decide} onClick={() => action.mutate('reject')}>Decline request</Button>
        <Button variant="primary" disabled={!decisionNotes.trim() || action.isPending || !selected?.available_actions.can_decide} loading={action.isPending}
          onClick={() => action.mutate('approve')}>Approve increase</Button>
      </ModalFooter>
    </Modal>
  </div>;
}
