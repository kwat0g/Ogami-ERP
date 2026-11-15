import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { fireEvent, render, screen, waitFor } from '@testing-library/react';
import { beforeEach, expect, it, vi } from 'vitest';
import { useState } from 'react';
import { defectSummaryApi } from '@/api/quality/defectSummary';
import type { InspectionDefectSummary } from '@/types/quality';
import { InspectionDefectSummaryEditor } from './InspectionDefectSummaryEditor';

vi.mock('@/api/quality/defectSummary', () => ({
  defectSummaryApi: { show: vi.fn(), save: vi.fn(), clear: vi.fn() },
}));

const summary: InspectionDefectSummary = {
  id: 'summary-1',
  category: 'surface_damage',
  category_label: 'Surface damage',
  other_category: null,
  severity: 'major',
  severity_label: 'Major',
  affected_quantity: '500.000',
  description: 'The same edge is cracked on each affected piece.',
  photos: [
    { id: 'photo-1', file_name: 'edge.jpg', mime_type: 'image/jpeg', file_size: 100, file_url: '/edge.jpg', created_at: null },
    { id: 'photo-2', file_name: 'detail.png', mime_type: 'image/png', file_size: 100, file_url: '/detail.png', created_at: null },
  ],
  created_at: null,
  updated_at: null,
};

function editor(overrides: Partial<React.ComponentProps<typeof InspectionDefectSummaryEditor>> = {}) {
  const props: React.ComponentProps<typeof InspectionDefectSummaryEditor> = {
    inspectionId: 'inspection-1',
    sourceQuantity: '1000',
    unit: 'PCS',
    quantity: '',
    onQuantityChange: vi.fn(),
    canManage: true,
    onSaved: vi.fn(),
    ...overrides,
  };
  const queryClient = new QueryClient({ defaultOptions: { queries: { retry: false } } });
  function ControlledEditor() {
    const [quantity, setQuantity] = useState(props.quantity);
    return <QueryClientProvider client={queryClient}>
      <InspectionDefectSummaryEditor {...props} quantity={quantity} onQuantityChange={value => {
        props.onQuantityChange(value);
        setQuantity(value);
      }} />
    </QueryClientProvider>;
  }
  render(<ControlledEditor />);
  return props;
}

beforeEach(() => {
  vi.clearAllMocks();
  vi.mocked(defectSummaryApi.show).mockResolvedValue(null);
  vi.mocked(defectSummaryApi.save).mockResolvedValue(summary);
});

it('uses one shared description for 500 affected pieces, with no per-piece fields', async () => {
  const onQuantityChange = vi.fn();
  editor({ onQuantityChange });
  await waitFor(() => expect(defectSummaryApi.show).toHaveBeenCalledWith('inspection-1'));

  fireEvent.change(screen.getByLabelText(/^Affected quantity/), { target: { value: '500' } });
  fireEvent.change(screen.getByLabelText(/Shared description/), { target: { value: 'The same edge is cracked on each affected piece.' } });
  expect(screen.queryByLabelText(/Defective piece \d+/)).not.toBeInTheDocument();
  expect(onQuantityChange).toHaveBeenCalledWith('500');
});

it('requires a description and at least one photo, and saves multiple photos together', async () => {
  editor({ quantity: '2' });
  await waitFor(() => expect(defectSummaryApi.show).toHaveBeenCalled());
  const save = screen.getByRole('button', { name: 'Save defect summary' });
  expect(save).toBeDisabled();

  fireEvent.change(screen.getByLabelText(/Shared description/), { target: { value: 'Both pieces have the same crack.' } });
  expect(save).toBeDisabled();
  const first = new File(['jpg'], 'edge.jpg', { type: 'image/jpeg' });
  const second = new File(['png'], 'edge-detail.png', { type: 'image/png' });
  fireEvent.change(screen.getByLabelText('Defect photos'), { target: { files: [first, second] } });
  expect(save).toBeEnabled();

  fireEvent.click(save);
  await waitFor(() => expect(defectSummaryApi.save).toHaveBeenCalledWith('inspection-1', expect.objectContaining({
    affected_quantity: '2',
    description: 'Both pieces have the same crack.',
    photos: [first, second],
  })));
});

it('allows zero defects without showing summary fields or requiring a photo', async () => {
  editor({ quantity: '0' });
  await waitFor(() => expect(defectSummaryApi.show).toHaveBeenCalled());

  expect(screen.queryByLabelText('Shared description')).not.toBeInTheDocument();
  expect(screen.queryByLabelText('Defect photos')).not.toBeInTheDocument();
  expect(screen.getByText('No description or photos are needed when the affected quantity is 0.')).toBeInTheDocument();
});

