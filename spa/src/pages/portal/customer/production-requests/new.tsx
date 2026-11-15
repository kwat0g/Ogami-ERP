import { useMemo, useRef, useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useNavigate, useSearchParams } from 'react-router-dom';
import toast from 'react-hot-toast';
import { customerPortalApi } from '@/api/b2b/customer';
import { Button } from '@/components/ui/Button';
import { Input } from '@/components/ui/Input';
import { Textarea } from '@/components/ui/Textarea';
import { EmptyState } from '@/components/ui/EmptyState';
import { PageHeader } from '@/components/layout/PageHeader';
import { formatQuantity } from '@/lib/formatNumber';

export default function CustomerProductionRequestCreatePage() {
  const navigate = useNavigate();
  const [searchParams] = useSearchParams();
  const productId = searchParams.get('product_id') ?? '';
  const queryClient = useQueryClient();
  const [neededQuantity, setNeededQuantity] = useState('');
  const [neededBy, setNeededBy] = useState('');
  const [notes, setNotes] = useState('');
  const requestKeyRef = useRef<{ fingerprint: string; key: string } | null>(null);
  const { data: catalog, isLoading, isError, refetch } = useQuery({
    queryKey: ['portal', 'customer', 'catalog', 'production-request'],
    queryFn: () => customerPortalApi.listCatalog(),
  });
  const product = catalog?.find((row) => row.id === productId);
  const available = product?.availability_mode === 'stock_limited' ? Number(product.available_quantity ?? 0) : 0;
  const totalNeeded = Number(neededQuantity);
  const productionQuantity = Number.isSafeInteger(totalNeeded) ? Math.max(0, totalNeeded - Math.floor(available)) : 0;
  const minimum = Math.max(1, Number(product?.minimum_order_quantity ?? 1));
  const maximum = product?.maximum_order_quantity == null ? null : Number(product.maximum_order_quantity);
  const availableOrderQuantity = Number.isSafeInteger(totalNeeded) && totalNeeded > 0
    ? Math.min(totalNeeded, Math.floor(available))
    : 0;
  const quantityError = !Number.isSafeInteger(totalNeeded) || totalNeeded < 1
    ? 'Enter a whole number of at least 1.'
    : productionQuantity < 1 ? 'This quantity is already available to order. Use Place an Order instead.'
      : productionQuantity < minimum ? `The remaining production quantity must be at least ${minimum} units under your customer agreement.`
        : maximum !== null && productionQuantity > maximum ? `The remaining production quantity cannot exceed ${maximum} units under your customer agreement.`
          : productionQuantity > 5000 ? 'The production request is limited to 5,000 pieces.' : undefined;
  const today = new Intl.DateTimeFormat('en-CA', { timeZone: 'Asia/Manila', year: 'numeric', month: '2-digit', day: '2-digit' }).format(new Date());
  const mutation = useMutation({
    mutationFn: () => {
      const payload = { product_id: product!.id, total_needed_quantity: totalNeeded, requested_need_by_date: neededBy, customer_notes: notes.trim() || undefined };
      const fingerprint = JSON.stringify(payload);
      if (requestKeyRef.current?.fingerprint !== fingerprint) requestKeyRef.current = { fingerprint, key: window.crypto.randomUUID() };
      return customerPortalApi.createProductionRequest({ ...payload, request_key: requestKeyRef.current.key });
    },
    onSuccess: (result) => {
      toast.success('Production request submitted. Ogami will review the quantity and date.');
      queryClient.invalidateQueries({ queryKey: ['portal', 'customer', 'production-requests'] });
      navigate(`/portal/customer/production-requests/${result.data.id}`);
    },
    onError: (error: Error & { response?: { data?: { message?: string; errors?: Record<string, string[]> } } }) => {
      const message = Object.values(error.response?.data?.errors ?? {}).flat().join(' ')
        || error.response?.data?.message || 'Unable to submit the production request.';
      toast.error(message);
    },
  });
  const canSubmit = !!product && !quantityError && !!neededBy && neededBy >= today && !mutation.isPending;
  const needDateMin = today;
  const availabilityText = useMemo(() => product
    ? `${formatQuantity(available)} ${product.unit_of_measure || 'pieces'} available to order now`
    : '', [available, product]);

  if (isLoading) return <div className="p-5 text-sm text-muted">Loading your product catalog…</div>;
  if (isError) return <div className="p-5"><EmptyState icon="alert-circle" title="Unable to load the catalog" description="Retry to check product availability." action={<Button onClick={() => refetch()}>Retry</Button>} /></div>;
  if (!product) return <div><PageHeader title="Request production" backTo="/portal/customer/orders/new" backLabel="Place an Order" /><div className="p-5"><EmptyState icon="package" title="Choose a catalog product" description="Open Place an Order and select Request production beside an unavailable stock item." action={<Button onClick={() => navigate('/portal/customer/orders/new')}>Open catalog</Button>} /></div></div>;
  if (product.availability_mode !== 'stock_limited') return <div><PageHeader title="Request production" backTo="/portal/customer/orders/new" backLabel="Place an Order" /><div className="p-5"><EmptyState icon="package" title="This product is made to order" description="Add it to a regular Sales Order from the catalog." action={<Button onClick={() => navigate('/portal/customer/orders/new')}>Place an Order</Button>} /></div></div>;

  return (
    <div>
      <PageHeader title="Request production" subtitle="Tell Ogami how many pieces you need and when you need them." backTo="/portal/customer/orders/new" backLabel="Catalog" />
      <form onSubmit={(event) => { event.preventDefault(); if (canSubmit) mutation.mutate(); }} className="max-w-3xl space-y-5 px-4 py-5 sm:px-5 lg:py-6">
        <section className="rounded-lg border border-default bg-canvas p-4 sm:p-5">
          <h2 className="text-base font-semibold">{product.name}</h2>
          <p className="mt-1 font-mono text-xs text-muted">{product.part_number} · {product.unit_of_measure || 'pieces'}</p>
          <p className="mt-3 text-sm text-muted">{availabilityText}. Enter your total need; the request covers only the remaining quantity.</p>
        </section>
        <section className="grid gap-4 rounded-lg border border-default bg-canvas p-4 sm:grid-cols-2 sm:p-5">
          <Input label="Total pieces needed" type="number" min={1} max={Math.min(available + Math.min(maximum ?? 5000, 5000), 999999)} step={1} required value={neededQuantity}
            error={neededQuantity ? quantityError : undefined} helper={neededQuantity && productionQuantity > 0 ? `${formatQuantity(productionQuantity)} pieces requested for production` : 'Include both any available pieces and pieces you need Ogami to produce.'}
            onChange={(event) => setNeededQuantity(event.target.value)} />
          <Input label="Need-by date" type="date" min={needDateMin} required value={neededBy}
            helper="This is your requested date, not a confirmed promise. Ogami will offer a feasible ready-to-order date after review."
            onChange={(event) => setNeededBy(event.target.value)} />
          <Textarea label="Notes for Ogami (optional)" rows={4} maxLength={1000} containerClassName="sm:col-span-2"
            value={notes} onChange={(event) => setNotes(event.target.value)} placeholder="Add context about your need or timing…" />
        </section>
        <div className="rounded-lg border border-info bg-info-bg p-4 text-sm text-info-fg">
          A production request is not a Sales Order. It does not reserve stock or confirm a delivery date. If stock is available, place a separate order for those pieces from the catalog.
        </div>
        <div className="flex flex-wrap items-center justify-between gap-3">
          {availableOrderQuantity >= minimum && <Button type="button" variant="secondary" onClick={() => navigate(`/portal/customer/orders/new?product_id=${encodeURIComponent(product.id)}&quantity=${encodeURIComponent(String(availableOrderQuantity))}`)}>Order {availableOrderQuantity} available pieces</Button>}
          <Button type="submit" variant="primary" className="ml-auto min-h-11" disabled={!canSubmit} loading={mutation.isPending}>
            {mutation.isPending ? 'Submitting request…' : `Request ${productionQuantity || '…'} pieces`}
          </Button>
        </div>
      </form>
    </div>
  );
}
