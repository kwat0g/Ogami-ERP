import { client } from '../client';
import type { ApiSuccess, PaginatedResponse, ListParams } from '@/types';
import type { CreatePayrollAdjustmentData, PayrollAdjustment } from '@/types/payroll';

export interface AdjustmentListParams extends ListParams {
 status?: string;
 type?: string;
 employee_id?: string;
}

export interface UnpaidAbsenceOptions {
 leave_requests: Array<{
  id: string;
  leave_request_no: string;
  leave_type: string | null;
  start_date: string;
  end_date: string;
  days: string;
 }>;
 target_periods: Array<{
  id: string;
  label: string;
  period_start: string;
  period_end: string;
  status: string;
 }>;
}

export const adjustmentsApi = {
 options: () => client.get<{ data: { statuses: Array<{ value: string; label: string }>; types: Array<{ value: string; label: string }> } }>('/payroll-adjustments/options').then((r) => r.data.data),
 unpaidAbsenceOptions: (params: { original_payroll_id: string; leave_request_id?: string }) =>
  client.get<{ data: UnpaidAbsenceOptions }>('/payroll-adjustments/unpaid-absence-options', { params }).then((r) => r.data.data),
 list: (params?: AdjustmentListParams) =>
 client.get<PaginatedResponse<PayrollAdjustment>>('/payroll-adjustments', { params }).then((r) => r.data),
 show: (id: string) =>
 client.get<ApiSuccess<PayrollAdjustment>>(`/payroll-adjustments/${id}`).then((r) => r.data.data),
 create: (data: CreatePayrollAdjustmentData) =>
 client.post<ApiSuccess<PayrollAdjustment>>('/payroll-adjustments', data).then((r) => r.data.data),
 approve: (id: string) =>
 client.patch<ApiSuccess<PayrollAdjustment>>(`/payroll-adjustments/${id}/approve`).then((r) => r.data.data),
 reject: (id: string, remarks: string) =>
 client.patch<ApiSuccess<PayrollAdjustment>>(`/payroll-adjustments/${id}/reject`, { remarks }).then((r) => r.data.data),
};
