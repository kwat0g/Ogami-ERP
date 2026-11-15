import { client } from '../client';
import type { ApiSuccess, PaginatedResponse } from '@/types';
import type { ContributionAgency, GovernmentScheduleVersion, GovernmentTable } from '@/types/payroll';

export interface ImportGovernmentScheduleData {
 source_title: string;
 source_url: string;
 csv: File;
}

export interface ImportGovernmentScheduleResult {
 total: number;
 imported: number;
 skipped: number;
 status: string | null;
 version_number: number | null;
 effective_date: string | null;
 source_file_sha256: string | null;
 idempotent: boolean;
 errors: Array<{ row: number; message: string }>;
 version?: GovernmentScheduleVersion;
}

export interface UpdateGovTableData {
 bracket_min?: string;
 bracket_max?: string;
 ee_amount?: string;
 er_amount?: string;
 ec_amount?: string;
 effective_date?: string;
 is_active?: boolean;
}

export const govTablesApi = {
 options: () => client.get<{ data: { agencies: Array<{ value: ContributionAgency; label: string }> } }>('/admin/gov-tables/options').then((r) => r.data.data),
 versions: (agency: ContributionAgency) =>
  client.get<{ data: GovernmentScheduleVersion[] }>('/admin/gov-tables/versions', { params: { agency } }).then((r) => r.data.data),
 import: (agency: ContributionAgency, data: ImportGovernmentScheduleData) => {
  const form = new FormData();
  form.append('source_title', data.source_title);
  form.append('source_url', data.source_url);
  form.append('csv', data.csv);
  return client.post<{ data: ImportGovernmentScheduleResult }>(`/gov-tables/${agency}/import`, form)
   .then((r) => r.data.data);
 },
 publish: (id: string) =>
  client.post<ApiSuccess<GovernmentScheduleVersion>>(`/admin/gov-tables/versions/${id}/publish`, {
   confirm_completeness: true,
  }).then((r) => r.data.data),
 /**
 * Government tables aren't paginated server-side (small fixed data set per
 * agency) but we still expect the standard PaginatedResponse envelope from
 * the controller. Some installs may have it return a flat list — accept both.
 */
 list: (agency: ContributionAgency) =>
 client
 .get<PaginatedResponse<GovernmentTable> | ApiSuccess<GovernmentTable[]>>('/admin/gov-tables', {
 params: { agency },
 })
 .then((r) => {
 const body = r.data as PaginatedResponse<GovernmentTable> | ApiSuccess<GovernmentTable[]>;
 return Array.isArray((body as PaginatedResponse<GovernmentTable>).data)
 ? ((body as PaginatedResponse<GovernmentTable>).data as GovernmentTable[])
 : [];
 }),
 update: (id: string, data: UpdateGovTableData) =>
 client.put<ApiSuccess<GovernmentTable>>(`/admin/gov-tables/${id}`, data).then((r) => r.data.data),
 deactivate: (id: string) =>
 client.patch<ApiSuccess<GovernmentTable>>(`/admin/gov-tables/${id}/deactivate`).then((r) => r.data.data),
 activate: (id: string) =>
 client.patch<ApiSuccess<GovernmentTable>>(`/admin/gov-tables/${id}/activate`).then((r) => r.data.data),
};
