import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { MemoryRouter } from 'react-router-dom';
import { fireEvent, render, screen, waitFor } from '@testing-library/react';
import { beforeEach, expect, it, vi } from 'vitest';
import { inspectionsApi } from '@/api/quality/inspections';
import { defectSummaryApi } from '@/api/quality/defectSummary';
import { WorkOrderFinalInspectionPanel } from './WorkOrderFinalInspectionPanel';
import type { WorkOrderFinalInspection } from '@/types/quality';

vi.mock('@/api/quality/inspections', () => ({
  inspectionsApi: {
    getWorkOrderFinalInspection: vi.fn(),
    saveWorkOrderFinalEvidence: vi.fn(),
    submitWorkOrderFinalInspection: vi.fn(),
    reviewWorkOrderFinalInspection: vi.fn(),
  },
}));
vi.mock('@/api/quality/defectSummary', () => ({ defectSummaryApi: { show: vi.fn(), save: vi.fn(), clear: vi.fn() } }));
const group = {
  work_order_id: 'wo',
  wo_number: 'WO-200',
  produced_quantity: 200,
  defective_quantity: 0,
  accepted_quantity: 0,
  state: 'checking',
  manifest: [
    {
      output_id: 'out',
      inspection_id: 'qc',
      physical_quantity: 200,
      result_version: 0,
      specification_revision_id: 'rev',
    },
  ],
  batches: [
    {
      id: 'qc',
      inspection_number: 'QC-200',
      status: 'draft',
      batch_quantity: 200,
      result_version: 0,
      sample_defect_count: 0,
      described_defective_quantity: 0,
      missing_readings: 0,
      counted_capture: { version: 2, required_quantity: 200, result_policy: 'zero_defects' },
      measurements: [],
    },
  ],
  evidence_page: { inspection_id: 'qc', page: 1, per_page: 50, total_pieces: 200, pieces: [] },
} as unknown as WorkOrderFinalInspection;
beforeEach(() => {
  vi.clearAllMocks();
  sessionStorage.clear();
  vi.mocked(inspectionsApi.getWorkOrderFinalInspection).mockResolvedValue(group);
  vi.mocked(inspectionsApi.saveWorkOrderFinalEvidence).mockResolvedValue(group);
  vi.mocked(inspectionsApi.submitWorkOrderFinalInspection).mockResolvedValue({
    ...group,
    state: 'awaiting_review',
  });
  vi.mocked(defectSummaryApi.show).mockResolvedValue(null);
  vi.mocked(defectSummaryApi.save).mockImplementation(async (_id, data) => ({
    id: 'summary', category: data.category, category_label: 'Surface damage', other_category: null,
    severity: data.severity, severity_label: 'Major', affected_quantity: data.affected_quantity,
    description: data.description, photos: [{ id: 'photo', file_name: 'proof.jpg', mime_type: 'image/jpeg', file_size: 5, file_url: '/proof.jpg', created_at: null }], created_at: null, updated_at: null,
  }));
});
function panel(data = group) {
  return render(
    <QueryClientProvider
      client={
        new QueryClient({
          defaultOptions: { queries: { retry: false }, mutations: { retry: false } },
        })
      }
    >
      <MemoryRouter>
        <WorkOrderFinalInspectionPanel group={data} canManage canReview />
      </MemoryRouter>
    </QueryClientProvider>,
  );
}
async function saveSummary(description: string) {
  const descriptionInput = await screen.findByLabelText(/Shared description/);
  await waitFor(() => expect(descriptionInput).toBeEnabled());
  fireEvent.change(descriptionInput, { target: { value: description } });
  const photos = screen.getByLabelText('Defect photos');
  await waitFor(() => expect(photos).toBeEnabled());
  fireEvent.change(photos, { target: { files: [new File(['proof'], 'proof.jpg', { type: 'image/jpeg' })] } });
  const save = screen.getByRole('button', { name: 'Save defect summary' });
  await waitFor(() => expect(save).toBeEnabled());
  fireEvent.click(save);
  await waitFor(() => expect(defectSummaryApi.save).toHaveBeenCalled());
}
it('PASS ALL still requires explicit physical coverage before submission', async () => {
  panel();
  const passAll = screen.getByRole('button', { name: 'PASS ALL' });
  expect(passAll).toHaveAttribute('aria-pressed', 'false');
  fireEvent.click(passAll);
  expect(passAll).toHaveAttribute('aria-pressed', 'true');
  expect(passAll).toHaveClass('bg-success-bg', 'text-success-fg');
  expect(screen.getByRole('checkbox', { name: 'I checked all 200 pieces' })).not.toBeChecked();
  expect(screen.getByRole('button', { name: 'Submit inspection' })).toBeDisabled();
  fireEvent.click(screen.getByRole('checkbox', { name: 'I checked all 200 pieces' }));
  fireEvent.click(screen.getByRole('button', { name: 'Submit inspection' }));
  await waitFor(() =>
    expect(inspectionsApi.submitWorkOrderFinalInspection).toHaveBeenCalledWith(
      'wo',
      expect.objectContaining({
        manifest: group.manifest,
        batches: [{ inspection_id: 'qc', coverage_confirmed: true, inspected_quantity: 200 }],
      }),
    ),
  );
});
it('clears the PASS ALL selected state when the defective count changes', () => {
  panel();
  const passAll = screen.getByRole('button', { name: 'PASS ALL' });
  fireEvent.click(passAll);
  fireEvent.change(screen.getByRole('spinbutton', { name: /Affected quantity/ }), { target: { value: '1' } });
  expect(passAll).toHaveAttribute('aria-pressed', 'false');
  expect(passAll).not.toHaveClass('bg-success-bg');
});
it('uses one shared summary for 500 defective pieces with no per-piece inputs', async () => {
  const largeBatch = {
    ...group,
    produced_quantity: 1000,
    manifest: [{ ...group.manifest[0], physical_quantity: 1000 }],
    batches: [{ ...group.batches[0], batch_quantity: 1000 }],
  } as unknown as WorkOrderFinalInspection;
  panel(largeBatch);
  await waitFor(() => expect(defectSummaryApi.show).toHaveBeenCalledWith('qc'));
  fireEvent.change(screen.getByRole('spinbutton', { name: /Affected quantity/ }), {
    target: { value: '500' },
  });
  expect(screen.getAllByLabelText(/Shared description/)).toHaveLength(1);
  expect(screen.queryByLabelText(/Problem for piece/)).not.toBeInTheDocument();
  expect(screen.queryByRole('button', { name: 'FAIL' })).not.toBeInTheDocument();
  expect(screen.queryByRole('button', { name: 'Next problems' })).not.toBeInTheDocument();
  expect(screen.getByRole('button', { name: 'Submit inspection' })).toBeDisabled();
  await saveSummary('The same edge is cracked on the affected pieces.');
  fireEvent.click(screen.getByRole('checkbox', { name: 'I checked all 1000 pieces' }));
  expect(screen.getByRole('button', { name: 'Submit inspection' })).toBeEnabled();
});
it('missing required size readings cannot be bypassed by PASS ALL', () => {
  panel({ ...group, batches: [{ ...group.batches[0], missing_readings: 1 }] });
  fireEvent.click(screen.getByRole('button', { name: 'PASS ALL' }));
  fireEvent.click(screen.getByRole('checkbox', { name: 'I checked all 200 pieces' }));
  expect(screen.getByRole('button', { name: 'Submit inspection' })).toBeDisabled();
});
it('unknown submission outcome locks fresh submission and retains reconciliation', async () => {
  vi.mocked(inspectionsApi.submitWorkOrderFinalInspection).mockRejectedValue(
    new Error('Connection lost'),
  );
  panel();
  fireEvent.click(screen.getByRole('button', { name: 'PASS ALL' }));
  fireEvent.click(screen.getByRole('checkbox', { name: 'I checked all 200 pieces' }));
  fireEvent.click(screen.getByRole('button', { name: 'Submit inspection' }));
  await screen.findByRole('button', { name: 'Check saved result' });
  expect(screen.getByRole('button', { name: 'Submit inspection' })).toBeDisabled();
  expect(inspectionsApi.submitWorkOrderFinalInspection).toHaveBeenCalledTimes(1);
});

