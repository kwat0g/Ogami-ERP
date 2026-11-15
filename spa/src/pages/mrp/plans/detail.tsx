import { useParams, Link } from 'react-router-dom';
import { useMemo, useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { LuRefreshCw } from '@/lib/icons';
import toast from 'react-hot-toast';
import { FulfillmentDialog } from '@/pages/crm/sales-orders/FulfillmentDialog';
import { mrpPlansApi } from '@/api/mrp/mrpPlans';
import { Button } from '@/components/ui/Button';
import { Chip, chipVariantForStatus } from '@/components/ui/Chip';
import { EmptyState } from '@/components/ui/EmptyState';
import { Panel } from '@/components/ui/Panel';
import { SkeletonDetail } from '@/components/ui/Skeleton';
import { PageHeader } from '@/components/layout/PageHeader';
import { usePermission } from '@/hooks/usePermission';
import { productionDateTime } from '@/components/production/scheduleDates';
import { formatPeso, formatQuantity } from '@/lib/formatNumber';
import { reportMutationError } from '@/lib/formErrors';
import { useRealtimeRecord } from '@/hooks/useRealtimeRecord';
import { Td, Th, tableCls, theadTrCls, trCls } from '@/components/ui/table-cells';
import type {
  MrpMaterialDiagnostic,
  MrpPlanDiagnostic,
  MrpStockProductionDiagnostic,
  MrpPlanWarningDiagnostic,
} from '@/types/mrp';

const isWarningDiagnostic = (row: MrpPlanDiagnostic): row is MrpPlanWarningDiagnostic =>
  'kind' in row && row.kind === 'warning';
const isMaterialDiagnostic = (row: MrpPlanDiagnostic): row is MrpMaterialDiagnostic => !('kind' in row);
const isStockMaterialRequirement = (row: MrpPlanDiagnostic): row is MrpStockProductionDiagnostic =>
  'kind' in row && row.kind === 'stock_material_requirement';

export default function MrpPlanDetailPage() {
  const { id } = useParams<{ id: string }>();
  const qc = useQueryClient();
  const { can } = usePermission();
  const [reviewFulfillment, setReviewFulfillment] = useState(false);
  useRealtimeRecord('mrp_plan', id);

  const { data, isLoading, isError, refetch } = useQuery({
    queryKey: ['mrp', 'plans', 'detail', id],
    queryFn: () => mrpPlansApi.show(id!),
    enabled: !!id,
  });

  const rerun = useMutation({
    mutationFn: () => mrpPlansApi.rerun(id!),
    onSuccess: (plan) => {
      qc.invalidateQueries({ queryKey: ['mrp', 'plans'] });
      qc.setQueryData(['mrp', 'plans', 'detail', id], plan);
      toast.success(
        plan.rerun_outcome === 'unchanged'
          ? 'MRP is already up to date. No plan records changed.'
          : `MRP updated — new version v${plan.version}.`,
      );
    },
    onError: (error) =>
      reportMutationError(
        error,
        'Could not re-run MRP. Review the run history for recovery details.',
      ),
  });

  const summary = useMemo(() => {
    if (!data || (data.source_type && data.source_type !== 'sales_order')) return null;
    const d = data.diagnostics.filter(isMaterialDiagnostic);
    return {
      totalDemand: d.reduce((s, r) => s + r.gross, 0),
      shortageCount: d.filter((r) => r.net > 0).length,
      legacySafetyStockDiagnostics: d.some((r) => r.safety_stock_gap === undefined),
      autoPrCount: data.auto_pr_count ?? 0,
      draftAutoPrCount:
        data.purchase_requests?.filter(
          (pr) => pr.is_auto_generated && pr.status === 'draft',
        ).length ?? 0,
      plannedCost: data.cost_summary?.planned_production_cost ?? null,
    };
  }, [data]);

  if (isLoading)
    return (
      <div>
        <PageHeader title="MRP plan" backTo="/mrp/plans" backLabel="Plans" />
        <SkeletonDetail />
      </div>
    );
  if (isError || !data)
    return (
      <div>
        <PageHeader title="MRP plan" backTo="/mrp/plans" backLabel="Plans" />
        <EmptyState
          icon="alert-circle"
          title="Failed to load plan"
          action={
            <Button variant="secondary" onClick={() => refetch()}>
              Retry
            </Button>
          }
        />
      </div>
    );

  const stockMaterials = data.diagnostics.filter(isStockMaterialRequirement);
  const otherProductionDiagnostics = data.diagnostics.filter(
    (diagnostic) =>
      !isStockMaterialRequirement(diagnostic)
      && !('kind' in diagnostic && ['stock_production_plan', 'material_shortage'].includes(diagnostic.kind)),
  );

  return (
    <div>
      <PageHeader
        title={
          <div className="flex items-center gap-3">
            <span className="font-mono">{data.mrp_plan_no}</span>
            <Chip
              variant={
                data.status === 'active'
                  ? 'success'
                  : data.status === 'cancelled'
                    ? 'danger'
                    : 'neutral'
              }
            >
              v{data.version} · {data.status_label ?? data.status}
            </Chip>
          </div>
        }
        subtitle={data.sales_order ? `for Sales Order ${data.sales_order.so_number}`
          : data.customer_production_request ? `for production request ${data.customer_production_request.request_number}`
            : data.stock_production_work_order ? `for stock production ${data.stock_production_work_order.wo_number}` : undefined}
        backTo="/mrp/plans"
        backLabel="Plans"
        actions={<div className="flex gap-2">{data.sales_order && data.sales_order.status === 'confirmed' && (can('production.wo.confirm') || can('production.schedule.confirm')) && <Button variant="secondary" onClick={() => setReviewFulfillment(true)}>Review fulfillment</Button>}{
          can('mrp.plans.run') && (data.source_type !== 'customer_production_request' || data.customer_production_request?.status === 'planning') ? (
            <Button
              variant="primary"
              size="sm"
              icon={<LuRefreshCw size={14} />}
              onClick={() => rerun.mutate()}
              loading={rerun.isPending}
            >
              Re-run
            </Button>
          ) : null
        }</div>}
      />

      {reviewFulfillment && data.sales_order && <FulfillmentDialog orderId={data.sales_order.id} onClose={() => setReviewFulfillment(false)} />}
      <div className="space-y-4 px-5 py-4">
        {data.source_type && data.source_type !== 'sales_order' && <Panel title="Plan source">
          {data.customer_production_request ? <div className="space-y-2 text-sm">
            <p>This plan exists for customer request <Link className="font-mono text-accent underline" to={`/crm/customer-production-requests/${data.customer_production_request.id}`}>{data.customer_production_request.request_number}</Link>.</p>
            {data.customer_production_request.customer && <p>Customer: {data.customer_production_request.customer.name}</p>}
            <p>Exact make quantity: <span className="font-mono">{formatQuantity(data.customer_production_request.requested_quantity)}</span> · Need by {data.customer_production_request.requested_need_by_date ?? '—'}</p>
          </div> : data.stock_production_work_order ? <div className="space-y-2 text-sm">
            <p>Internal stock production is rooted in work order <Link className="font-mono text-accent underline" to={`/production/work-orders/${data.stock_production_work_order.id}`}>{data.stock_production_work_order.wo_number}</Link>.</p>
            <p>{data.stock_production_work_order.product?.part_number} · {data.stock_production_work_order.product?.name}</p>
            <p>Exact make quantity: <span className="font-mono">{formatQuantity(data.stock_production_work_order.quantity_target)}</span></p>
          </div> : <p className="text-sm text-muted">Source details are unavailable.</p>}
        </Panel>}
        {data.status === 'active' && summary?.legacySafetyStockDiagnostics && (
          <div
            role="status"
            className="rounded-md border border-warning bg-warning-bg px-3 py-2 text-sm text-warning-fg"
          >
            This plan predates separate safety-stock warnings. Re-run MRP to refresh its shortage
            and purchase recommendations.
            {summary.draftAutoPrCount > 0 &&
              ' Re-running can cancel draft auto-PRs if no production shortage remains.'}
          </div>
        )}
        {/* Summary cards */}
        {summary && (
          <div className="grid grid-cols-2 gap-3 sm:grid-cols-4">
            <div className="rounded-md border border-default bg-canvas p-3">
              <div className="text-2xs uppercase tracking-wider text-muted mb-1">Total demand</div>
              <div className="text-lg font-mono tabular-nums font-medium">
                {formatQuantity(summary.totalDemand)}
              </div>
              <div className="text-2xs text-muted mt-0.5">units gross</div>
            </div>
            <div
              className={`rounded-md border p-3 ${summary.shortageCount > 0 ? 'border-danger bg-danger-bg' : 'border-default bg-canvas'}`}
            >
              <div className="text-2xs uppercase tracking-wider text-muted mb-1">
                {summary.legacySafetyStockDiagnostics ? 'Shortages' : 'Production shortages'}
              </div>
              <div
                className={`text-lg font-mono tabular-nums font-medium ${summary.shortageCount > 0 ? 'text-danger-fg' : ''}`}
              >
                {summary.shortageCount}
              </div>
              <div className="text-2xs text-muted mt-0.5">
                {summary.legacySafetyStockDiagnostics
                  ? 'saved plan calculation'
                  : 'production demand uncovered'}
              </div>
            </div>
            <div
              className={`rounded-md border p-3 ${summary.autoPrCount > 0 ? 'border-info bg-info-bg' : 'border-default bg-canvas'}`}
            >
              <div className="text-2xs uppercase tracking-wider text-muted mb-1">Auto PRs</div>
              <div className="text-lg font-mono tabular-nums font-medium">
                {summary.autoPrCount}
              </div>
              <div className="text-2xs text-muted mt-0.5">generated</div>
            </div>
            <div
              className={`rounded-md border p-3 ${summary.plannedCost ? 'border-success bg-success-bg' : 'border-default bg-canvas'}`}
            >
              <div className="text-2xs uppercase tracking-wider text-muted mb-1">Planned cost</div>
              <div className="text-lg font-mono tabular-nums font-medium">
                {formatPeso(summary.plannedCost)}
              </div>
              <div className="text-2xs text-muted mt-0.5">remaining production</div>
            </div>
          </div>
        )}
        {data.source_type === 'sales_order' || !data.source_type ? <Panel title="Diagnostics" meta={`${data.diagnostics.length} materials`} noPadding>
          {data.diagnostics.length === 0 ? (
            <div className="p-4 text-sm text-muted">No materials evaluated (no active BOM).</div>
          ) : (
            <div className="overflow-x-auto">
              <table className={`${tableCls} min-w-max`}>
                <thead>
                  <tr className={theadTrCls}>
                    <Th className="whitespace-nowrap">Item</Th>
                    <Th align="right" className="whitespace-nowrap">
                      Gross
                    </Th>
                    <Th align="right" className="whitespace-nowrap">
                      On hand
                    </Th>
                    <Th align="right" className="whitespace-nowrap">
                      Reserved
                    </Th>
                    <Th align="right" className="whitespace-nowrap">
                      QC held
                    </Th>
                    <Th align="right" className="whitespace-nowrap">
                      Usable now
                    </Th>
                    <Th align="right" className="whitespace-nowrap">
                      In transit
                    </Th>
                    <Th align="right" className="whitespace-nowrap">
                      Unit cost
                    </Th>
                    <Th align="right" className="whitespace-nowrap">
                      Shortfall
                    </Th>
                    <Th align="right" className="whitespace-nowrap">
                      Shortfall cost
                    </Th>
                    <Th className="whitespace-nowrap">Action</Th>
                  </tr>
                </thead>
                <tbody>
                  {data.diagnostics.map((d, index) =>
                    isWarningDiagnostic(d) ? (
                      <tr key={`warning-${d.sales_order_line_id}-${index}`} className={trCls}>
                        <Td colSpan={11}>
                          <div className="text-warning-fg">{d.message}</div>
                        </Td>
                      </tr>
                    ) : isMaterialDiagnostic(d) ? (
                      <tr key={d.item_id} className={trCls}>
                        <Td mono className="whitespace-nowrap">
                          {d.item_code}
                        </Td>
                        <Td align="right" mono className="whitespace-nowrap">
                          {formatQuantity(d.gross)}
                        </Td>
                        <Td align="right" mono className="whitespace-nowrap">
                          {formatQuantity(d.on_hand)}
                        </Td>
                        <Td align="right" mono className="whitespace-nowrap">
                          {formatQuantity(d.reserved)}
                        </Td>
                        <Td align="right" mono className="whitespace-nowrap">
                          {formatQuantity(d.quality_held ?? 0)}
                        </Td>
                        <Td align="right" mono className="whitespace-nowrap">
                          {d.usable == null ? 'Not recorded' : formatQuantity(d.usable)}
                        </Td>
                        <Td align="right" mono className="whitespace-nowrap">
                          {formatQuantity(d.in_transit)}
                        </Td>
                        <Td align="right" mono className="whitespace-nowrap">
                          {formatPeso(d.standard_unit_cost)}
                        </Td>
                        <Td align="right" mono className="whitespace-nowrap font-medium">
                          {formatQuantity(d.net)}
                        </Td>
                        <Td align="right" mono className="whitespace-nowrap">
                          {formatPeso(d.net_cost)}
                        </Td>
                        <Td>
                          <div className="flex flex-wrap items-center gap-1">
                            {(d.safety_stock_gap ?? 0) > 0 && (
                              <Chip variant="warning">
                                Safety stock gap ·{' '}
                                <span className="font-mono tabular-nums">
                                  {formatQuantity(d.safety_stock_gap)}
                                </span>
                              </Chip>
                            )}
                            {d.action === 'pr_created' ? (
                              <Chip variant={d.priority === 'urgent' ? 'danger' : 'info'}>
                                PR · {d.priority}
                              </Chip>
                            ) : d.action === 'awaiting_qc' ? (
                              <Chip variant="warning">
                                QC hold ·{' '}
                                <span className="font-mono tabular-nums">
                                  {formatQuantity(d.awaiting_qc)}
                                </span>
                              </Chip>
                            ) : d.action === 'awaiting_po_approval' ? (
                              <Chip variant="info">
                                PO pending ·{' '}
                                <span className="font-mono tabular-nums">
                                  {formatQuantity(d.pending_purchase_orders)}
                                </span>
                              </Chip>
                            ) : d.action === 'safety_stock_warning' ? null : (
                              <Chip variant="success">sufficient</Chip>
                            )}
                          </div>
                        </Td>
                      </tr>
                    ) : <tr key={`stock-${index}`} className={trCls}><Td colSpan={11}><div className="text-muted">{d.type.replaceAll('_', ' ')}</div></Td></tr>,
                  )}
                </tbody>
              </table>
            </div>
          )}
        </Panel> : <>
          <Panel title="Material requirements" meta={`${stockMaterials.length} materials`} noPadding>
            {stockMaterials.length === 0 ? (
              <div className="p-4 text-sm text-muted">No material snapshots were saved for this plan.</div>
            ) : (
              <div className="overflow-x-auto">
                <table className={`${tableCls} min-w-max`}>
                  <thead>
                    <tr className={theadTrCls}>
                      <Th>Material</Th>
                      <Th align="right" className="whitespace-nowrap">Required</Th>
                      <Th align="right" className="whitespace-nowrap">Available to plan</Th>
                      <Th align="right" className="whitespace-nowrap">Shortage</Th>
                      <Th align="right" className="whitespace-nowrap">Planned purchase</Th>
                      <Th align="right" className="whitespace-nowrap">Unit cost</Th>
                      <Th align="right" className="whitespace-nowrap">Required cost</Th>
                      <Th>Plan status</Th>
                    </tr>
                  </thead>
                  <tbody>
                    {stockMaterials.map((material, index) => (
                      <tr key={`${material.item_id ?? material.item_code}-${index}`} className={trCls}>
                        <Td>
                          <div className="font-mono text-xs">{material.item_code ?? 'Material'}</div>
                          <div className="text-muted">{material.item_name}</div>
                          {material.work_order_numbers?.length ? (
                            <div className="mt-1 text-xs text-muted">
                              Work order{material.work_order_numbers.length > 1 ? 's' : ''}: {material.work_order_numbers.join(', ')}
                            </div>
                          ) : null}
                        </Td>
                        <Td align="right" mono className="whitespace-nowrap">
                          {formatQuantity(material.required_quantity)} {material.unit}
                        </Td>
                        <Td align="right" mono className="whitespace-nowrap">
                          {material.available_quantity == null ? 'Not recorded' : `${formatQuantity(material.available_quantity)} ${material.unit}`}
                        </Td>
                        <Td align="right" mono className="whitespace-nowrap">
                          {material.shortage_quantity == null ? 'Not recorded' : `${formatQuantity(material.shortage_quantity)} ${material.unit}`}
                        </Td>
                        <Td align="right" mono className="whitespace-nowrap">
                          {material.purchase_quantity == null ? 'Not recorded' : `${formatQuantity(material.purchase_quantity)} ${material.unit}`}
                        </Td>
                        <Td align="right" mono className="whitespace-nowrap">
                          {material.standard_unit_cost == null ? 'Not recorded' : formatPeso(material.standard_unit_cost)}
                        </Td>
                        <Td align="right" mono className="whitespace-nowrap">
                          {material.required_cost == null ? 'Not recorded' : formatPeso(material.required_cost)}
                        </Td>
                        <Td>
                          {material.action === 'purchase_required' ? (
                            <Chip variant="warning">Purchase required</Chip>
                          ) : material.action === 'covered' ? (
                            <Chip variant="success">Covered</Chip>
                          ) : (
                            <Chip variant="neutral">Availability not recorded</Chip>
                          )}
                        </Td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            )}
          </Panel>
          {otherProductionDiagnostics.length > 0 && (
            <Panel title="Production diagnostics" meta={`${otherProductionDiagnostics.length} records`}>
              <div className="space-y-2 text-sm">
                {otherProductionDiagnostics.map((diagnostic, index) => (
                  <div key={`production-diagnostic-${index}`} className="rounded-md border border-default p-3 text-muted">
                    {'message' in diagnostic ? diagnostic.message : 'type' in diagnostic ? diagnostic.type.replaceAll('_', ' ') : 'Planning diagnostic'}
                  </div>
                ))}
              </div>
            </Panel>
          )}
        </>}

        <div className="grid gap-4 xl:grid-cols-2">
          {(data.cost_summary || data.source_type === 'stock_production_work_order' || data.source_type === 'customer_production_request') && (
            <Panel title="Cost estimate">
              <div className="grid grid-cols-2 gap-3 text-sm sm:grid-cols-4">
                <div>
                  <div className="text-2xs uppercase text-muted">Material</div>
                  <div className="mt-1 font-mono tabular-nums">
                    {data.cost_summary?.material_cost == null ? 'Not recorded' : formatPeso(data.cost_summary.material_cost)}
                  </div>
                </div>
                <div>
                  <div className="text-2xs uppercase text-muted">Labor</div>
                  <div className="mt-1 font-mono tabular-nums">
                    {data.cost_summary?.labor_cost == null ? 'Not estimated' : formatPeso(data.cost_summary.labor_cost)}
                  </div>
                </div>
                <div>
                  <div className="text-2xs uppercase text-muted">Machine</div>
                  <div className="mt-1 font-mono tabular-nums">
                    {data.cost_summary?.machine_cost == null ? 'Not estimated' : formatPeso(data.cost_summary.machine_cost)}
                  </div>
                </div>
                <div>
                  <div className="text-2xs uppercase text-muted">Overhead</div>
                  <div className="mt-1 font-mono tabular-nums">
                    {data.cost_summary?.overhead_cost == null ? 'Not estimated' : formatPeso(data.cost_summary.overhead_cost)}
                  </div>
                </div>
              </div>
            </Panel>
          )}
          <Panel title="Linked records">
            <div className="space-y-3 text-sm">
              <div>
                <div className="text-2xs uppercase tracking-wider text-muted mb-1">
                  Work orders ({data.work_orders?.length ?? 0})
                </div>
                {data.work_orders?.length ? (
                  data.work_orders.map((w) => (
                    <Link
                      key={w.id}
                      to={`/production/work-orders/${w.id}`}
                      className={`block font-mono text-xs text-accent hover:underline ${w.parent ? 'pl-4' : ''}`}
                    >
                      {w.parent ? '↳ ' : ''}
                      {w.wo_number}{' '}
                      <Chip variant={chipVariantForStatus(w.status)} className="font-sans">
                        {w.status_label ?? w.status}
                      </Chip>
                      <span className="text-muted">qty {formatQuantity(w.quantity_target)}</span>
                      <span className="mt-1 block font-sans text-muted">
                        {w.schedule
                          ? `Production: ${productionDateTime(w.schedule.scheduled_start)} – ${productionDateTime(w.schedule.scheduled_end)}`
                          : 'Choose production day when confirming'}
                        {w.planned_end && ` · Due ${productionDateTime(w.planned_end)}`}
                      </span>
                    </Link>
                  ))
                ) : (
                  <span className="text-muted">—</span>
                )}
              </div>
              <div>
                <div className="text-2xs uppercase tracking-wider text-muted mb-1">
                  Purchase requests ({data.purchase_requests?.length ?? 0})
                </div>
                {data.purchase_requests?.length ? (
                  data.purchase_requests.map((p) => (
                    <div key={p.id}>
                      <Link
                        to={`/purchasing/purchase-requests/${p.id}`}
                        className="block font-mono text-xs text-accent hover:underline"
                      >
                        {p.pr_number}{' '}
                        <Chip variant={chipVariantForStatus(p.status)} className="font-sans">
                          {p.status_label ?? p.status}
                        </Chip>
                        <span className="text-muted">{p.priority_label ?? p.priority}</span>
                      </Link>
                      {p.purchase_orders?.map((po) => (
                        <Link
                          key={po.id}
                          to={`/purchasing/purchase-orders/${po.id}`}
                          className="block pl-4 font-mono text-xs text-accent hover:underline"
                        >
                          ↳ {po.po_number}{' '}
                          <Chip variant={chipVariantForStatus(po.status)} className="font-sans">
                            {po.status_label ?? po.status}
                          </Chip>
                        </Link>
                      ))}
                    </div>
                  ))
                ) : (
                  <span className="text-muted">—</span>
                )}
              </div>
            </div>
          </Panel>
        </div>
      </div>
    </div>
  );
}