it('explains an invalid PCS quantity even when the value is below one', async () => {
  editor({ quantity: '0.5' });
  await waitFor(() => expect(defectSummaryApi.show).toHaveBeenCalled());

  expect(screen.getByText('Enter 0 or a whole number up to 1000 PCS.')).toBeVisible();
  expect(screen.queryByLabelText('Shared description')).not.toBeInTheDocument();
});

it('restores the saved summary and photos after reload', async () => {
  vi.mocked(defectSummaryApi.show).mockResolvedValue(summary);
  const onQuantityChange = vi.fn();
  editor({ onQuantityChange });

  expect(await screen.findByDisplayValue(summary.description)).toBeInTheDocument();
  await waitFor(() => expect(onQuantityChange).toHaveBeenCalledWith('500.000'));
  expect(screen.getByText('edge.jpg')).toBeInTheDocument();
  expect(screen.getByText('detail.png')).toBeInTheDocument();
});

it('shows unsaved edits and restores the saved state when an edit is reverted', async () => {
  vi.mocked(defectSummaryApi.show).mockResolvedValue(summary);
  const onSaved = vi.fn();
  editor({ quantity: '500.000', onSaved });
  await screen.findByDisplayValue(summary.description);

  fireEvent.change(screen.getByLabelText('Severity'), { target: { value: 'critical' } });
  expect(screen.getByText('Save your changes before submitting the inspection.')).toBeVisible();
  expect(onSaved).toHaveBeenLastCalledWith(null);

  fireEvent.change(screen.getByLabelText('Severity'), { target: { value: 'major' } });
  await waitFor(() => expect(onSaved).toHaveBeenLastCalledWith(summary));
  expect(screen.getByText('Summary saved. You can submit the inspection.')).toBeVisible();
});

it('restores a saved positive quantity when the surrounding draft starts at zero', async () => {
  vi.mocked(defectSummaryApi.show).mockResolvedValue(summary);
  const onQuantityChange = vi.fn();
  editor({ quantity: '0', onQuantityChange });

  expect(await screen.findByDisplayValue(summary.description)).toBeInTheDocument();
  await waitFor(() => expect(onQuantityChange).toHaveBeenCalledWith('500.000'));
});

it('does not replace a quantity entered while the saved summary is loading', async () => {
  let resolveSummary: (value: InspectionDefectSummary | null) => void = () => {};
  vi.mocked(defectSummaryApi.show).mockImplementation(() => new Promise(resolve => {
    resolveSummary = resolve;
  }));
  const onQuantityChange = vi.fn();
  editor({ quantity: '0', onQuantityChange });
  await waitFor(() => expect(defectSummaryApi.show).toHaveBeenCalled());

  fireEvent.change(screen.getByLabelText(/^Affected quantity/), { target: { value: '1' } });
  resolveSummary(summary);

  expect(await screen.findByDisplayValue(summary.description)).toBeInTheDocument();
  expect(screen.getByLabelText(/^Affected quantity/)).toHaveValue(1);
  expect(onQuantityChange).toHaveBeenCalledTimes(1);
});

it('removes a saved photo while retaining at least one attachment', async () => {
  vi.mocked(defectSummaryApi.show).mockResolvedValue(summary);
  editor({ quantity: '500.000' });
  fireEvent.click(await screen.findByRole('button', { name: 'Remove edge.jpg' }));

  expect(screen.queryByText('edge.jpg')).not.toBeInTheDocument();
  expect(screen.getByText('detail.png')).toBeInTheDocument();
  fireEvent.click(screen.getByRole('button', { name: 'Save defect summary' }));

  await waitFor(() => expect(defectSummaryApi.save).toHaveBeenCalledWith('inspection-1', expect.objectContaining({
    removePhotoIds: ['photo-1'],
    photos: [],
  })));
});

it('retains description and selected photos when saving fails', async () => {
  vi.mocked(defectSummaryApi.save).mockRejectedValue(new Error('Upload failed'));
  editor({ quantity: '2' });
  await waitFor(() => expect(defectSummaryApi.show).toHaveBeenCalled());
  const description = 'A shared inspection note.';
  const photo = new File(['jpg'], 'proof.jpg', { type: 'image/jpeg' });
  fireEvent.change(screen.getByLabelText(/Shared description/), { target: { value: description } });
  fireEvent.change(screen.getByLabelText('Defect photos'), { target: { files: [photo] } });
  fireEvent.click(screen.getByRole('button', { name: 'Save defect summary' }));

  expect(await screen.findByRole('alert')).toHaveTextContent('Upload failed');
  expect(screen.getByDisplayValue(description)).toBeInTheDocument();
  expect(screen.getByText('proof.jpg')).toBeInTheDocument();
});