it('editing a shared description removes physical coverage confirmation', async () => {
  panel();
  fireEvent.change(screen.getByRole('spinbutton', { name: /Affected quantity/ }), {
    target: { value: '1' },
  });
  fireEvent.click(screen.getByRole('checkbox', { name: 'I checked all 200 pieces' }));
  fireEvent.change(await screen.findByLabelText(/Shared description/), {
    target: { value: 'Scratch' },
  });
  expect(screen.getByRole('checkbox', { name: 'I checked all 200 pieces' })).not.toBeChecked();
});
it('an evidence author cannot see the independent approval action', () => {
  panel({
    ...group,
    state: 'awaiting_review',
    defect_page: {
      page: 1,
      per_page: 50,
      total: 1,
      pieces: [{ piece_number: 7, problem_description: 'Legacy note: chipped edge.', requirement_id: null }],
    },
    batches: [{
      ...group.batches[0],
      status: 'awaiting_review',
      can_review: false,
      sample_defect_count: 1,
      defect_summary: {
        id: 'summary', category: 'surface_damage', category_label: 'Surface damage', other_category: null,
        severity: 'critical', severity_label: 'Critical', affected_quantity: '1',
        description: 'One shared defect across the output batch.',
        photos: [{ id: 'photo', file_name: 'proof.jpg', mime_type: 'image/jpeg', file_size: 5, file_url: '/proof.jpg', created_at: null }],
        created_at: null, updated_at: null,
      },
    }],
  });
  expect(
    screen.queryByRole('button', { name: 'Approve inspection results' }),
  ).not.toBeInTheDocument();
  expect(screen.getByText('One shared defect across the output batch.')).toBeVisible();
  expect(screen.getByRole('img', { name: 'Defect proof: proof.jpg' })).toHaveAttribute('src', '/proof.jpg');
  expect(screen.getByText('Historical per-piece notes')).toBeVisible();
  expect(screen.getByText('Legacy note: chipped edge.')).toBeInTheDocument();
  expect(screen.queryByRole('button', { name: 'Next problems' })).not.toBeInTheDocument();
});
it('saves only the shared defect quantity while retaining size-reading pagination', async () => {
  const measurements = [{
    id: 'size', parameter_name: 'Length', parameter_type: 'dimensional' as const, evaluation_mode: 'numeric' as const,
    sample_index: 0, acceptance_criterion: null, unit_of_measure: 'mm', nominal_value: '1.5',
    tolerance_min: '1', tolerance_max: '2', measured_value: null, is_critical: false, is_pass: null, notes: null,
  }];
  panel({
    ...group,
    batches: [{ ...group.batches[0], defective_quantity: 0, batch_quantity: 200, measurements, missing_readings: 200 }],
  });
  expect(screen.getByRole('button', { name: 'Next readings' })).toBeInTheDocument();
  await waitFor(() => expect(defectSummaryApi.show).toHaveBeenCalled());
  fireEvent.change(screen.getByRole('spinbutton', { name: /Affected quantity/ }), { target: { value: '1' } });
  await saveSummary('One shared issue across the group.');
  fireEvent.click(screen.getByRole('button', { name: 'Save draft' }));
  await waitFor(() => expect(inspectionsApi.saveWorkOrderFinalEvidence).toHaveBeenCalledWith('wo', expect.objectContaining({
    defective_quantity: 1,
    readings: [],
  })));
  expect(vi.mocked(inspectionsApi.saveWorkOrderFinalEvidence).mock.calls[0][1]).not.toHaveProperty('pieces');
  expect(screen.queryByRole('button', { name: 'Next problems' })).not.toBeInTheDocument();
});

