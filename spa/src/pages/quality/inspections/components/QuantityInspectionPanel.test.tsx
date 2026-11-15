import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { MemoryRouter } from 'react-router-dom';
import { fireEvent, render, screen, waitFor } from '@testing-library/react';
import { beforeEach, expect, it, vi } from 'vitest';
import { inspectionsApi } from '@/api/quality/inspections';
import type { Inspection } from '@/types/quality';
import { QuantityInspectionPanel } from './QuantityInspectionPanel';
vi.mock('@/api/quality/inspections', () => ({ inspectionsApi: { recordBatchResult: vi.fn() } }));
vi.mock('@/api/quality/defectSummary', () => ({ defectSummaryApi: { show: vi.fn(), save: vi.fn(), clear: vi.fn() } }));
import { defectSummaryApi } from '@/api/quality/defectSummary';
const source = { id: 'qc', stage: 'incoming', status: 'draft', result_version: 0, sampling_basis: 'lot', source_quantity: '500.000', source_uom_code: 'kg', batch_quantity: 1, counted_capture: { version: 1, required_quantity: 1, result_policy: 'zero_defects' }, measurements: [{ id: 'moisture', evaluation_mode: 'numeric' }] } as unknown as Inspection;
function panel(inspection = source) {
  render(<QueryClientProvider client={new QueryClient()}><MemoryRouter><QuantityInspectionPanel inspection={inspection} canManage /></MemoryRouter></QueryClientProvider>);
}
beforeEach(() => {
  vi.clearAllMocks();
  vi.mocked(inspectionsApi.recordBatchResult).mockResolvedValue({ ...source, status: 'awaiting_review' });
  vi.mocked(defectSummaryApi.show).mockResolvedValue(null);
  vi.mocked(defectSummaryApi.save).mockImplementation(async (_id, data) => ({
    id: 'summary', category: data.category, category_label: 'Surface damage', other_category: null,
    severity: data.severity, severity_label: 'Major', affected_quantity: data.affected_quantity,
    description: data.description, photos: [{ id: 'photo', file_name: 'proof.jpg', mime_type: 'image/jpeg', file_size: 1, file_url: '/proof.jpg', created_at: null }], created_at: null, updated_at: null,
  }));
});
it('records decimal defective kg with one shared summary and no sample readings', async () => {
  panel();
  await waitFor(() => expect(defectSummaryApi.show).toHaveBeenCalledWith('qc'));
  fireEvent.change(screen.getByLabelText(/Affected quantity/), { target: { value: '2.5' } });
  expect(screen.queryByText(/Moisture/)).not.toBeInTheDocument();
  fireEvent.change(await screen.findByLabelText(/Shared description/), { target: { value: 'Contaminated resin' } });
  fireEvent.change(screen.getByLabelText('Defect photos'), { target: { files: [new File(['proof'], 'proof.jpg', { type: 'image/jpeg' })] } });
  fireEvent.click(screen.getByRole('button', { name: 'Save defect summary' }));
  await waitFor(() => expect(defectSummaryApi.save).toHaveBeenCalled());
  fireEvent.click(screen.getByRole('button', { name: 'Submit inspection' }));
  await waitFor(() => expect(inspectionsApi.recordBatchResult).toHaveBeenCalledWith('qc', { expected_result_version: 0, quantity_result: { defective_quantity: '2.5' } }));
});
it('requires an explicit zero for PASS and rejects quantities above the receipt', () => {
  panel();
  const submit = screen.getByRole('button', { name: 'Submit inspection' });
  expect(submit).toBeDisabled();
  fireEvent.change(screen.getByLabelText(/Affected quantity/), { target: { value: '501' } });
  expect(submit).toBeDisabled();
  fireEvent.change(screen.getByLabelText(/Affected quantity/), { target: { value: '0' } });
  expect(screen.getByText('PASS')).toBeInTheDocument();
  expect(submit).toBeEnabled();
});
it('uses one shared summary and blocks submission until it is saved for 500 defective pieces', async () => {
  panel({ ...source, sampling_basis: 'piece', source_uom_code: 'PCS', batch_quantity: 500 });
  await waitFor(() => expect(defectSummaryApi.show).toHaveBeenCalledWith('qc'));
  fireEvent.change(screen.getByLabelText(/Affected quantity/), { target: { value: '500' } });
  expect(screen.queryByLabelText(/Defective piece \d+/)).not.toBeInTheDocument();
  expect(screen.getAllByLabelText(/Shared description/)).toHaveLength(1);
  expect(screen.getByRole('button', { name: 'Submit inspection' })).toBeDisabled();
  fireEvent.change(screen.getByLabelText(/Shared description/), { target: { value: 'A single shared issue.' } });
  fireEvent.change(screen.getByLabelText('Defect photos'), { target: { files: [new File(['proof'], 'proof.jpg', { type: 'image/jpeg' })] } });
  fireEvent.click(screen.getByRole('button', { name: 'Save defect summary' }));
  await waitFor(() => expect(screen.getByRole('button', { name: 'Submit inspection' })).toBeEnabled());
});
