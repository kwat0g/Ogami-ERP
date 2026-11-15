import { Chip } from '@/components/ui/Chip';
import type { InspectionDefectSummary } from '@/types/quality';

export function InspectionDefectSummaryDisplay({ summary }: { summary: InspectionDefectSummary }) {
  const severityVariant = summary.severity === 'critical' ? 'danger' : summary.severity === 'major' ? 'warning' : 'info';

  return (
    <section aria-label="Saved defect summary" className="min-w-0 rounded-md border border-default bg-canvas lg:col-span-2">
      <div className="border-b border-default px-4 py-3">
        <h2 className="text-sm font-medium text-primary">Defect summary</h2>
        <p className="mt-1 text-xs text-muted">One shared record for the affected items in this inspection.</p>
      </div>
      <div className="space-y-4 px-4 py-4">
        <div className="flex flex-wrap items-center gap-2">
          <p className="font-medium text-primary">{summary.category_label}</p>
          <Chip variant={severityVariant}>{summary.severity_label}</Chip>
          <span className="text-sm text-muted">Affected quantity: <span className="font-mono tabular-nums text-primary">{summary.affected_quantity}</span></span>
        </div>
        <p className="whitespace-pre-line break-words text-sm text-primary">{summary.description}</p>
        {summary.photos.length > 0 && <ul aria-label="Defect summary photos" className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
          {summary.photos.map((photo) => <li key={photo.id} className="min-w-0 space-y-1">
            <img src={photo.file_url} alt={`Defect summary photo: ${photo.file_name}`} loading="lazy" className="h-36 w-full rounded-md border border-default bg-subtle object-contain" />
            <p className="truncate text-xs text-muted">{photo.file_name}</p>
          </li>)}
        </ul>}
      </div>
    </section>
  );
}