it('reloads authoritative versions after an unknown save while retaining the defect count', async () => {
  vi.mocked(inspectionsApi.saveWorkOrderFinalEvidence)
    .mockRejectedValueOnce(new Error('Connection lost'))
    .mockResolvedValue(group);
  vi.mocked(inspectionsApi.getWorkOrderFinalInspection).mockResolvedValue({
    ...group,
    manifest: [{ ...group.manifest[0], result_version: 1 }],
    batches: [{ ...group.batches[0], result_version: 1 }],
  });
  panel();
  fireEvent.change(screen.getByRole('spinbutton', { name: /Affected quantity/ }), {
    target: { value: '1' },
  });
  fireEvent.click(screen.getByRole('button', { name: 'Save draft' }));
  fireEvent.click(await screen.findByRole('button', { name: 'Reload saved totals' }));
  await screen.findByText(/Saved totals refreshed/);
  expect(screen.getByRole('spinbutton', { name: /Affected quantity/ })).toHaveValue(1);
  fireEvent.click(screen.getByRole('button', { name: 'Save draft' }));
  await waitFor(() =>
    expect(inspectionsApi.saveWorkOrderFinalEvidence).toHaveBeenLastCalledWith(
      'wo',
      expect.objectContaining({ expected_result_version: 1 }),
    ),
  );
});

