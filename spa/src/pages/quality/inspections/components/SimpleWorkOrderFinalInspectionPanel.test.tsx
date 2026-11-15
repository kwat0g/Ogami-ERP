import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { MemoryRouter } from 'react-router-dom';
import { fireEvent, render, screen, waitFor } from '@testing-library/react';
import { beforeEach, expect, it, vi } from 'vitest';
import { inspectionsApi } from '@/api/quality/inspections';
import { defectSummaryApi } from '@/api/quality/defectSummary';
import type { WorkOrderFinalInspection } from '@/types/quality-final';
import { SimpleWorkOrderFinalInspectionPanel } from './SimpleWorkOrderFinalInspectionPanel';
vi.mock('@/api/quality/inspections', () => ({ inspectionsApi: { recordBatchResult: vi.fn(), getWorkOrderFinalInspection: vi.fn(), reviewWorkOrderFinalInspection: vi.fn() } }));
vi.mock('@/api/quality/defectSummary', () => ({ defectSummaryApi: { show: vi.fn(), save: vi.fn(), clear: vi.fn() } }));
const group = { work_order_id: 'wo', produced_quantity: 5, defective_quantity: 0, accepted_quantity: 0, state: 'checking', manifest: [], batches: [{ id: 'qc', inspection_number: 'QC-1', status: 'draft', stage: 'outgoing', result_version: 0, sampling_basis: 'piece', source_quantity: '5.000', source_uom_code: 'PCS', batch_quantity: 5, counted_capture: { version: 2, workflow: 'finish_first_v1', required_quantity: 5 }, measurements: [{ parameter_name: 'Diameter', evaluation_mode: 'numeric', tolerance_min: '1', tolerance_max: '2' }] }] } as unknown as WorkOrderFinalInspection;
function panel(data = group) { render(<QueryClientProvider client={new QueryClient()}><MemoryRouter><SimpleWorkOrderFinalInspectionPanel group={data} canManage canReview /></MemoryRouter></QueryClientProvider>); }
beforeEach(() => {
  vi.clearAllMocks();
  vi.mocked(defectSummaryApi.show).mockResolvedValue(null);
  vi.mocked(defectSummaryApi.save).mockImplementation(async (_id, data) => ({
    id: 'summary', category: data.category, category_label: 'Surface damage', other_category: null,
    severity: data.severity, severity_label: 'Major', affected_quantity: data.affected_quantity,
    description: data.description, photos: [{ id: 'photo', file_name: 'proof.jpg', mime_type: 'image/jpeg', file_size: 5, file_url: '/proof.jpg', created_at: null }], created_at: null, updated_at: null,
  }));
});
it('submits final QC from a defect count alone with no measurement or confirmation inputs', async () => {
  vi.mocked(inspectionsApi.recordBatchResult).mockResolvedValue({ ...group.batches[0], status: 'awaiting_review' });
  vi.mocked(inspectionsApi.getWorkOrderFinalInspection).mockResolvedValue(group);
  panel();
  expect(screen.queryByRole('checkbox')).not.toBeInTheDocument();
  await waitFor(() => expect(defectSummaryApi.show).toHaveBeenCalled());
  fireEvent.change(screen.getByLabelText(/Affected quantity/), { target: { value: '0' } });
  fireEvent.click(screen.getByRole('button', { name: 'Submit inspection' }));
  await waitFor(() => expect(inspectionsApi.recordBatchResult).toHaveBeenCalledWith('qc', { expected_result_version: 0, quantity_result: { defective_quantity: '0' } }));
});
it('shows one shared summary for the defective final pieces', async () => {
  panel();
  await waitFor(() => expect(defectSummaryApi.show).toHaveBeenCalled());
  fireEvent.change(screen.getByLabelText(/Affected quantity/), { target: { value: '2' } });
  expect(screen.getAllByLabelText(/Shared description/)).toHaveLength(1);
  expect(screen.queryByLabelText(/Defective piece \d+/)).not.toBeInTheDocument();
  expect(screen.getByRole('button', { name: 'Submit inspection' })).toBeDisabled();
});
