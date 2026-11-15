import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { MemoryRouter } from 'react-router-dom';
import { fireEvent, render, screen, waitFor } from '@testing-library/react';
import { beforeEach, expect, it, vi } from 'vitest';
import { inspectionsApi } from '@/api/quality/inspections';
import type { Inspection } from '@/types/quality';
import { CountedBatchInspectionPanel } from './CountedBatchInspectionPanel';
vi.mock('@/api/quality/inspections', () => ({ inspectionsApi: { recordBatchResult: vi.fn() } }));
vi.mock('@/api/quality/defectSummary', () => ({ defectSummaryApi: { show: vi.fn(), save: vi.fn(), clear: vi.fn() } }));
import { defectSummaryApi } from '@/api/quality/defectSummary';
const inspection = {
  id: 'counted',
  status: 'draft',
  result_version: 0,
  batch_quantity: 200,
  counted_capture: { version: 1, required_quantity: 200, result_policy: 'zero_defects' },
  measurements: [
    {
      id: 'appearance',
      parameter_name: 'Appearance',
      acceptance_criterion: 'No cracks',
      parameter_type: 'visual',
    },
  ],
} as unknown as Inspection;
function panel(source = inspection) {
  return render(
    <QueryClientProvider client={new QueryClient()}>
      <MemoryRouter>
        <CountedBatchInspectionPanel inspection={source} canManage />
      </MemoryRouter>
    </QueryClientProvider>,
  );
}
beforeEach(() => {
  vi.clearAllMocks();
  vi.mocked(defectSummaryApi.show).mockResolvedValue(null);
  vi.mocked(defectSummaryApi.save).mockImplementation(async (_id, data) => ({
    id: 'summary', category: data.category, category_label: 'Surface damage', other_category: null,
    severity: data.severity, severity_label: 'Minor', affected_quantity: data.affected_quantity,
    description: data.description, photos: [{ id: 'photo', file_name: 'proof.jpg', mime_type: 'image/jpeg', file_size: 1, file_url: '/proof.jpg', created_at: null }], created_at: null, updated_at: null,
  }));
});
async function saveSummary(description = 'Shared defect description') {
  fireEvent.change(await screen.findByLabelText(/Shared description/), { target: { value: description } });
  fireEvent.change(screen.getByLabelText('Defect photos'), { target: { files: [new File(['proof'], 'proof.jpg', { type: 'image/jpeg' })] } });
  fireEvent.click(screen.getByRole('button', { name: 'Save defect summary' }));
  await waitFor(() => expect(defectSummaryApi.save).toHaveBeenCalled());
}
it('keeps a 200-piece inspection bounded and never sends per-piece answers', async () => {
  vi.mocked(inspectionsApi.recordBatchResult).mockResolvedValue({
    ...inspection,
    status: 'awaiting_review',
  });
  panel();
  await waitFor(() => expect(defectSummaryApi.show).toHaveBeenCalled());
  expect(screen.getAllByRole('spinbutton')).toHaveLength(1);
  expect(screen.queryByRole('button', { name: 'PASS' })).not.toBeInTheDocument();
  expect(screen.getByRole('button', { name: 'Submit inspection' })).toBeDisabled();
  fireEvent.change(screen.getByRole('spinbutton', { name: /Affected quantity/ }), {
    target: { value: '0' },
  });
  fireEvent.click(screen.getByRole('checkbox', { name: /I checked all 200 pieces/ }));
  fireEvent.click(screen.getByRole('button', { name: 'Submit inspection' }));
  await waitFor(() =>
    expect(inspectionsApi.recordBatchResult).toHaveBeenCalledWith('counted', {
      expected_result_version: 0,
      coverage_confirmed: true,
      inspected_quantity: 200,
      sample_defect_count: 0,
      requirement_problem: false,
      measurements: [],
    }),
  );
});
it('requires one saved summary for defective pieces and holds the entire batch', async () => {
  panel();
  fireEvent.change(screen.getByRole('spinbutton', { name: /Affected quantity/ }), {
    target: { value: '2' },
  });
  expect(screen.getAllByRole('checkbox')).toHaveLength(1);
  fireEvent.click(screen.getByRole('checkbox', { name: /I checked all 200 pieces/ }));
  expect(screen.getByRole('button', { name: 'Submit inspection' })).toBeDisabled();
  expect(screen.getByText(/FAIL.*batch stays on hold/)).toBeVisible();
  await saveSummary('Two cracked pieces');
  expect(screen.getByRole('button', { name: 'Submit inspection' })).toBeEnabled();
});

it('requires defective supplier pieces to be separated before submission', async () => {
  const incoming = { ...inspection, count_only_incoming: true };
  vi.mocked(inspectionsApi.recordBatchResult).mockResolvedValue({ ...incoming, status: 'awaiting_review' });
  panel(incoming);
  fireEvent.change(screen.getByRole('spinbutton', { name: /Affected quantity/ }), { target: { value: '2' } });
  await saveSummary('Two torn bags');
  fireEvent.click(screen.getByRole('checkbox', { name: /I checked all 200 pieces/ }));
  expect(screen.getByRole('button', { name: 'Submit inspection' })).toBeDisabled();
  fireEvent.click(screen.getByRole('checkbox', { name: /I separated the 2 defective pieces/ }));
  fireEvent.click(screen.getByRole('button', { name: 'Submit inspection' }));
  await waitFor(() => expect(inspectionsApi.recordBatchResult).toHaveBeenCalledWith('counted', expect.objectContaining({
    sample_defect_count: 2,
    defects_removed_confirmed: true,
  })));
});