it('uses the existing record layout and explains full-piece measurement coverage', () => {
  const measurements = ['Length', 'Width', 'Height', 'Diameter'].map((name, index) => ({
    id: `size-${index}`,
    parameter_name: name,
    parameter_type: 'dimensional',
    evaluation_mode: 'numeric',
    tolerance_min: '1',
    tolerance_max: '2',
    unit_of_measure: 'mm',
  }));
  panel({
    ...group,
    produced_quantity: 500,
    context: {
      product: { part_number: 'PC-001', name: 'Pivot Cap Cover Type A', unit_of_measure: 'PCS' },
      sales_order: {
        id: 'so-hash',
        so_number: 'SO-202610-0025',
        requested_delivery_date: '2026-10-20',
        current_delivery_date: '2026-10-20',
      },
      batch_number: 'BATCH-202610-0001',
      machine: { code: 'IMM-03', name: 'Sumitomo SE130' },
      mold: { code: 'M-PC-002', name: 'Backup mold' },
      production_started_at: '2026-10-09T06:00:00+08:00',
      production_finished_at: '2026-10-09T08:00:00+08:00',
      production_due_at: '2026-10-19T00:00:00+08:00',
      timezone: 'Asia/Manila',
      output_batches: [{ inspection_id: 'qc', batch_code: 'WO-500-B01' }],
    },
    batches: [
      {
        ...group.batches[0],
        batch_quantity: 500,
        missing_readings: 2000,
        measurements,
      } as unknown as WorkOrderFinalInspection['batches'][number],
    ],
  });
  for (const name of [
    'Record flow',
    'Inspection details',
    'Inspection checklist',
    'Linked records',
    'Inspection history',
  ]) {
    expect(screen.getByRole('heading', { name })).toBeInTheDocument();
  }
  expect(screen.getByText('SO-202610-0025', { exact: true })).toBeInTheDocument();
  expect(
    screen.getByText(
      '4 size checks × 500 pieces = 2,000 required readings. Enter actual measurements; PASS/FAIL is calculated.',
    ),
  ).toBeVisible();
  expect(screen.getByRole('textbox', { name: 'Length · piece 1' })).toBeVisible();
  expect(screen.queryByRole('textbox', { name: 'Length · piece 51' })).not.toBeInTheDocument();
  fireEvent.click(screen.getByText('Order and production details'));
  expect(screen.getByText('Oct 9, 2026, 8:00 AM')).toBeVisible();
  fireEvent.click(screen.getByRole('button', { name: 'PASS ALL' }));
  fireEvent.click(screen.getByRole('checkbox', { name: 'I checked all 500 pieces' }));
  expect(screen.getByRole('button', { name: 'Submit inspection' })).toBeDisabled();
});

