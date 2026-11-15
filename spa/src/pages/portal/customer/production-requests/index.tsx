import { useState } from 'react';
import { Link } from 'react-router-dom';
import { useQuery } from '@tanstack/react-query';
import { customerPortalApi } from '@/api/b2b/customer';
import { Button } from '@/components/ui/Button';
import { Chip } from '@/components/ui/Chip';
import { EmptyState } from '@/components/ui/EmptyState';
import { PageHeader } from '@/components/layout/PageHeader';
import { SkeletonTable } from '@/components/ui/Skeleton';
import { formatDateIso } from '@/lib/formatDate';
import { formatQuantity } from '@/lib/formatNumber';

const statusVariant = (status: string): 'success' | 'warning' | 'danger' | 'neutral' => {
  if (status === 'ready_to_order' || status === 'converted') return 'success';
  if (['requested', 'planning', 'date_offered', 'accepted', 'in_production'].includes(status)) return 'warning';
  if (['declined', 'cancelled', 'expired'].includes(status)) return 'danger';
  return 'neutral';
};

export default function CustomerProductionRequestsPage() {
  const [page, setPage] = useState(1);
  const { data, isLoading, isError, refetch } = useQuery({
    queryKey: ['portal', 'customer', 'production-requests', page],
    queryFn: () => customerPortalApi.listProductionRequests({ page, per_page: 50 }),
  });

  return <div>
    <PageHeader title="Production requests" subtitle="Track quantities you asked Ogami to produce before placing an order." actions={<Link className="inline-flex min-h-11 items-center rounded-md border border-default px-4 text-sm font-medium hover:bg-surface" to="/portal/customer/orders/new">Place an Order</Link>} />
    {isLoading ? <SkeletonTable columns={5} rows={5} /> : isError ? <div className="p-5"><EmptyState icon="alert-circle" title="Unable to load production requests" action={<Button onClick={() => refetch()}>Retry</Button>} /></div> : !data?.data.length ? <div className="p-5"><EmptyState icon="package" title="No production requests yet" description="If the stock available now does not cover your need, ask Ogami to produce the remaining quantity." action={<Link to="/portal/customer/orders/new"><Button>Open product catalog</Button></Link>} /></div> : <div className="space-y-3 p-4 sm:p-5">
      {data.data.map((request) => <Link key={request.id} to={`/portal/customer/production-requests/${request.id}`} className="block rounded-lg border border-default bg-canvas p-4 transition-colors hover:bg-surface focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent">
        <div className="flex flex-wrap items-start justify-between gap-3">
          <div><p className="font-mono text-xs text-muted">{request.request_number}</p><h2 className="mt-1 font-semibold">{request.product?.name ?? 'Product'}</h2><p className="mt-1 text-sm text-muted">{request.product?.part_number ?? '—'} · {formatQuantity(request.requested_quantity)} {request.product?.unit_of_measure || 'pieces'}</p></div>
          <Chip variant={statusVariant(request.status)}>{request.status_label ?? request.status.replaceAll('_', ' ')}</Chip>
        </div>
        <div className="mt-4 grid gap-2 text-sm sm:grid-cols-2"><p><span className="text-muted">Need by:</span> {formatDateIso(request.requested_need_by_date, '—')}</p><p><span className="text-muted">Offered ready date:</span> {request.offered_ready_date ? formatDateIso(request.offered_ready_date, '—') : 'Not offered yet'}</p></div>
      </Link>)}
    </div>}
    {data && data.meta.last_page > 1 && <div className="flex items-center justify-between border-t border-default px-4 py-3 text-sm">
      <span className="text-muted">Page {data.meta.current_page} of {data.meta.last_page} · {data.meta.total} requests</span>
      <div className="flex gap-2">
        <Button size="sm" variant="secondary" disabled={page <= 1} onClick={() => setPage((value) => Math.max(1, value - 1))}>Previous</Button>
        <Button size="sm" variant="secondary" disabled={page >= data.meta.last_page} onClick={() => setPage((value) => Math.min(data.meta.last_page, value + 1))}>Next</Button>
      </div>
    </div>}
  </div>;
}
