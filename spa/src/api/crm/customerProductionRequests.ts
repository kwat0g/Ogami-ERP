import { client } from '../client';
import type { PaginatedResponse } from '@/types';
import type { CustomerProductionRequest } from '@/types/b2b';

export const customerProductionRequestsApi = {
  list: (params?: { status?: string; page?: number; per_page?: number }, signal?: AbortSignal) =>
    client.get<PaginatedResponse<CustomerProductionRequest>>('/crm/customer-production-requests', { params, signal }).then((response) => response.data),
  show: (id: string) =>
    client.get<{ data: CustomerProductionRequest }>(`/crm/customer-production-requests/${id}`).then((response) => response.data.data),
  plan: (id: string, data: { planned_start: string; planned_end: string; minimum_workers?: number; maximum_workers?: number }) =>
    client.post<{ data: CustomerProductionRequest }>(`/crm/customer-production-requests/${id}/plan`, data).then((response) => response.data.data),
  offer: (id: string, data: { offered_ready_date: string; notes?: string }) =>
    client.post<{ data: CustomerProductionRequest }>(`/crm/customer-production-requests/${id}/offer`, data).then((response) => response.data.data),
  decline: (id: string, reason: string) =>
    client.post<{ data: CustomerProductionRequest }>(`/crm/customer-production-requests/${id}/decline`, { reason }).then((response) => response.data.data),
};
