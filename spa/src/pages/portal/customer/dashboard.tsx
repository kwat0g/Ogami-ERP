import { useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import { Link } from 'react-router-dom';
import { LuArrowRight, LuChevronLeft, LuChevronRight, LuSearch } from '@/lib/icons';
import { customerPortalApi } from '@/api/b2b/customer';
import { Button } from '@/components/ui/Button';
import { Chip } from '@/components/ui/Chip';
import { EmptyState } from '@/components/ui/EmptyState';
import { Input } from '@/components/ui/Input';
import { Panel } from '@/components/ui/Panel';
import { StatCard } from '@/components/ui/StatCard';
import {
  DashboardShell,
  KpiGrid,
  PanelRow,
} from '@/components/dashboard/DashboardShell';
import { formatDate } from '@/lib/formatDate';
import { formatPeso } from '@/lib/formatNumber';
import { complaintStatusVariant, deliveryStatusVariant, invoiceStatusVariant, salesOrderStatusVariant } from '@/lib/statusVariants';
import { Td, Th, tableCls, theadTrCls, trCls } from '@/components/ui/table-cells';

const PRODUCT_TERMS_PAGE_SIZE = 4;

const coveredIssueLabels: Record<string, string> = {
  manufacturing_defect: 'Manufacturing defect',
  shipping_damage: 'Shipping damage',
  customer_caused_damage: 'Customer-caused damage',
};

const remedyLabels: Record<string, string> = {
  repair: 'Repair',
  replace: 'Replace',
  credit: 'Credit',
  refund: 'Refund',
};

function agreementQuantityRange(minimum: string, maximum: string | null, unit: string | null): string {
  const formatter = new Intl.NumberFormat('en-PH', { maximumFractionDigits: 3 });
  const min = formatter.format(Number(minimum));
  const unitLabel = unit ? ` ${unit}` : '';
  if (maximum === null) return `At least ${min}${unitLabel} (no maximum)`;
  return `${min}–${formatter.format(Number(maximum))}${unitLabel}`;
}

function warrantyList(values: string[] | null, labels: Record<string, string>): string {
  return values?.length ? values.map((value) => labels[value] ?? value.replaceAll('_', ' ')).join(', ') : 'Not specified';
}

export default function CustomerDashboardPage() {
  const [productSearch, setProductSearch] = useState('');
  const [productPage, setProductPage] = useState(1);
  const dashboard = useQuery({
    queryKey: ['portal', 'customer', 'dashboard'],
    queryFn: () => customerPortalApi.dashboard(),
  });
  const account = useQuery({
    queryKey: ['portal', 'customer', 'me'],
    queryFn: () => customerPortalApi.me(),
  });
  const terms = account.data?.customer_terms;
  const productTerms = (dashboard.data?.product_terms ?? []).filter((product) => {
    const query = productSearch.trim().toLowerCase();
    return !query || `${product.part_number} ${product.name}`.toLowerCase().includes(query);
  });
  const productPageCount = Math.max(1, Math.ceil(productTerms.length / PRODUCT_TERMS_PAGE_SIZE));
  const currentProductPage = Math.min(productPage, productPageCount);
  const visibleProductTerms = productTerms.slice(
    (currentProductPage - 1) * PRODUCT_TERMS_PAGE_SIZE,
    currentProductPage * PRODUCT_TERMS_PAGE_SIZE,
  );
  const productRangeStart = productTerms.length ? (currentProductPage - 1) * PRODUCT_TERMS_PAGE_SIZE + 1 : 0;
  const productRangeEnd = Math.min(currentProductPage * PRODUCT_TERMS_PAGE_SIZE, productTerms.length);

  const viewAll = (to: string) => (
    <Link to={to} className="text-2xs text-accent hover:underline flex items-center gap-1">
      View all <LuArrowRight size={11} />
    </Link>
  );

  return (
    <DashboardShell
      title="Dashboard"
      subtitle="Orders, deliveries, and account balance at a glance"
      query={dashboard}
      kpiCount={4}
    >
      {(data) => (
        <>
          <div data-layout="section">
            <KpiGrid count={4}>
              <StatCard
                label="Open Orders"
                value={data.open_so_count}
                helper="Pending fulfillment"
                linkTo="/portal/customer/orders"
              />
              <StatCard
                label="Pending Deliveries"
                value={data.pending_delivery_count}
                helper="Awaited deliveries"
                linkTo="/portal/customer/deliveries"
              />
              <StatCard
                label="Open Invoices"
                value={data.open_invoice_count}
                helper="Invoices due"
                linkTo="/portal/customer/invoices?signal=unpaid"
              />
              <StatCard
                label="Outstanding"
                value={formatPeso(data.total_outstanding)}
                helper="Total balance"
                linkTo="/portal/customer/statement-of-account"
              />
            </KpiGrid>
          </div>

          <Panel title="Terms for your account">
            {account.isLoading ? <p className="text-sm text-muted">Loading your terms…</p>
              : account.isError ? <p role="alert" className="text-sm text-danger-fg">Account terms could not be loaded.</p>
                : terms ? <div className="space-y-4">
                  <dl className="grid gap-x-6 gap-y-3 text-sm sm:grid-cols-2 xl:grid-cols-3">
                    <div><dt className="text-xs text-muted">Agreement status</dt><dd className="mt-0.5 capitalize">{terms.agreement_status.replaceAll('_', ' ')}</dd></div>
                    <div><dt className="text-xs text-muted">Agreement period</dt><dd className="mt-0.5">{terms.agreement_starts_on || terms.agreement_ends_on ? `${formatDate(terms.agreement_starts_on)} – ${formatDate(terms.agreement_ends_on)}` : 'Not recorded'}</dd></div>
                    <div><dt className="text-xs text-muted">Account default payment terms</dt><dd className="mt-0.5">{terms.payment_terms_days} days</dd></div>
                    <div><dt className="text-xs text-muted">Allowed payment methods</dt><dd className="mt-0.5">{terms.allowed_payment_methods === null ? 'All supported methods' : terms.allowed_payment_methods.length ? terms.allowed_payment_methods.map((method) => ({ cash: 'Cash', check: 'Check', bank_transfer: 'Bank transfer' })[method]).join(', ') : 'None configured'}</dd></div>
                    <div><dt className="text-xs text-muted">Shipping paid by</dt><dd className="mt-0.5">{terms.shipping_payer ? terms.shipping_payer === 'company' ? 'Company' : 'Customer' : 'Not specified'}</dd></div>
                  </dl>
                  <section aria-labelledby="customer-product-terms-title" className="space-y-3 border-t border-subtle pt-4">
                    <div className="flex flex-wrap items-baseline justify-between gap-x-3 gap-y-1">
                      <h3 id="customer-product-terms-title" className="text-sm font-semibold text-primary">Product terms</h3>
                      <span className="text-xs text-muted">{data.product_terms?.length ?? 0} products in your agreement</span>
                    </div>
                    {data.product_terms?.length ? (
                      <div className="space-y-3">
                        {data.product_terms.length > PRODUCT_TERMS_PAGE_SIZE && (
                          <Input
                            aria-label="Search product terms"
                            placeholder="Search part number or name"
                            fieldSize="md"
                            prefix={<LuSearch size={16} aria-hidden="true" />}
                            value={productSearch}
                            onChange={(event) => {
                              setProductSearch(event.target.value);
                              setProductPage(1);
                            }}
                          />
                        )}
                        {visibleProductTerms.length ? (
                          <ul className="space-y-2">
                            {visibleProductTerms.map((product) => (
                              <li key={product.id}>
                                <details name="customer-product-term" className="rounded-md border border-subtle bg-elevated/20">
                                  <summary className="cursor-pointer p-3 marker:text-muted">
                                    <span className="text-sm font-medium text-primary">{product.part_number} · {product.name}</span>
                                    <span className="mt-1 block text-xs text-muted">
                                      Quantity per order: {agreementQuantityRange(product.minimum_order_quantity, product.maximum_order_quantity, product.unit_of_measure)}
                                    </span>
                                  </summary>
                                  <div className="mx-3 border-t border-subtle pb-3 pt-3">
                                    <p className="text-xs font-medium text-primary">Warranty choices</p>
                                    {product.warranty_options.length ? (
                                      <ul className="mt-2 space-y-2">
                                        {product.warranty_options.map((option) => (
                                          <li key={option.id}>
                                            <details name={`customer-warranty-${product.id}`} className="rounded-sm bg-canvas/70 px-3 py-2">
                                              <summary className="flex cursor-pointer flex-wrap items-baseline justify-between gap-x-3 gap-y-1 marker:text-muted">
                                                <span className="text-sm font-medium text-primary">{option.label}</span>
                                                <span className="text-xs text-muted">{option.warranty_duration_days == null ? 'Duration not specified' : `${option.warranty_duration_days} days`}</span>
                                              </summary>
                                              <dl className="mt-2 grid gap-x-4 gap-y-2 text-xs sm:grid-cols-2 xl:grid-cols-4">
                                                <div><dt className="text-muted">Report deadline (from delivery)</dt><dd className="mt-0.5">{option.warranty_report_window_days == null ? 'Not specified' : `${option.warranty_report_window_days} days`}</dd></div>
                                                <div><dt className="text-muted">Warranty starts from</dt><dd className="mt-0.5">{option.warranty_start_basis ? option.warranty_start_basis[0].toUpperCase() + option.warranty_start_basis.slice(1) : 'Not specified'}</dd></div>
                                                <div><dt className="text-muted">Covered issues</dt><dd className="mt-0.5">{warrantyList(option.warranty_covered_issues, coveredIssueLabels)}</dd></div>
                                                <div><dt className="text-muted">Remedies</dt><dd className="mt-0.5">{warrantyList(option.warranty_remedies, remedyLabels)}</dd></div>
                                              </dl>
                                              {option.additional_terms && <p className="mt-2 whitespace-pre-line text-xs text-muted">{option.additional_terms}</p>}
                                            </details>
                                          </li>
                                        ))}
                                      </ul>
                                    ) : <p className="mt-1 text-xs text-muted">No product warranty choices are recorded.</p>}
                                  </div>
                                </details>
                              </li>
                            ))}
                          </ul>
                        ) : (
                          <p role="status" className="rounded-md border border-dashed border-subtle px-3 py-4 text-sm text-muted">No product terms match “{productSearch}”.</p>
                        )}
                        {productTerms.length > PRODUCT_TERMS_PAGE_SIZE && (
                          <nav aria-label="Product terms pages" className="flex flex-wrap items-center justify-between gap-2 pt-1">
                            <span className="text-xs tabular-nums text-muted">
                              Showing {productRangeStart}–{productRangeEnd} of {productTerms.length}{productSearch.trim() ? ' matching products' : ' products'}
                            </span>
                            <div className="flex items-center gap-1">
                              <Button
                                type="button"
                                variant="ghost"
                                size="sm"
                                className="min-h-11"
                                icon={<LuChevronLeft size={14} />}
                                disabled={currentProductPage <= 1}
                                onClick={() => setProductPage((page) => Math.max(1, page - 1))}
                              >
                                Previous
                              </Button>
                              <span aria-live="polite" className="px-2 text-xs tabular-nums text-muted">
                                {currentProductPage} / {productPageCount}
                              </span>
                              <Button
                                type="button"
                                variant="ghost"
                                size="sm"
                                className="min-h-11"
                                icon={<LuChevronRight size={14} />}
                                disabled={currentProductPage >= productPageCount}
                                onClick={() => setProductPage((page) => Math.min(productPageCount, page + 1))}
                              >
                                Next
                              </Button>
                            </div>
                          </nav>
                        )}
                      </div>
                    ) : <p className="rounded-md border border-dashed border-subtle px-3 py-4 text-sm text-muted">No products are currently listed in your agreement. Contact Sales if you expect to place an order.</p>}
                  </section>
                  <p className="border-t border-subtle pt-3 text-xs text-muted">The account payment term is a default; you may request different terms on each order for Sales to review. Order totals exclude freight. Sales confirms any customer-paid freight separately. Warranty reports are reviewed under the product terms above.</p>
                </div>
                  : <p className="text-sm text-muted">No account terms are recorded. Contact Sales for details.</p>}
          </Panel>

          <PanelRow>
            <Panel title="Recent Orders" actions={viewAll('/portal/customer/orders')} noPadding>
              {data.recent_orders.length > 0 ? (
                <div className="overflow-x-auto">
                  <table className={tableCls}>
                    <thead>
                      <tr className={theadTrCls}>
                        <Th>Order #</Th>
                        <Th>Date</Th>
                        <Th align="right">Amount</Th>
                        <Th align="right">Status</Th>
                      </tr>
                    </thead>
                    <tbody>
                      {data.recent_orders.map((order) => (
                        <tr key={order.id} className={trCls}>
                          <Td>
                            <Link
                              to={`/portal/customer/orders/${order.id}`}
                              className="font-mono font-medium text-accent hover:underline"
                            >
                              {order.so_number}
                            </Link>
                          </Td>
                          <Td className="text-muted">{order.date ? formatDate(order.date) : '—'}</Td>
                          <Td align="right" mono>{formatPeso(order.total_amount)}</Td>
                          <Td align="right" mono>
                            <Chip variant={salesOrderStatusVariant[order.status]}>
                              {order.status_label ?? order.status.replace(/_/g, ' ')}
                            </Chip>
                          </Td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
              ) : (
                <EmptyState size="compact" icon="package" title="No orders yet" />
              )}
            </Panel>

            <Panel title="Recent Invoices" actions={viewAll('/portal/customer/invoices')} noPadding>
              {data.recent_invoices.length > 0 ? (
                <div className="overflow-x-auto">
                  <table className={tableCls}>
                    <thead>
                      <tr className={theadTrCls}>
                        <Th>Invoice #</Th>
                        <Th>Date</Th>
                        <Th align="right">Amount</Th>
                        <Th align="right">Status</Th>
                      </tr>
                    </thead>
                    <tbody>
                      {data.recent_invoices.map((inv) => (
                        <tr key={inv.id} className={trCls}>
                          <Td>
                            <Link
                              to={`/portal/customer/invoices/${inv.id}`}
                              className="font-mono font-medium text-accent hover:underline"
                            >
                              {inv.invoice_number}
                            </Link>
                          </Td>
                          <Td className="text-muted">{inv.date ? formatDate(inv.date) : '—'}</Td>
                          <Td align="right" mono>{formatPeso(inv.total_amount)}</Td>
                          <Td align="right" mono>
                            <Chip variant={invoiceStatusVariant[inv.status] ?? 'neutral'}>
                              {inv.status_label ?? inv.status.replace(/_/g, ' ')}
                            </Chip>
                          </Td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
              ) : (
                <EmptyState size="compact" icon="receipt" title="No invoices yet" />
              )}
            </Panel>
          </PanelRow>

          <PanelRow>
            <Panel title="Recent Deliveries" actions={viewAll('/portal/customer/deliveries')} noPadding>
              {data.recent_deliveries.length > 0 ? (
                <div className="overflow-x-auto">
                  <table className={tableCls}>
                    <thead>
                      <tr className={theadTrCls}>
                        <Th>Delivery #</Th>
                        <Th>Date</Th>
                        <Th align="right">Status</Th>
                      </tr>
                    </thead>
                    <tbody>
                      {data.recent_deliveries.map((delivery) => (
                        <tr key={delivery.id} className={trCls}>
                          <Td>
                            <Link
                              to={`/portal/customer/deliveries/${delivery.id}`}
                              className="font-mono font-medium text-accent hover:underline"
                            >
                              {delivery.delivery_number}
                            </Link>
                          </Td>
                          <Td className="text-muted">
                            {formatDate(delivery.delivered_at ?? delivery.scheduled_date)}
                          </Td>
                          <Td align="right" mono>
                            <Chip variant={deliveryStatusVariant[delivery.status]}>
                              {delivery.status_label ?? delivery.status.replace(/_/g, ' ')}
                            </Chip>
                          </Td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
              ) : (
                <EmptyState size="compact" icon="truck" title="No deliveries yet" />
              )}
            </Panel>

            <Panel title="Recent Quality Complaints" actions={viewAll('/portal/customer/complaints')} noPadding>
              {data.recent_complaints.length > 0 ? (
                <div className="overflow-x-auto">
                  <table className={tableCls}>
                    <thead>
                      <tr className={theadTrCls}>
                        <Th>Complaint #</Th>
                        <Th>Description</Th>
                        <Th align="right">Status</Th>
                      </tr>
                    </thead>
                    <tbody>
                      {data.recent_complaints.map((complaint) => (
                        <tr key={complaint.id} className={trCls}>
                          <Td mono className="text-muted">{complaint.complaint_number}</Td>
                          <Td className="max-w-xs truncate">{complaint.description}</Td>
                          <Td align="right" mono>
                            <Chip variant={complaintStatusVariant[complaint.status]}>
                              {complaint.status_label ?? complaint.status.replace(/_/g, ' ')}
                            </Chip>
                          </Td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
              ) : (
                <EmptyState size="compact" icon="message-square" title="No complaints yet" />
              )}
            </Panel>
          </PanelRow>
        </>
      )}
    </DashboardShell>
  );
}
