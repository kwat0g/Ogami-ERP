import { useState } from 'react';
import { Link } from 'react-router-dom';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import toast from 'react-hot-toast';
import { customerPortalApi } from '@/api/b2b/customer';
import { Button } from '@/components/ui/Button';
import { Chip } from '@/components/ui/Chip';
import { EmptyState } from '@/components/ui/EmptyState';
import { PageHeader } from '@/components/layout/PageHeader';
import { SkeletonTable } from '@/components/ui/Skeleton';
import { formatDate } from '@/lib/formatDate';
import { formatQuantity } from '@/lib/formatNumber';
import type { CustomerOrderLimitIncreaseRequest } from '@/types/b2b';

const variant = (status: CustomerOrderLimitIncreaseRequest['status']) => {
  if (status === 'approved') return 'success' as const;
  if (status === 'rejected') return 'danger' as const;
  if (status === 'requested') return 'warning' as const;
  return 'neutral' as const;
};

export default function CustomerOrderLimitIncreaseRequestsPage() {
  const [page, setPage] = useState(1);
  const queryClient = useQueryClient();
  const query = useQuery({
    queryKey: ['portal', 'customer', 'order-limit-requests', page],
    queryFn: () => customerPortalApi.orderLimitIncreaseRequests(page),
  });
  const cancel = useMutation({
    mutationFn: (request: CustomerOrderLimitIncreaseRequest) => customerPortalApi.cancelOrderLimitIncreaseRequest(request.id, request.version),
    onSuccess: () => {
      toast.success('Request cancelled.');
      void queryClient.invalidateQueries({ queryKey: ['portal', 'customer', 'order-limit-requests'] });
    },
    onError: (error: Error & { response?: { data?: { message?: string } } }) => {
      toast.error(error.response?.data?.message ?? 'Unable to cancel this request. Refresh and try again.');
      void queryClient.invalidateQueries({ queryKey: ['portal', 'customer', 'order-limit-requests'] });
    },
  });

  return <div>
    <PageHeader title="Order limit requests" subtitle="Track requests to raise the maximum quantity allowed on one order." actions={<Link to="/portal/customer/order-limit-requests/new"><Button>Request an increase</Button></Link>} />
    {query.isLoading ? <SkeletonTable columns={5} rows={4} /> : query.isError ? (
      <div className="p-5"><EmptyState icon="alert-circle" title="Unable to load order limit requests" description="Retry to check the latest status." action={<Button onClick={() => void query.refetch()}>Retry</Button>} /></div>
    ) : !query.data?.data.length ? (
      <div className="p-5"><EmptyState icon="package" title="No order limit requests yet" description="If your planned order exceeds the maximum set for your account, ask Sales to review a higher limit." action={<Link to="/portal/customer/order-limit-requests/new"><Button>Request an increase</Button></Link>} /></div>
    ) : (
      <div className="space-y-3 p-4 sm:p-5">
        {query.data.data.map((request) => <article key={request.id} className="rounded-lg border border-default bg-canvas p-4 sm:p-5">
          <div className="flex flex-wrap items-start justify-between gap-3">
            <div className="min-w-0">
              <p className="font-mono text-xs text-muted">{request.product.part_number}</p>
              <h2 className="mt-1 font-semibold break-words">{request.product.name}</h2>
            </div>
            <Chip variant={variant(request.status)}>{request.status_label}</Chip>
          </div>
          <div className="mt-4 grid gap-3 text-sm sm:grid-cols-3">
            <p><span className="text-muted">Current maximum:</span> {formatQuantity(request.current_maximum_quantity)} {request.product.unit_of_measure || 'pieces'}</p>
            <p><span className="text-muted">Requested maximum:</span> {formatQuantity(request.requested_maximum_quantity)} {request.product.unit_of_measure || 'pieces'}</p>
            <p><span className="text-muted">Submitted:</span> {request.created_at ? formatDate(request.created_at) : '—'}</p>
          </div>
          {request.customer_notes && <p className="mt-3 text-sm text-muted">Your note: {request.customer_notes}</p>}
          {request.decision_notes && <p className="mt-3 rounded-md bg-surface px-3 py-2 text-sm">Sales response: {request.decision_notes}</p>}
          {request.available_actions.can_cancel && <div className="mt-4 flex justify-end">
            <Button type="button" size="sm" variant="secondary" loading={cancel.isPending} disabled={cancel.isPending}
              onClick={() => { if (window.confirm('Cancel this order limit request?')) cancel.mutate(request); }}>Cancel request</Button>
          </div>}
        </article>)}
      </div>
    )}
    {query.data && query.data.meta.last_page > 1 && <div className="flex items-center justify-between border-t border-default px-4 py-3 text-sm">
      <span className="text-muted">Page {query.data.meta.current_page} of {query.data.meta.last_page} · {query.data.meta.total} requests</span>
      <div className="flex gap-2">
        <Button size="sm" variant="secondary" disabled={page <= 1} onClick={() => setPage((value) => Math.max(1, value - 1))}>Previous</Button>
        <Button size="sm" variant="secondary" disabled={page >= query.data.meta.last_page} onClick={() => setPage((value) => Math.min(query.data!.meta.last_page, value + 1))}>Next</Button>
      </div>
    </div>}
  </div>;
}
