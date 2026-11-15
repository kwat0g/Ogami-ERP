import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { cleanup, fireEvent, render, screen, waitFor } from '@testing-library/react';
import { MemoryRouter, Route, Routes } from 'react-router-dom';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { customerPortalApi } from '@/api/b2b/customer';
import type { PortalCatalogItem } from '@/types/b2b';
import CustomerOrderLimitIncreaseRequestPage from './new';

vi.mock('@/api/b2b/customer', () => ({
  customerPortalApi: {
    listCatalog: vi.fn(),
    createOrderLimitIncreaseRequest: vi.fn(),
  },
}));

const product: PortalCatalogItem = {
  id: 'product-1',
  part_number: 'WB-001',
  name: 'Wiper Bushing',
  unit_of_measure: 'pcs',
  unit_price: '25.00',
  pricing_method: 'fixed',
  minimum_order_quantity: '5.000',
  maximum_order_quantity: '20.000',
};

function renderPage() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } });
  render(
    <QueryClientProvider client={client}>
      <MemoryRouter initialEntries={['/portal/customer/order-limit-requests/new?product_id=product-1&requested_maximum_quantity=35']}>
        <Routes>
          <Route path="/portal/customer/order-limit-requests/new" element={<CustomerOrderLimitIncreaseRequestPage />} />
          <Route path="/portal/customer/order-limit-requests" element={<div data-testid="order-limit-requests-list">Order limit requests</div>} />
        </Routes>
      </MemoryRouter>
    </QueryClientProvider>,
  );
}

beforeEach(() => {
  vi.clearAllMocks();
  vi.mocked(customerPortalApi.listCatalog).mockResolvedValue([product]);
  vi.mocked(customerPortalApi.createOrderLimitIncreaseRequest).mockResolvedValue({ id: 'request-1' } as never);
});

afterEach(cleanup);

describe('customer order-limit increase request form', () => {
  it('submits a separate limit request without asking for a target price', async () => {
    renderPage();

    expect(await screen.findByText('Current maximum per order')).toBeVisible();
    expect(screen.getByText('20 pcs')).toBeVisible();
    expect(screen.getByRole('spinbutton', { name: 'Requested maximum per order' })).toHaveValue(35);
    expect(screen.queryByLabelText(/target unit price/i)).not.toBeInTheDocument();
    fireEvent.change(screen.getByRole('textbox', { name: 'Reason for request' }), {
      target: { value: 'Our monthly replenishment is increasing.' },
    });
    fireEvent.click(screen.getByRole('button', { name: 'Submit limit increase request' }));

    await waitFor(() => expect(customerPortalApi.createOrderLimitIncreaseRequest).toHaveBeenCalledWith({
      product_id: 'product-1',
      requested_maximum_quantity: 35,
      customer_notes: 'Our monthly replenishment is increasing.',
    }));
    expect(await screen.findByTestId('order-limit-requests-list')).toBeVisible();
  });
});
