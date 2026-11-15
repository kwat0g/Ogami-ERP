import { reportMutationError } from '@/lib/formErrors';
import { useEffect, useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { LuPencil, LuEyeOff, LuEye } from '@/lib/icons';
import toast from 'react-hot-toast';
import { govTablesApi, type UpdateGovTableData } from '@/api/admin/gov-tables';
import { Button } from '@/components/ui/Button';
import { Chip } from '@/components/ui/Chip';
import { ConfirmDialog } from '@/components/ui/ConfirmDialog';
import { Input } from '@/components/ui/Input';
import { Modal, ModalFooter } from '@/components/ui/Modal';
import { EmptyState } from '@/components/ui/EmptyState';
import { SkeletonTable } from '@/components/ui/Skeleton';
import { PageHeader } from '@/components/layout/PageHeader';
import { formatDecimal } from '@/lib/formatNumber';
import { formatDate } from '@/lib/formatDate';
import type { ContributionAgency, GovernmentTable } from '@/types/payroll';
import { Td, Th, tableCls, theadTrCls, trCls } from '@/components/ui/table-cells';
import { Tabs } from '@/components/ui/Tabs';
import { useRealtimeList } from '@/hooks/useRealtimeRecord';
import { Checkbox } from '@/components/ui/Checkbox';
import type { GovernmentScheduleVersion } from '@/types/payroll';

const AGENCY_HELP: Record<string, string> = {
 sss: 'Flat peso amounts per bracket. EE = employee share; ER = employer SS/MPF share; EC = employer-only Employees’ Compensation.',
 philhealth: 'Rate-based premium. Basis = clamp(salary, floor, ceiling) × rate.',
 pagibig: 'Rate-based per bracket. Max basis 10,000 → max EE/ER 200.00 each.',
 bir: 'Semi-monthly TRAIN brackets. EE column = fixed_tax, ER column = rate_on_excess.',
};

export default function AdminGovTablesPage() {
 useRealtimeList('government_table', 'visible');
 const [active, setActive] = useState<ContributionAgency | ''>('');
 const { data: options } = useQuery({
 queryKey: ['gov-tables', 'options'],
 queryFn: () => govTablesApi.options(),
 staleTime: 5 * 60 * 1000,
 });
 const agencies = options?.agencies ?? [];
 const selectedAgency = active || agencies[0]?.value || '';

 return (
 <div>
 <PageHeader
 title="Government Tables"
 subtitle="SSS, PhilHealth, Pag-IBIG, BIR — used by the payroll engine for contribution calculations"
 backTo="/payroll/periods"
 backLabel="Payroll"
 />

 <div className="px-5 pt-3">
 <Tabs
 items={agencies.map((a) => ({ key: a.value, label: a.label }))}
 value={selectedAgency}
 onChange={(value) => setActive(value as ContributionAgency)}
 label="Contribution agency"
 />
 </div>

 {selectedAgency && <AgencyTable agency={selectedAgency as ContributionAgency} />}
 </div>
 );
}

function AgencyTable({ agency }: { agency: ContributionAgency }) {
 const qc = useQueryClient();
 const [editing, setEditing] = useState<GovernmentTable | null>(null);
 const [confirmDeactivate, setConfirmDeactivate] = useState<string | null>(null);
 const [confirmActivate, setConfirmActivate] = useState<string | null>(null);

 const { data, isLoading, isError, refetch } = useQuery({
 queryKey: ['gov-tables', agency],
 queryFn: () => govTablesApi.list(agency),
 });

 const applyServerBracket = (updated: GovernmentTable) => {
  qc.setQueryData<GovernmentTable[]>(['gov-tables', agency], (rows) =>
   rows?.map((row) => row.id === updated.id ? updated : row),
  );
 };

 const deactivate = useMutation({
 mutationFn: (id: string) => govTablesApi.deactivate(id),
 onSuccess: (updated) => {
 applyServerBracket(updated);
 toast.success('Bracket deactivated.');
 setConfirmDeactivate(null);
 },
 onError: (error) => reportMutationError(error, 'Failed to deactivate bracket.'),
 });

 const activate = useMutation({
 mutationFn: (id: string) => govTablesApi.activate(id),
 onSuccess: (updated) => {
 applyServerBracket(updated);
 toast.success('Bracket activated.');
 setConfirmActivate(null);
 },
 onError: (error) => reportMutationError(error, 'Failed to activate bracket.'),
 });

 const help = AGENCY_HELP[agency];
 const rateLike = agency === 'philhealth' || agency === 'pagibig';
 const isBir = agency === 'bir';

 const rowStatus = (row: GovernmentTable) => row.schedule_version_status
  ?? (row.is_active ? 'active' : 'inactive');
 const rowStatusLabel = (row: GovernmentTable) => ({
  draft: 'Draft', published: 'Published', superseded: 'Superseded',
  legacy_unverified: 'Legacy unverified', active: 'Active', inactive: 'Inactive',
 }[rowStatus(row)] ?? 'Unknown');

 return (
 <div className="px-5 py-4">
 {help && <p className="text-xs text-muted mb-3">{help}</p>}

 <ScheduleVersionManager agency={agency} />

 {isLoading && <SkeletonTable columns={agency === 'sss' ? 8 : 7} rows={6} />}
 {isError && (
 <EmptyState icon="alert-circle" title="Failed to load brackets"
 action={<Button variant="secondary" onClick={() => refetch()}>Retry</Button>} />
 )}
 {data && data.length === 0 && <EmptyState icon="inbox" title="No brackets seeded for this agency" />}
 {data && data.length > 0 && (
 <div className="border border-default rounded-md overflow-hidden">
 <table className={tableCls}>
 <thead>
 <tr className={theadTrCls}>
 <Th>Min</Th>
 <Th>Max</Th>
 <Th align="right">
 {isBir ? 'Fixed tax' : 'EE share'}
 </Th>
 <Th align="right">
 {isBir ? 'Rate on excess' : 'ER share'}
 </Th>
                 {agency === 'sss' && <Th align="right">EC share</Th>}
 <Th>Effective</Th>
 <Th>Schedule status</Th>
 <Th align="right">Actions</Th>
 </tr>
 </thead>
 <tbody>
 {data.map((row) => (
 <tr key={row.id} className={trCls}>
 <Td mono>{formatDecimal(row.bracket_min)}</Td>
 <Td mono>{formatDecimal(row.bracket_max)}</Td>
 <Td align="right" mono>
 {rateLike ? `${(Number(row.ee_amount) * 100).toFixed(2)}%` : formatDecimal(row.ee_amount)}
 </Td>
 <Td align="right" mono>
 {agency === 'pagibig' || agency === 'philhealth'
 ? `${(Number(row.er_amount) * 100).toFixed(2)}%`
 : isBir
 ? `${(Number(row.er_amount) * 100).toFixed(2)}%`
 : formatDecimal(row.er_amount)}
 </Td>
                 {agency === 'sss' && <Td align="right" mono>{formatDecimal(row.ec_amount)}</Td>}
 <Td mono>{formatDate(row.effective_date)}</Td>
 <Td>
 <Chip variant={row.schedule_version_status === 'published' || (!row.schedule_version_status && row.is_active) ? 'success' : 'neutral'}>
 {rowStatusLabel(row)}
 </Chip>
 </Td>
 <Td align="right" mono>
 <div className="flex items-center justify-end gap-1">
 <Button size="sm" variant="ghost" icon={<LuPencil size={12} />}
 onClick={() => setEditing(row)} disabled={row.is_immutable}
 title={row.is_immutable ? 'Imported schedule rows are immutable. Import a corrected version.' : undefined}>Edit</Button>
 {row.is_immutable ? null : row.is_active ? (
 <Button size="sm" variant="ghost" icon={<LuEyeOff size={12} />}
 onClick={() => setConfirmDeactivate(row.id)}
 disabled={deactivate.isPending}>Deactivate</Button>
 ) : (
 <Button size="sm" variant="ghost" icon={<LuEye size={12} />}
 onClick={() => setConfirmActivate(row.id)}
 disabled={activate.isPending}>Activate</Button>
 )}
 </div>
 </Td>
 </tr>
 ))}
 </tbody>
 </table>
 </div>
 )}

 <EditBracketModal
 bracket={editing}
 onClose={() => setEditing(null)}
 agency={agency}
 />

 <ConfirmDialog
 isOpen={confirmDeactivate !== null}
 onClose={() => setConfirmDeactivate(null)}
 onConfirm={() => { if (confirmDeactivate) deactivate.mutate(confirmDeactivate); }}
 title="Deactivate this bracket?"
 variant="warning"
 confirmLabel="Deactivate"
 pending={deactivate.isPending}
 />
 <ConfirmDialog
 isOpen={confirmActivate !== null}
 onClose={() => setConfirmActivate(null)}
 onConfirm={() => { if (confirmActivate) activate.mutate(confirmActivate); }}
 title="Activate this bracket?"
 variant="primary"
 confirmLabel="Activate"
 pending={activate.isPending}
 />
 </div>
 );
}

function ScheduleVersionManager({ agency }: { agency: ContributionAgency }) {
 const qc = useQueryClient();
 const [sourceTitle, setSourceTitle] = useState('');
 const [sourceUrl, setSourceUrl] = useState('');
 const [csv, setCsv] = useState<File | null>(null);
 const [reviewed, setReviewed] = useState<Record<string, boolean>>({});
 const [importErrors, setImportErrors] = useState<Array<{ row: number; message: string }>>([]);

 const versionsQuery = useQuery({
  queryKey: ['gov-tables', 'versions', agency],
  queryFn: () => govTablesApi.versions(agency),
 });

 const importMutation = useMutation({
  mutationFn: () => {
   if (!csv) throw new Error('Choose a CSV file first.');
   return govTablesApi.import(agency, { source_title: sourceTitle, source_url: sourceUrl, csv });
  },
  onSuccess: async (result) => {
   if (result.errors.length > 0) {
    setImportErrors(result.errors);
    toast.error(`Schedule was not imported. Resolve ${result.errors.length} validation issue${result.errors.length === 1 ? '' : 's'} and retry.`);
    return;
   }
   setImportErrors([]);
   await Promise.all([
    qc.invalidateQueries({ queryKey: ['gov-tables', 'versions', agency] }),
    qc.invalidateQueries({ queryKey: ['gov-tables', agency] }),
   ]);
   toast.success(`Schedule version ${result.version_number} imported as a draft. A different reviewer must publish it.`);
   setCsv(null);
  },
  onError: (error) => reportMutationError(error, 'Failed to import schedule source.'),
 });

 const publishMutation = useMutation({
  mutationFn: (id: string) => govTablesApi.publish(id),
  onSuccess: async () => {
   await Promise.all([
    qc.invalidateQueries({ queryKey: ['gov-tables', 'versions', agency] }),
    qc.invalidateQueries({ queryKey: ['gov-tables', agency] }),
   ]);
   toast.success('Schedule version published. New payroll periods will pin it when its effective date applies.');
  },
  onError: (error) => reportMutationError(error, 'Failed to publish schedule version.'),
 });

 const submitImport = (event: React.FormEvent<HTMLFormElement>) => {
  event.preventDefault();
  if (!csv) {
   toast.error('Choose the schedule CSV file first.');
   return;
  }
  importMutation.mutate();
 };

 return (
  <section className="mb-5 border border-default rounded-md">
   <details>
    <summary className="cursor-pointer px-4 py-3 font-medium text-sm focus-visible:outline focus-visible:outline-2 focus-visible:outline-accent">
     Import or review schedule versions
    </summary>
    <div className="border-t border-default px-4 py-4 space-y-5">
     <form onSubmit={submitImport} className="grid gap-3 md:grid-cols-2">
      <div className="md:col-span-2">
       <p className="text-sm font-medium">Import source schedule</p>
       <p className="text-xs text-muted mt-1">
        Import creates an inactive draft. It becomes available to new payroll runs only after a different authorized reviewer publishes it.
       </p>
      </div>
      <Input label="Source title" value={sourceTitle} maxLength={200} required
       onChange={(event) => { setSourceTitle(event.target.value); setImportErrors([]); }} placeholder="Official contribution schedule" />
      <Input label="HTTPS source URL" type="url" value={sourceUrl} required
       onChange={(event) => { setSourceUrl(event.target.value); setImportErrors([]); }} placeholder="https://agency.gov/schedule" />
      <label className="md:col-span-2 grid gap-1.5 text-sm font-medium">
       <span>Schedule CSV</span>
       <input type="file" accept=".csv,text/csv,text/plain" required={!csv}
        onChange={(event) => { setCsv(event.target.files?.[0] ?? null); setImportErrors([]); }}
        className="block w-full rounded-md border border-default bg-surface px-3 py-2 text-sm text-primary file:mr-3 file:rounded file:border-0 file:bg-subtle file:px-3 file:py-1.5 file:text-sm file:font-medium focus-visible:outline focus-visible:outline-2 focus-visible:outline-accent" />
       <span className="text-xs font-normal text-muted">
        CSV must contain one effective date and non-overlapping brackets. SSS files also need an <code>ec_amount</code> column; other agencies default EC to zero.
       </span>
      </label>
      {importErrors.length > 0 && (
       <div className="md:col-span-2 rounded-md border border-danger bg-danger-subtle p-3" role="alert" aria-live="polite">
        <p className="text-sm font-medium">Schedule was not imported ({importErrors.length} issue{importErrors.length === 1 ? '' : 's'}).</p>
        <ul className="mt-2 max-h-48 overflow-y-auto list-disc pl-5 text-xs text-danger space-y-1">
         {importErrors.map((error, index) => <li key={`${error.row}-${index}`}>Row {error.row}: {error.message}</li>)}
        </ul>
       </div>
      )}
      <div className="md:col-span-2">
       <Button type="submit" variant="primary" loading={importMutation.isPending} disabled={importMutation.isPending}>
        Import draft version
       </Button>
      </div>
     </form>

     <div>
      <div className="mb-2">
       <h2 className="text-sm font-medium">Schedule history</h2>
       <p className="text-xs text-muted mt-1">A payroll period pins the latest published schedule effective on or before its payroll date.</p>
      </div>
      {versionsQuery.isLoading && <p className="text-sm text-muted">Loading schedule history…</p>}
      {versionsQuery.isError && (
       <div className="flex items-center gap-3 text-sm text-danger">
        <span>Schedule history could not be loaded.</span>
        <Button size="sm" variant="secondary" onClick={() => versionsQuery.refetch()}>Retry</Button>
       </div>
      )}
      {versionsQuery.data?.length === 0 && (
       <p className="text-sm text-muted">No versioned schedules yet. Existing rows are marked legacy unverified and cannot qualify a new payroll run.</p>
      )}
      {versionsQuery.data && versionsQuery.data.length > 0 && (
       <div className="overflow-x-auto border border-default rounded-md">
        <table className={tableCls}>
         <thead><tr className={theadTrCls}>
          <Th>Effective</Th><Th>Version</Th><Th>Status</Th><Th>Source and file evidence</Th><Th>Review</Th>
         </tr></thead>
         <tbody>
          {versionsQuery.data.map((version) => (
           <ScheduleVersionRow key={version.id} version={version} reviewed={!!reviewed[version.id]}
            onReviewChange={(checked) => setReviewed((current) => ({ ...current, [version.id]: checked }))}
            onPublish={() => publishMutation.mutate(version.id)}
            publishing={publishMutation.isPending && publishMutation.variables === version.id} />
          ))}
         </tbody>
        </table>
       </div>
      )}
     </div>
    </div>
   </details>
  </section>
 );
}

function ScheduleVersionRow({
 version, reviewed, onReviewChange, onPublish, publishing,
}: {
 version: GovernmentScheduleVersion;
 reviewed: boolean;
 onReviewChange: (checked: boolean) => void;
 onPublish: () => void;
 publishing: boolean;
}) {
 const statusLabel: Record<GovernmentScheduleVersion['status'], string> = {
  draft: 'Draft', published: 'Published', superseded: 'Superseded', legacy_unverified: 'Legacy unverified',
 };

 return (
  <tr className={trCls}>
   <Td mono>{formatDate(version.effective_date)}</Td>
   <Td mono>v{version.version_number} · {version.row_count} rows</Td>
   <Td><Chip variant={version.status === 'published' ? 'success' : 'neutral'}>{statusLabel[version.status]}</Chip></Td>
   <Td>
    <div className="max-w-[34rem] min-w-64">
     <p className="font-medium text-sm">{version.source_title ?? 'Source evidence unavailable'}</p>
     {version.source_url && <a href={version.source_url} target="_blank" rel="noreferrer" className="text-xs text-accent underline underline-offset-2 break-all">{version.source_url}</a>}
     <p className="text-xs text-muted mt-1">{version.source_file_name ?? 'No source file recorded'} · SHA-256</p>
     {version.source_file_sha256
      ? <code className="block break-all text-[11px] text-muted">{version.source_file_sha256}</code>
      : <p className="text-xs text-warning">This legacy schedule has no verified source hash.</p>}
     <p className="text-xs text-muted mt-1">
      Imported by {version.importer?.name ?? 'system'}{version.publisher ? ` · published by ${version.publisher.name}` : ''}
     </p>
    </div>
   </Td>
   <Td>
    {version.status === 'draft' ? (
     <div className="min-w-64 space-y-2">
      <Checkbox checked={reviewed} onChange={(event) => onReviewChange(event.target.checked)}
       label="I compared the full schedule with the cited source and confirmed its completeness." />
      {version.can_publish ? (
       <Button size="sm" variant="primary" onClick={onPublish} loading={publishing}
        disabled={!reviewed || publishing}>Publish version</Button>
      ) : (
       <p className="text-xs text-muted">The importer cannot publish their own draft. Ask another authorized reviewer.</p>
      )}
     </div>
    ) : version.status === 'legacy_unverified' ? (
     <span className="text-xs text-muted">Reimport with source evidence to make this schedule eligible.</span>
    ) : version.status === 'superseded' ? (
     <span className="text-xs text-muted">Kept for payroll snapshot history.</span>
    ) : (
     <span className="text-xs text-muted">Eligible for periods with a matching payroll date.</span>
    )}
   </Td>
  </tr>
 );
}

function EditBracketModal({
 bracket, onClose, agency,
}: { bracket: GovernmentTable | null; onClose: () => void; agency: ContributionAgency }) {
 const qc = useQueryClient();
 const [data, setData] = useState<UpdateGovTableData>({});

 // Reset form when the target bracket changes.
 useEffect(() => { setData({}); }, [bracket?.id]);

 const mutation = useMutation({
 mutationFn: (payload: UpdateGovTableData) => govTablesApi.update(bracket!.id, payload),
 onSuccess: (updated) => {
  qc.setQueryData<GovernmentTable[]>(['gov-tables', agency], (rows) =>
   rows?.map((row) => row.id === updated.id ? updated : row),
  );
 toast.success('Bracket updated.');
 onClose();
 },
 onError: (error) => reportMutationError(error, 'Failed to update bracket.'),
 });

 if (!bracket) return null;

 return (
 <Modal isOpen={!!bracket} onClose={onClose} size="md" title={`Edit ${agency.toUpperCase()} bracket`}>
 <div className="py-3 grid grid-cols-2 gap-3">
 <Input label="Bracket min" defaultValue={bracket.bracket_min}
 onChange={(e) => setData((d) => ({ ...d, bracket_min: e.target.value }))} className="font-mono" />
 <Input label="Bracket max" defaultValue={bracket.bracket_max}
 onChange={(e) => setData((d) => ({ ...d, bracket_max: e.target.value }))} className="font-mono" />
 <Input label={agency === 'bir' ? 'Fixed tax' : 'EE amount'} defaultValue={bracket.ee_amount}
 onChange={(e) => setData((d) => ({ ...d, ee_amount: e.target.value }))} className="font-mono" />
 <Input label={agency === 'bir' ? 'Rate on excess' : 'ER amount'} defaultValue={bracket.er_amount}
 onChange={(e) => setData((d) => ({ ...d, er_amount: e.target.value }))} className="font-mono" />
 {agency === 'sss' && <Input label="EC amount" defaultValue={bracket.ec_amount ?? '0.0000'}
 onChange={(e) => setData((d) => ({ ...d, ec_amount: e.target.value }))} className="font-mono" />}
 <Input label="Effective date" type="date" defaultValue={bracket.effective_date}
 onChange={(e) => setData((d) => ({ ...d, effective_date: e.target.value }))} />
 </div>
 <p className="text-xs text-muted">
 Editing changes will affect future payroll runs immediately. Historical payrolls remain unchanged because they store the raw computed amounts.
 </p>
 <ModalFooter>
 <Button variant="secondary" onClick={onClose} disabled={mutation.isPending}>Cancel</Button>
 <Button variant="primary"
 onClick={() => mutation.mutate(data)}
 disabled={mutation.isPending} loading={mutation.isPending}>
 Save changes
 </Button>
 </ModalFooter>
 </Modal>
 );
}
