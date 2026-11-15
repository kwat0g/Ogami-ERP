import { useMemo, useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useNavigate, useSearchParams } from 'react-router-dom';
import toast from 'react-hot-toast';
import { customerPortalApi } from '@/api/b2b/customer';
import { Button } from '@/components/ui/Button';
import { EmptyState } from '@/components/ui/EmptyState';
import { Input } from '@/components/ui/Input';
import { PageHeader } from '@/components/layout/PageHeader';
import { Select } from '@/components/ui/Select';
import { Textarea } from '@/components/ui/Textarea';
import { formatQuantity } from '@/lib/formatNumber';

const MAX_REQUESTED_LIMIT = 999999999;

export default function CustomerOrderLimitIncreaseRequestPage() {
  const navigate = useNavigate();
  const [searchParams] = useSearchParams();
  const queryClient = useQueryClient();
  const [productId, setProductId] = useState(searchParams.get('product_id') ?? '');
  const [requestedMaximum, setRequestedMaximum] = useState(searchParams.get('requested_maximum_quantity') ?? '');
  const [notes, setNotes] = useState('');
  const [submitError, setSubmitError] = useState('');
  const catalog = useQuery({
    queryKey: ['portal', 'customer', 'catalog', 'order-limit-increase'],
    queryFn: () => customerPortalApi.listCatalog(),
  });
  const limitedProducts = useMemo(
    () => (catalog.data ?? []).filter((product) => product.maximum_order_quantity !== null && product.maximum_order_quantity !== undefined),
    [catalog.data],
  );
  const product = limitedProducts.find((item) => item.id === productId);
  const currentMaximum = product?.maximum_order_quantity == null ? null : Number(product.maximum_order_quantity);
  const requestedValue = Number(requestedMaximum);
  const quantityError = requestedMaximum !== '' && (!Number.isSafeInteger(requestedValue) || currentMaximum === null
    || requestedValue <= currentMaximum || requestedValue > MAX_REQUESTED_LIMIT)
    ? currentMaximum === null
      ? 'Choose a product with a maximum order quantity configured.'
      : requestedValue <= currentMaximum
        ? `Enter more than the current maximum of ${formatQuantity(String(currentMaximum))}.`
        : `Enter a whole number no greater than ${MAX_REQUESTED_LIMIT.toLocaleString('en-PH')}.`
    : undefined;
  const mutation = useMutation({
    mutationFn: () => customerPortalApi.createOrderLimitIncreaseRequest({
      product_id: product!.id,
      requested_maximum_quantity: requestedValue,
      customer_notes: notes.trim() || undefined,
    }),
    onSuccess: () => {
      toast.success('Order limit increase request submitted to Sales.');
      void queryClient.invalidateQueries({ queryKey: ['portal', 'customer', 'order-limit-requests'] });
      navigate('/portal/customer/order-limit-requests');
    },
    onError: (error: Error & { response?: { data?: { message?: string; errors?: Record<string, string[]> } } }) => {
      const message = Object.values(error.response?.data?.errors ?? {}).flat().join(' ')
        || error.response?.data?.message || 'Unable to submit the request. Your entries have been kept.';
      setSubmitError(message);
    },
  });
  const canSubmit = !!product && currentMaximum !== null && !quantityError
    && Number.isSafeInteger(requestedValue) && requestedValue > currentMaximum
    && requestedValue <= MAX_REQUESTED_LIMIT && notes.length <= 1000 && !mutation.isPending;

  if (catalog.isLoading) return <div className="p-5 text-sm text-muted">Loading your product limits…</div>;
  if (catalog.isError) return <div className="p-5"><EmptyState icon="alert-circle" title="Unable to load product limits" description="Retry to check the maximum quantities set for your account." action={<Button onClick={() => void catalog.refetch()}>Retry</Button>} /></div>;

  return <div>
    <PageHeader title="Request a higher order limit" subtitle="Ask Sales to review the maximum quantity allowed on each order." backTo="/portal/customer/order-limit-requests" backLabel="Order limit requests" />
    {limitedProducts.length === 0 ? <div className="p-5"><EmptyState icon="package" title="No order limit to increase" description="Your available products do not have a maximum per-order quantity configured. Contact Sales if you need help." /></div> : (
      <form onSubmit={(event) => { event.preventDefault(); if (canSubmit) { setSubmitError(''); mutation.mutate(); } }} className="max-w-3xl space-y-5 px-4 py-5 sm:px-5 lg:py-6">
        <section className="grid gap-4 rounded-lg border border-default bg-canvas p-4 sm:grid-cols-2 sm:p-5">
          <Select label="Product" required value={productId} onChange={(event) => { setProductId(event.target.value); setSubmitError(''); }}>
            <option value="">Select product</option>
            {limitedProducts.map((item) => <option key={item.id} value={item.id}>{item.part_number} · {item.name}</option>)}
          </Select>
          <div className="flex min-h-11 flex-col justify-center text-sm" aria-live="polite">
            {product && currentMaximum !== null
              ? <><span className="text-muted">Current maximum per order</span><strong className="mt-1">{formatQuantity(String(currentMaximum))} {product.unit_of_measure || 'pieces'}</strong></>
              : <span className="text-muted">Select a product to see your current limit.</span>}
          </div>
          <Input label="Requested maximum per order" type="number" min={currentMaximum === null ? 1 : Math.floor(currentMaximum) + 1} max={MAX_REQUESTED_LIMIT} step={1} required
            value={requestedMaximum} onChange={(event) => { setRequestedMaximum(event.target.value); setSubmitError(''); }}
            error={quantityError} helper={product ? `Enter a new per-order cap above ${formatQuantity(String(currentMaximum))} ${product.unit_of_measure || 'pieces'}.` : 'Choose a product first.'} />
          <Textarea label="Reason for request" rows={3} maxLength={1000} value={notes}
            onChange={(event) => setNotes(event.target.value)} placeholder="Optional context to help Sales review your request…" />
        </section>
        <div className="rounded-lg border border-info bg-info-bg p-4 text-sm text-info-fg">
          Submitting this request does not change your current order limit. Sales must approve it before you can place an order above the current maximum.
        </div>
        {submitError && <p role="alert" className="text-sm text-danger-fg">{submitError}</p>}
        <div className="flex justify-end gap-3">
          <Button type="button" variant="secondary" onClick={() => navigate('/portal/customer/order-limit-requests')}>Cancel</Button>
          <Button type="submit" variant="primary" disabled={!canSubmit} loading={mutation.isPending}>
            {mutation.isPending ? 'Submitting request…' : 'Submit limit increase request'}
          </Button>
        </div>
      </form>
    )}
  </div>;
}
