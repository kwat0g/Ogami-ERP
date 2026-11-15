import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { MemoryRouter } from 'react-router-dom';
import { fireEvent, render, screen, waitFor } from '@testing-library/react';
import { beforeEach, expect, it, vi } from 'vitest';
import { inspectionsApi } from '@/api/quality/inspections';
import { defectSummaryApi } from '@/api/quality/defectSummary';
import type { Inspection } from '@/types/quality';
import { FullCountDecisionPanel } from './FullCountDecisionPanel';

vi.mock('@/api/quality/inspections', () => ({ inspectionsApi: { recordBatchResult: vi.fn() } }));
vi.mock('@/api/quality/defectSummary', () => ({ defectSummaryApi: { show: vi.fn(), save: vi.fn(), clear: vi.fn() } }));

const inspection = {
  id: 'count-qc', status: 'draft', result_version: 0, batch_quantity: 100,
  source_uom_code: 'PCS', measurements: [{ id: 'count-row', item_result: null }],
} as unknown as Inspection;

beforeEach(() => {
  vi.clearAllMocks();
  vi.mocked(inspectionsApi.recordBatchResult).mockResolvedValue({ ...inspection, status: 'awaiting_review' });
  vi.mocked(defectSummaryApi.show).mockResolvedValue(null);
  vi.mocked(defectSummaryApi.save).mockImplementation(async (_id, data) => ({
    id: 'summary', category: data.category, category_label: 'Surface damage', other_category: null,
    severity: data.severity, severity_label: 'Major', affected_quantity: data.affected_quantity,
    description: data.description, photos: [{ id: 'photo', file_name: 'proof.jpg', mime_type: 'image/jpeg', file_size: 1, file_url: '/proof.jpg', created_at: null }], created_at: null, updated_at: null,
  }));
});

function panel() {
  return render(<QueryClientProvider client={new QueryClient()}>
    <MemoryRouter><FullCountDecisionPanel inspection={inspection} canManage /></MemoryRouter>
  </QueryClientProvider>);
}
async function saveSummary(description: string) {
  fireEvent.change(await screen.findByLabelText(/Shared description/), { target: { value: description } });
  fireEvent.change(screen.getByLabelText('Defect photos'), { target: { files: [new File(['proof'], 'proof.jpg', { type: 'image/jpeg' })] } });
  fireEvent.click(screen.getByRole('button', { name: 'Save defect summary' }));
  await waitFor(() => expect(defectSummaryApi.save).toHaveBeenCalled());
}

it('requires full-count confirmation and submits zero defects', async () => {
  panel();
  await waitFor(() => expect(defectSummaryApi.show).toHaveBeenCalled());
  fireEvent.change(screen.getByRole('spinbutton', { name: /Affected quantity/ }), { target: { value: '0' } });
  expect(screen.getByRole('button', { name: 'Submit inspection' })).toBeDisabled();
  fireEvent.click(screen.getByRole('checkbox', { name: /I counted all 100 received pieces/ }));
  fireEvent.click(screen.getByRole('button', { name: 'Submit inspection' }));
  await waitFor(() => expect(inspectionsApi.recordBatchResult).toHaveBeenCalledWith('count-qc', {
    expected_result_version: 0, coverage_confirmed: true, inspected_quantity: 100,
    sample_defect_count: 0, physical_defects_confirmed: false,
    items: [{ id: 'count-row', result: 'passed' }],
  }));
});

it('accepts good pieces only after summary and segregation, rejects overcount', async () => {
  panel();
  await waitFor(() => expect(defectSummaryApi.show).toHaveBeenCalled());
  const defects = screen.getByRole('spinbutton', { name: /Affected quantity/ });
  fireEvent.change(defects, { target: { value: '101' } });
  expect(screen.getByRole('button', { name: 'Submit inspection' })).toBeDisabled();
  fireEvent.change(defects, { target: { value: '2' } });
  expect(screen.getByText('98 pieces eligible for acceptance')).toBeVisible();
  fireEvent.click(screen.getByRole('checkbox', { name: /I counted all 100 received pieces/ }));
  await saveSummary('Two torn bags');
  expect(screen.getByRole('button', { name: 'Submit inspection' })).toBeDisabled();
  fireEvent.click(screen.getByRole('checkbox', { name: 'I segregated the defective pieces' }));
  fireEvent.click(screen.getByRole('button', { name: 'Submit inspection' }));
  await waitFor(() => expect(inspectionsApi.recordBatchResult).toHaveBeenCalledWith('count-qc', expect.objectContaining({
    sample_defect_count: 2, defects_removed_confirmed: true,
  })));
});

it('holds the whole receipt when every piece is defective and segregated', async () => {
  panel();
  await waitFor(() => expect(defectSummaryApi.show).toHaveBeenCalled());
  fireEvent.change(screen.getByRole('spinbutton', { name: /Affected quantity/ }), { target: { value: '100' } });
  fireEvent.click(screen.getByRole('checkbox', { name: /I counted all 100 received pieces/ }));
  await saveSummary('All bags torn');
  expect(screen.getByRole('button', { name: 'Submit inspection' })).toBeDisabled();
  fireEvent.click(screen.getByRole('checkbox', { name: 'I segregated the defective pieces' }));
  fireEvent.click(screen.getByRole('button', { name: 'Submit inspection' }));
  await waitFor(() => expect(inspectionsApi.recordBatchResult).toHaveBeenCalledWith('count-qc', expect.objectContaining({
    sample_defect_count: 100, defects_removed_confirmed: true,
  })));
});
