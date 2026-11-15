import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { render, screen } from '@testing-library/react';
import { MemoryRouter, Route, Routes } from 'react-router-dom';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { mrpPlansApi } from '@/api/mrp/mrpPlans';
import MrpPlanDetailPage from './detail';

vi.mock('@/api/mrp/mrpPlans', () => ({
  mrpPlansApi: { show: vi.fn(), rerun: vi.fn() },
}));
vi.mock('@/hooks/usePermission', () => ({ usePermission: () => ({ can: () => false }) }));
vi.mock('@/hooks/useRealtimeRecord', () => ({ useRealtimeRecord: vi.fn() }));

const plan = {
  id: 'plan-1',
  mrp_plan_no: 'MRP-202611-0001',
  source_type: 'stock_production_work_order',
  stock_production_work_order: {
    id: 'work-order-1',
    wo_number: 'WO-202611-0022',
    quantity_target: 2500,
    status: 'planned',
    product: { id: 'product-1', part_number: 'TC-001', name: 'Generator starter' },
  },
  version: 1,
  status: 'active',
  status_label: 'Active',
  total_lines: 1,
  shortages_found: 1,
  auto_pr_count: 1,
  draft_wo_count: 1,
  diagnostics: [
    {
      kind: 'stock_production_plan',
      type: 'exact_make_quantity',
      quantity_target: 2500,
      material_line_count: 1,
      subassembly_count: 0,
    },
    {
      kind: 'stock_material_requirement',
      type: 'stock_material_requirement',
      item_id: 'material-1',
      item_code: 'RM-008',
      item_name: 'PA66 A3W Natural resin',
      unit: 'kg',
      required_quantity: '16012.500',
      available_quantity: '0.000',
      shortage_quantity: '16012.500',
      purchase_quantity: '16012.500',
      standard_unit_cost: '240.0000',
      required_cost: '3843000.00',
      shortage_cost: '3843000.00',
      action: 'purchase_required',
    },
  ],
  cost_summary: {
    material_cost: '3843000.00',
    labor_cost: '0.00',
    machine_cost: '0.00',
    overhead_cost: '0.00',
    planned_production_cost: '3843000.00',
  },
  generation: { source: 'stock_production_work_order' },
  work_orders: [
    {
      id: 'work-order-1',
      wo_number: 'WO-202611-0022',
      product_id: 'product-1',
      quantity_target: 2500,
      status: 'planned',
      status_label: 'Planned',
      planned_start: '2026-11-15T10:00:00Z',
      planned_end: '2026-11-30T23:59:00Z',
      schedule: null,
      parent: null,
    },
  ],
  purchase_requests: [],
} as never;

function renderPage() {
  return render(
    <QueryClientProvider client={new QueryClient({ defaultOptions: { queries: { retry: false } } })}>
      <MemoryRouter initialEntries={['/mr/plans/plan-1']}>
        <Routes>
          <Route path="/mr/plans/:id" element={<MrpPlanDetailPage />} />
        </Routes>
      </MemoryRouter>
    </QueryClientProvider>,
  );
}

describe('stock MRP plan details', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    vi.mocked(mrpPlansApi.show).mockResolvedValue(plan);
  });

  it('shows required materials, supply, purchase quantities, and cost estimates', async () => {
    renderPage();

    expect(await screen.findByText('RM-008')).toBeVisible();
    expect(screen.getByText('Material requirements')).toBeVisible();
    expect(screen.getByText('Available to plan')).toBeVisible();
    expect(screen.getAllByText('16,012.5 kg')).toHaveLength(3);
    expect(screen.getByText('Purchase required')).toBeVisible();
    expect(screen.getByText('Cost estimate')).toBeVisible();
    expect(screen.getAllByText(/3,843,000\.00/)).toHaveLength(2);
  });
});
