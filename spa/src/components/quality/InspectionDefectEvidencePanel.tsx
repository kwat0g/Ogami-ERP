import { useQuery } from '@tanstack/react-query';
import { defectEvidenceApi } from '@/api/quality/defectEvidence';
import { Button } from '@/components/ui/Button';
import { Chip } from '@/components/ui/Chip';

/** Older incoming inspections used one evidence record per defect. Keep those records visible for audit. */
export function InspectionDefectEvidencePanel({
  inspectionId,
  receivedQuantity,
}: {
  inspectionId: string;
  receivedQuantity?: string | null;
}) {
  const evidenceQuery = useQuery({
    queryKey: ['quality', 'inspections', inspectionId, 'defect-evidence'],
    queryFn: () => defectEvidenceApi.list(inspectionId),
    enabled: Boolean(inspectionId),
  });

  return (
    <section aria-label="Historical defect evidence" className="min-w-0 rounded-md border border-default bg-canvas lg:col-span-2">
      <div className="border-b border-default px-4 py-3">
        <h2 className="text-sm font-medium text-primary">Historical defect evidence</h2>
        <p className="mt-1 text-xs text-muted">Earlier individual defect records are retained for audit. New inspections use one shared defect summary.</p>
      </div>
      <div className="space-y-4 px-4 py-4">
        {evidenceQuery.isLoading && <p role="status" className="text-sm text-muted">Loading historical defect evidence…</p>}
        {evidenceQuery.isError && <div role="alert" className="flex flex-wrap items-center gap-2 text-sm text-danger-fg">
          <span>Historical defect evidence could not be loaded.</span>
          <Button type="button" size="sm" onClick={() => void evidenceQuery.refetch()}>Retry</Button>
        </div>}
        {(evidenceQuery.data ?? []).length > 0 && <ul className="divide-y divide-default">
          {(evidenceQuery.data ?? []).map((item) => <li key={item.id} className="grid gap-3 py-3 sm:grid-cols-[minmax(0,1fr)_12rem]">
            <div className="min-w-0 space-y-1 text-sm">
              <div className="flex flex-wrap items-center gap-2">
                <p className="font-medium text-primary">{item.category_label}</p>
                <Chip variant={item.severity === 'critical' ? 'danger' : item.severity === 'major' ? 'warning' : 'info'}>{item.severity_label}</Chip>
              </div>
              <p className="text-muted">Affected quantity: <span className="font-mono tabular-nums text-primary">{item.affected_quantity}</span>{receivedQuantity ? ` / ${receivedQuantity} received` : ''}</p>
              {item.grn_item?.item_code && <p className="text-xs text-muted">GRN item: {item.grn_item.item_code}{item.grn_item.item_name ? ` — ${item.grn_item.item_name}` : ''}</p>}
              {item.notes && <p className="whitespace-pre-line break-words text-sm">{item.notes}</p>}
              {item.created_by && <p className="text-xs text-muted">Added by {item.created_by.name}</p>}
            </div>
            <img src={item.photo_url} alt={`Historical defect evidence photo: ${item.category_label}`} loading="lazy" className="max-h-44 w-full rounded-md border border-default bg-subtle object-contain" />
          </li>)}
        </ul>}
        {!evidenceQuery.isLoading && !evidenceQuery.isError && (evidenceQuery.data ?? []).length === 0 && <p className="text-sm text-muted">No earlier defect records.</p>}
      </div>
    </section>
  );
}