it('keeps fallback supplier QC focused on defective pieces', async () => {
  const incoming = { ...inspection, count_only_incoming: true };
  vi.mocked(inspectionsApi.recordBatchResult).mockResolvedValue({ ...incoming, status: 'awaiting_review' });
  panel(incoming);
  fireEvent.change(screen.getByRole('spinbutton', { name: /Affected quantity/ }), { target: { value: '0' } });
  expect(screen.queryByRole('checkbox', { name: 'Other inspection requirement failed' })).not.toBeInTheDocument();
  fireEvent.click(screen.getByRole('checkbox', { name: /I checked all 200 pieces/ }));
  fireEvent.click(screen.getByRole('button', { name: 'Submit inspection' }));
  await waitFor(() => expect(inspectionsApi.recordBatchResult).toHaveBeenCalledWith('counted', expect.objectContaining({
    sample_defect_count: 0,
    requirement_problem: false,
  })));
});

it('lets QC report a separate batch problem when no pieces are defective', async () => {
  vi.mocked(inspectionsApi.recordBatchResult).mockResolvedValue({
    ...inspection,
    status: 'awaiting_review',
  });
  panel();
  fireEvent.change(screen.getByRole('spinbutton', { name: /Affected quantity/ }), {
    target: { value: '0' },
  });
  fireEvent.click(screen.getByRole('checkbox', { name: 'Other inspection requirement failed' }));
  expect(screen.getByRole('button', { name: 'Submit inspection' })).toBeDisabled();
  fireEvent.change(screen.getByRole('textbox', { name: 'Describe the problem' }), {
    target: { value: 'Required certificate missing' },
  });
  fireEvent.click(screen.getByRole('checkbox', { name: /I checked all 200 pieces/ }));
  fireEvent.click(screen.getByRole('button', { name: 'Submit inspection' }));
  await waitFor(() =>
    expect(inspectionsApi.recordBatchResult).toHaveBeenCalledWith('counted',
      expect.objectContaining({
        sample_defect_count: 0,
        requirement_problem: true,
        problem_description: 'Required certificate missing',
      })),
  );
});

it('uses the affected quantity as the material-lot defect confirmation', async () => {
  const lot = { ...inspection, sampling_basis: 'lot' as const, source_quantity: '200.500', source_uom_code: 'kg',
    batch_quantity: 1, counted_capture: { ...inspection.counted_capture!, required_quantity: 1 } };
  vi.mocked(inspectionsApi.recordBatchResult).mockClear();
  vi.mocked(inspectionsApi.recordBatchResult).mockResolvedValue({ ...lot, status: 'awaiting_review' });
  panel(lot);
  expect(screen.getByText(/Check the material lot \(200.500 kg\)/)).toBeVisible();
  expect(screen.queryByRole('checkbox', { name: 'Material lot has defects' })).not.toBeInTheDocument();
  expect(screen.getByRole('spinbutton', { name: /Affected quantity/ })).toBeVisible();
  fireEvent.change(screen.getByRole('spinbutton', { name: /Affected quantity/ }), { target: { value: '4.5' } });
  await saveSummary('Contaminated resin');
  fireEvent.click(screen.getByRole('checkbox', { name: /I checked all 1 lot/ }));
  fireEvent.click(screen.getByRole('button', { name: 'Submit inspection' }));
  await waitFor(() => expect(inspectionsApi.recordBatchResult).toHaveBeenCalledWith('counted', expect.objectContaining({
    physical_defects_confirmed: true, inspected_quantity: 1,
  })));
  expect(vi.mocked(inspectionsApi.recordBatchResult).mock.calls[0][1]).not.toHaveProperty('sample_defect_count');
});

it('uses zero affected lot quantity to confirm no physical defects', async () => {
  const lot = { ...inspection, sampling_basis: 'lot' as const, source_quantity: '200.500', source_uom_code: 'kg',
    batch_quantity: 1, counted_capture: { ...inspection.counted_capture!, required_quantity: 1 } };
  vi.mocked(inspectionsApi.recordBatchResult).mockResolvedValue({ ...lot, status: 'awaiting_review' });
  panel(lot);
  fireEvent.change(await screen.findByRole('spinbutton', { name: /Affected quantity/ }), { target: { value: '0' } });
  fireEvent.click(screen.getByRole('checkbox', { name: /I checked all 1 lot/ }));
  fireEvent.click(screen.getByRole('button', { name: 'Submit inspection' }));
  await waitFor(() => expect(inspectionsApi.recordBatchResult).toHaveBeenCalledWith('counted', expect.objectContaining({
    physical_defects_confirmed: false,
    inspected_quantity: 1,
  })));
});