it('uses confirmed checks without inventing measurements for version 3', async () => {
  const requirements = [
    ...['Length', 'Width', 'Height', 'Diameter'].map((parameter_name, index) => ({
      id: `size-${index}`,
      parameter_name,
      parameter_type: 'dimensional',
      evaluation_mode: 'numeric',
      nominal_value: '10',
      tolerance_min: '9',
      tolerance_max: '11',
      unit_of_measure: 'mm',
      is_critical: false,
    })),
    {
      id: 'surface',
      parameter_name: 'Surface finish',
      parameter_type: 'visual',
      evaluation_mode: 'manual',
      acceptance_criterion: 'No cracks or exposed fibers',
      is_critical: true,
    },
  ];
  const confirmedGroup = {
    ...group,
    produced_quantity: 500,
    batches: [
      {
        ...group.batches[0],
        batch_quantity: 500,
        missing_readings: 0,
        counted_capture: {
          version: 3,
          workflow: 'finish_first_v1',
          evidence_method: 'confirmed_checks',
          required_quantity: 500,
          result_policy: 'zero_defects',
        },
        measurements: requirements,
      },
    ],
  } as unknown as WorkOrderFinalInspection;

  panel(confirmedGroup);
  expect(screen.getByText(/I checked every piece against all listed requirements/)).toBeInTheDocument();
  expect(screen.getByText('No cracks or exposed fibers')).toBeInTheDocument();
  fireEvent.click(screen.getByRole('button', { name: 'PASS ALL' }));
  expect(screen.queryByRole('textbox', { name: 'Length · piece 1' })).not.toBeInTheDocument();
  expect(
    screen.getByRole('button', { name: 'Submit inspection' }),
  ).toBeDisabled();
  fireEvent.click(
    screen.getByRole('checkbox', { name: /I checked every piece against all listed requirements/ }),
  );
  fireEvent.click(screen.getByRole('button', { name: 'Submit inspection' }));
  await waitFor(() =>
    expect(inspectionsApi.submitWorkOrderFinalInspection).toHaveBeenCalledWith(
      'wo',
      expect.objectContaining({
        batches: [
          {
            inspection_id: 'qc',
            coverage_confirmed: true,
            inspected_quantity: 500,
            checks_confirmed: true,
          },
        ],
      }),
    ),
  );
});

it('submits a shared summary for version 3 confirmed checks without per-piece defect rows', async () => {
  const confirmedGroup = {
    ...group,
    batches: [
      {
        ...group.batches[0],
        counted_capture: {
          version: 3,
          workflow: 'finish_first_v1',
          evidence_method: 'confirmed_checks',
          required_quantity: 200,
          result_policy: 'zero_defects',
        },
        measurements: [
          {
            id: 'size-hash',
            parameter_name: 'Length',
            parameter_type: 'dimensional',
            tolerance_min: '9',
            tolerance_max: '11',
            unit_of_measure: 'mm',
            is_critical: false,
          },
        ],
      },
    ],
  } as unknown as WorkOrderFinalInspection;
  vi.mocked(inspectionsApi.saveWorkOrderFinalEvidence).mockResolvedValue(confirmedGroup);
  panel(confirmedGroup);
  await waitFor(() => expect(defectSummaryApi.show).toHaveBeenCalled());
  fireEvent.change(screen.getByRole('spinbutton', { name: /Affected quantity/ }), {
    target: { value: '1' },
  });
  await saveSummary('Scratch on the edge.');
  expect(screen.queryByLabelText(/Problem for piece/)).not.toBeInTheDocument();
  fireEvent.click(screen.getByRole('checkbox', { name: /I checked every piece against all listed requirements/ }));
  fireEvent.click(screen.getByRole('button', { name: 'Submit inspection' }));
  await waitFor(() => expect(inspectionsApi.submitWorkOrderFinalInspection).toHaveBeenCalledWith('wo', expect.objectContaining({
    batches: [{ inspection_id: 'qc', coverage_confirmed: true, inspected_quantity: 200, checks_confirmed: true }],
  })));
});

it('editing a summary invalidates the final work-order coverage confirmation', async () => {
  panel({
    ...group,
    batches: [{
      ...group.batches[0],
      counted_capture: { version: 3, evidence_method: 'confirmed_checks' },
      measurements: [{ id: 'surface', parameter_name: 'Surface finish', parameter_type: 'visual' }],
    }],
  } as unknown as WorkOrderFinalInspection);
  fireEvent.change(screen.getByRole('spinbutton', { name: /Affected quantity/ }), {
    target: { value: '2' },
  });
  fireEvent.change(await screen.findByLabelText(/Shared description/), { target: { value: 'Scratched edge.' } });
  const confirmation = screen.getByRole('checkbox', {
    name: /I checked every piece against all listed requirements/,
  });
  fireEvent.click(confirmation);
  fireEvent.change(screen.getByLabelText(/Shared description/), { target: { value: 'Changed summary.' } });
  expect(confirmation).not.toBeChecked();
  expect(screen.queryByLabelText(/Problem for piece/)).not.toBeInTheDocument();
  expect(screen.getByRole('button', { name: 'Submit inspection' })).toBeDisabled();
});
