import { client } from '../client';
import type { PaginatedResponse } from '@/types';
import type { CustomerOrderLimitIncreaseRequest } from '@/types/b2b';

export const customerOrderLimitIncreaseRequestsApi = {
  list: (page = 1, status?: CustomerOrderLimitIncreaseRequest['status']) =>
    client.get<PaginatedResponse<CustomerOrderLimitIncreaseRequest>>('/crm/order-limit-increase-requests', { params: { page, status } }).then((response) => response.data),
  decide: (id: string, decision: 'approve' | 'reject', version: number, notes: string) =>
    client.post<{ data: CustomerOrderLimitIncreaseRequest }>(`/crm/order-limit-increase-requests/${id}/decision`, { decision, version, notes }).then((response) => response.data.data),
};
