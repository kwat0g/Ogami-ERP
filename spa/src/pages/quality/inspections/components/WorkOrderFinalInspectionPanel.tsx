import { useCallback, useEffect, useRef, useState } from 'react';
import { Link } from 'react-router-dom';
import type { AxiosError } from 'axios';
import { inspectionsApi } from '@/api/quality/inspections';
import { Panel } from '@/components/ui/Panel';
import { Select } from '@/components/ui/Select';
import { ProgressBar } from '@/components/ui/ProgressBar';
import { ChainHeader } from '@/components/chain/ChainHeader';
import { formatDate } from '@/lib/formatDate';
import { Button } from '@/components/ui/Button';
import { Checkbox } from '@/components/ui/Checkbox';
import { Input } from '@/components/ui/Input';
import { Textarea } from '@/components/ui/Textarea';
import { InspectionDefectSummaryEditor } from '@/components/quality/InspectionDefectSummaryEditor';
import { inspectionDefectSummaryMatches } from '@/lib/inspectionDefectSummary';
import { DraftRestoreBanner } from '@/components/ui/DraftRestoreBanner';
import { useFormDraftAutosave } from '@/hooks/useFormDraftAutosave';
import { usePendingProductionSubmission } from '@/hooks/usePendingProductionSubmission';
import { useUnsavedChangesGuard } from '@/hooks/useUnsavedChangesGuard';
import { useAuthStore } from '@/stores/authStore';
import type {
  FinalInspectionEvidence,
  FinalInspectionSubmission,
  WorkOrderFinalInspection,
} from '@/types/quality-final';

const numeric = (row: {
  evaluation_mode?: string;
  parameter_type: string | null;
  tolerance_min: unknown;
  tolerance_max: unknown;
}) =>
  row.evaluation_mode === 'numeric' ||
  (row.parameter_type === 'dimensional' &&
    (row.tolerance_min != null || row.tolerance_max != null));
export function WorkOrderFinalInspectionPanel({
  group,
  canManage,
  canReview,
  onUpdated,
}: {
  group: WorkOrderFinalInspection;
  canManage: boolean;
  canReview: boolean;
  onUpdated?: (data: WorkOrderFinalInspection) => void;
}) {
  const [current, setCurrent] = useState(group);
  const [batchId, setBatchId] = useState(
    group.evidence_page?.inspection_id ?? group.batches[0]?.id ?? '',
  );
  const batch = current.batches.find((row) => row.id === batchId);
  const confirmedChecks =
    batch?.counted_capture?.version === 3 &&
    batch.counted_capture.evidence_method === 'confirmed_checks';
  const [count, setCount] = useState(
    String(batch?.defective_quantity ?? batch?.sample_defect_count ?? 0),
  );
  const [summary, setSummary] = useState(batch?.defect_summary ?? null);
  const [passAllBatchId, setPassAllBatchId] = useState<string | null>(null);
  const [readingValues, setReadingValues] = useState<Record<string, string>>({});
  const [readingPage, setReadingPage] = useState(1);
  const [confirmed, setConfirmed] = useState(false);
  const [dirty, setDirty] = useState(false);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState('');
  const [remarks, setRemarks] = useState('');
  const [feedback, setFeedback] = useState('');
  const [replaceOpen, setReplaceOpen] = useState(false);
  const [replaceReason, setReplaceReason] = useState('');
  const actor = useAuthStore((state) => state.user?.id) ?? 'anonymous';
  const methodVersion = batch?.counted_capture?.version ?? 2;
  const draftKey = `final-qc:${group.work_order_id}:v${methodVersion}:${confirmedChecks ? 'confirmed-checks' : 'readings'}:${batchId}`;
  const pending = usePendingProductionSubmission<{
    action: 'submit' | 'review';
    body:
      | FinalInspectionSubmission
      | { manifest: WorkOrderFinalInspection['manifest']; remarks: string };
  }>(`final-qc:${actor}:${group.work_order_id}:v${methodVersion}:${confirmedChecks ? 'confirmed-checks' : 'readings'}`);
  const quantity = batch?.batch_quantity ?? 0;
  const editable = canManage && batch != null && ['draft', 'in_progress'].includes(batch.status);
  const sizes = (batch?.measurements ?? []).filter(numeric);
  const defectCount = /^\d+$/.test(count) ? Number(count) : NaN;
  const passAllSelected = passAllBatchId === batchId && defectCount === 0;
  const countValid =
    Number.isSafeInteger(defectCount) && defectCount >= 0 && defectCount <= quantity;
  const canSave = countValid;
  const readingStart = (readingPage - 1) * 50;
  const readingNumbers = sizes.length
    ? Array.from(
        { length: Math.min(50, Math.max(0, quantity - readingStart)) },
        (_, i) => readingStart + i + 1,
      )
    : [];
  const valueFor = (piece: number, requirement: string) =>
    readingValues[`${piece}:${requirement}`] ??
    current.evidence_page?.pieces
      .find((row) => row.piece_number === piece)
      ?.readings.find((row) => row.requirement_id === requirement)?.measured_value ??
    '';
  const waiting = current.batches.filter((row) => row.status === 'awaiting_review');
  const drafts = current.batches.filter((row) => ['draft', 'in_progress'].includes(row.status));
  const missingReadings = current.batches.reduce(
    (sum, row) => sum + (row.counted_capture?.version === 3 ? 0 : row.missing_readings ?? 0),
    0,
  );
  const blocked = busy || Boolean(pending.pending);
  const otherDraftsReady = drafts
    .filter((row) => row.id !== batchId)
    .every((row) => {
      const defects = row.defective_quantity ?? row.sample_defect_count ?? 0;
      return defects === 0 || inspectionDefectSummaryMatches(row.defect_summary ?? null, String(defects));
    });
  const canSubmit =
    editable &&
    confirmed &&
    countValid &&
    missingReadings === 0 &&
    otherDraftsReady &&
    (defectCount === 0 || inspectionDefectSummaryMatches(summary, String(defectCount))) &&
    !blocked &&
    !current.blocker;
  const draftRef = useRef({
    count,
    readingValues,
    readingPage,
  });
  draftRef.current = { count, readingValues, readingPage };
  const getValues = useCallback(() => draftRef.current, []);
  const restoreValues = useCallback((values: Record<string, unknown>) => {
    setPassAllBatchId(null);
    if (typeof values.count === 'string') setCount(values.count);
    if (values.readingValues && typeof values.readingValues === 'object')
      setReadingValues(
        Object.fromEntries(
          Object.entries(values.readingValues).filter(
            (entry): entry is [string, string] => typeof entry[1] === 'string',
          ),
        ),
      );
    if (typeof values.readingPage === 'number') setReadingPage(values.readingPage);
    setConfirmed(false);
    setDirty(true);
  }, []);
  const recovery = useFormDraftAutosave({
    formKey: draftKey,
    userId: actor,
    getValues,
    setValues: restoreValues,
    enabled: dirty && editable,
  });
  useEffect(() => {
    if (!dirty || !editable) return;
    const timer = window.setTimeout(recovery.saveNow, 500);
    return () => window.clearTimeout(timer);
  }, [dirty, editable, count, readingValues, recovery.saveNow]);
  useUnsavedChangesGuard(dirty || Boolean(pending.pending), true);

  const accept = (result: WorkOrderFinalInspection, clear = true) => {
    setCurrent(result);
    onUpdated?.(result);
    setError('');
    if (clear) {
      setDirty(false);
      recovery.discard();
    }
  };
  const message = (cause: unknown) =>
    (cause as AxiosError<{ message?: string }>)?.response?.data?.message ??
    'Save could not be confirmed. Check saved progress before retrying.';
  const evidence = (readingsOnly = false, source = current): FinalInspectionEvidence => ({
    manifest: source.manifest,
    inspection_id: batchId,
    expected_result_version: source.batches.find((row) => row.id === batchId)?.result_version ?? 0,
    defective_quantity: defectCount,
    page: readingPage,
    readings: readingsOnly
      ? readingNumbers.flatMap((piece) =>
          sizes.map((row) => ({
            piece_number: piece,
            requirement_id: row.id,
            measured_value: valueFor(piece, row.id) || null,
          })),
        )
      : [],
  });
  const saveDraft = async () => {
    let source = current;
    if (Object.keys(readingValues).length) {
      source = await inspectionsApi.saveWorkOrderFinalEvidence(
        group.work_order_id,
        evidence(true, source),
      );
      setCurrent(source);
    }
    source = await inspectionsApi.saveWorkOrderFinalEvidence(
      group.work_order_id,
      evidence(false, source),
    );
    return source;
  };
  const save = async () => {
    if (!canSave || !editable || blocked) return;
    setBusy(true);
    setError('');
    try {
      const saved = await saveDraft();
      accept(saved);
      setReadingValues({});
      setFeedback('Progress saved.');
    } catch (cause) {
      setError(message(cause));
    } finally {
      setBusy(false);
    }
  };
  const load = async (nextBatch = batchId, nextReading = readingPage) => {
    if (dirty) {
      setError('Save this page before changing pages or batches.');
      return;
    }
    setBusy(true);
    try {
      const result = await inspectionsApi.getWorkOrderFinalInspection(group.work_order_id, {
        inspection_id: nextBatch,
        page: nextReading,
        per_page: 50,
      });
      accept(result);
      setBatchId(nextBatch);
      setReadingPage(nextReading);
      const next = result.batches.find((row) => row.id === nextBatch);
      setCount(String(next?.defective_quantity ?? next?.sample_defect_count ?? 0));
      setSummary(next?.defect_summary ?? null);
      setReadingValues({});
      setConfirmed(false);
    } catch (cause) {
      setError(message(cause));
    } finally {
      setBusy(false);
    }
  };
  const submit = async (review = false) => {
    if (blocked || (!review && !canSubmit)) return;
    setBusy(true);
    setError('');
    try {
      const source = !review && dirty ? await saveDraft() : current;
      accept(source);
      const body = review
        ? { manifest: source.manifest, remarks }
        : {
            manifest: source.manifest,
            batches: drafts.map((row) => ({
              inspection_id: row.id,
              coverage_confirmed: true,
              inspected_quantity: row.batch_quantity,
              ...(row.counted_capture?.version === 3 ? { checks_confirmed: true as const } : {}),
            })),
          };
      if (
        !pending.remember({
          key: JSON.stringify(source.manifest),
          data: { action: review ? 'review' : 'submit', body },
        })
      )
        return;
      const result = review
        ? await inspectionsApi.reviewWorkOrderFinalInspection(
            group.work_order_id,
            body as { manifest: WorkOrderFinalInspection['manifest']; remarks: string },
          )
        : await inspectionsApi.submitWorkOrderFinalInspection(
            group.work_order_id,
            body as FinalInspectionSubmission,
          );
      accept(result);
      pending.clear();
      setConfirmed(false);
    } catch (cause) {
      const status = (cause as AxiosError)?.response?.status;
      if (status === 422 || status === 403 || status === 409) pending.clear();
      setError(message(cause));
    } finally {
      setBusy(false);
    }
  };
  const reloadSavedTotals = async () => {
    setBusy(true);
    try {
      const result = await inspectionsApi.getWorkOrderFinalInspection(group.work_order_id, {
        inspection_id: batchId,
        page: readingPage,
        per_page: 50,
      });
      setCurrent(result);
      onUpdated?.(result);
      setConfirmed(false);
      setError('Saved totals refreshed. Your typed values remain; check them before saving.');
    } catch (cause) {
      setError(message(cause));
    } finally {
      setBusy(false);
    }
  };
  const reconcile = async () => {
    setBusy(true);
    try {
      const result = await inspectionsApi.getWorkOrderFinalInspection(group.work_order_id);
      const original = pending.pending?.data;
      const ids = original?.body.manifest.map((row) => row.inspection_id) ?? [];
      const settled = ids.every((id) => {
        const row = result.batches.find((item) => item.id === id);
        return (
          row &&
          (original?.action === 'review'
            ? ['passed', 'failed'].includes(row.status)
            : ['awaiting_review', 'passed', 'failed'].includes(row.status))
        );
      });
      if (settled) {
        accept(result);
        pending.clear();
        setConfirmed(false);
      } else
        setError(
          'Result is still unconfirmed. Keep this page and check again; nothing new was submitted.',
        );
    } catch (cause) {
      setError(message(cause));
    } finally {
      setBusy(false);
    }
  };

  const context = current.context;
  const requirements = batch?.measurements ?? [];
  const requirementName = (id?: string | null) =>
    id ? requirements.find((row) => row.id === id)?.parameter_name ?? 'Unknown requirement' : 'Other / not classified';
  const checkCategories = [...new Set(requirements.map((row) => {
    const name = row.parameter_name.toLowerCase();
    if (name.includes('weight')) return 'weight';
    if (row.parameter_type === 'dimensional') return 'size';
    if (row.parameter_type === 'visual') return 'surface';
    if (row.parameter_type === 'functional') return 'functional';
    return row.parameter_name;
  }))];
  const confirmationLabel = `I checked every piece against all listed requirements${checkCategories.length ? `, including ${checkCategories.join(', ')} checks` : ''}`;
  const canReplaceMethod = Boolean(
    current.can_replace_method &&
      batch?.counted_capture?.version === 2 &&
      ['draft', 'in_progress'].includes(batch.status),
  );
  const totalReadings = sizes.length * quantity;
  const savedReadings = Math.max(0, totalReadings - (batch?.missing_readings ?? 0));
  const batchCode =
    batch?.work_order_output?.batch_code ??
    context?.output_batches.find((row) => row.inspection_id === batchId)?.batch_code;
  const dateTime = (value: string | null | undefined) => {
    if (!value || Number.isNaN(Date.parse(value))) return '—';
    return new Intl.DateTimeFormat('en-US', {
      timeZone: context?.timezone ?? 'Asia/Manila',
      month: 'short',
      day: 'numeric',
      year: 'numeric',
      hour: 'numeric',
      minute: '2-digit',
      hour12: true,
    }).format(new Date(value));
  };
  const replaceMethod = async () => {
    if (!replaceReason.trim() || !canReplaceMethod || blocked) return;
    setBusy(true);
    setError('');
    try {
      const result = await inspectionsApi.replaceOutgoingEvidenceMethod(group.work_order_id, {
        manifest: current.manifest,
        reason: replaceReason.trim(),
      });
      const replacement = result.batches.find((row) => row.counted_capture?.version === 3);
      const nextId = result.evidence_page?.inspection_id ?? replacement?.id ?? batchId;
      accept(result);
      setBatchId(nextId);
      const next = result.batches.find((row) => row.id === nextId);
      setCount(String(next?.defective_quantity ?? next?.sample_defect_count ?? 0));
      setSummary(next?.defect_summary ?? null);
      setReadingValues({});
      setConfirmed(false);
      setReplaceOpen(false);
      setReplaceReason('');
    } catch (cause) {
      setError(message(cause));
    } finally {
      setBusy(false);
    }
  };

  return (
    <div className="space-y-5">
      <Panel title="Record flow">
        <ChainHeader
          steps={[
            {
              key: 'finished',
              label: 'Production finished',
              state: 'done',
              date: context?.production_finished_at ?? undefined,
            },
            {
              key: 'inspection',
              label: 'Inspection',
              state: current.state === 'checking' ? 'active' : 'done',
            },
            {
              key: 'review',
              label: 'Checker review',
              state:
                current.state === 'checking'
                  ? 'pending'
                  : current.state === 'awaiting_review'
                    ? 'active'
                    : 'done',
            },
            {
              key: 'release',
              label: current.state === 'failed' ? 'Defects on hold' : 'Quality released',
              state:
                current.state === 'released'
                  ? 'done'
                  : current.state === 'failed'
                    ? 'rejected'
                    : 'pending',
            },
          ]}
        />
      </Panel>
      <div className="grid items-start gap-5 lg:grid-cols-3">
        <div className="min-w-0 space-y-5 lg:col-span-2">
          <Panel title="Inspection details">
            <dl className="grid grid-cols-2 gap-x-5 gap-y-3 text-sm sm:grid-cols-3">
              <div>
                <dt className="text-xs text-muted">Stage</dt>
                <dd className="mt-1 font-medium">Outgoing · final WO inspection</dd>
              </div>
              <div>
                <dt className="text-xs text-muted">Produced quantity</dt>
                <dd className="mt-1 font-mono">{current.produced_quantity.toLocaleString()} pieces</dd>
              </div>
              <div>
                <dt className="text-xs text-muted">{current.repaired_accepted_quantity ? 'Originally defective' : 'Defective pieces · saved'}</dt>
                <dd className="mt-1 font-mono">
                  {current.defective_quantity.toLocaleString()}
                </dd>
              </div>
              <div>
                <dt className="text-xs text-muted">{current.repaired_accepted_quantity ? 'Passed initially' : 'Without reported defects'}</dt>
                <dd className="mt-1 font-mono">
                  {Math.max(0, current.produced_quantity - current.defective_quantity).toLocaleString()} pieces
                </dd>
              </div>
              <div>
                <dt className="text-xs text-muted">Approved for release</dt>
                <dd className="mt-1 font-mono">
                  {current.accepted_quantity.toLocaleString()} pieces
                </dd>
              </div>
              {Boolean(current.repaired_accepted_quantity) && (
                <div>
                  <dt className="text-xs text-muted">Repaired · QC approved</dt>
                  <dd className="mt-1 font-mono">{current.repaired_accepted_quantity?.toLocaleString()} pieces</dd>
                </div>
              )}
              {confirmedChecks ? (
                <div>
                  <dt className="text-xs text-muted">Confirmed checks</dt>
                  <dd className="mt-1">{batch?.coverage_confirmed_at ? 'Confirmed' : 'Not confirmed'}</dd>
                </div>
              ) : (
                <div>
                  <dt className="text-xs text-muted">Size readings · saved</dt>
                  <dd className="mt-1 font-mono">
                    {savedReadings.toLocaleString()} / {totalReadings.toLocaleString()}
                  </dd>
                </div>
              )}
            </dl>
          </Panel>
          <section
            aria-label="Final work order inspection"
            className="min-w-0 rounded-md border border-default bg-canvas"
          >
            <div className="border-b border-default px-4 py-3">
              <h2 className="text-sm font-medium">Inspection checklist</h2>
              <p className="mt-1 text-xs text-muted">
                {current.state === 'released'
                  ? `${current.accepted_quantity.toLocaleString()} pieces approved for release${current.repaired_accepted_quantity ? `, including ${current.repaired_accepted_quantity.toLocaleString()} repaired pieces` : ''}.`
                  : `Check all ${quantity.toLocaleString()} pieces in this batch. Stock stays held until QC review.`}
              </p>
            </div>
            <div className="space-y-4 p-4">
              {(error || current.blocker || pending.storageError) && (
                <div>
                  <p role="alert" className="text-sm text-danger-fg">
                    {error || current.blocker || pending.storageError}
                  </p>
                  {error && !pending.pending && (
                    <Button
                      variant="secondary"
                      disabled={busy}
                      onClick={() => void reloadSavedTotals()}
                    >
                      Reload saved totals
                    </Button>
                  )}
                </div>
              )}
              {pending.pending && (
                <div className="rounded-md border border-default p-3">
                  <p className="text-sm">
                    Submission result unconfirmed. Check saved result before submitting again.
                  </p>
                  <Button variant="secondary" disabled={busy} onClick={() => void reconcile()}>
                    Check saved result
                  </Button>
                </div>
              )}
              {recovery.hasDraft && editable && (
                <DraftRestoreBanner
                  ageMs={recovery.draftAge}
                  onRestore={recovery.restore}
                  onDiscard={recovery.discard}
                />
              )}
              {feedback && !error && !dirty && (
                <p role="status" className="text-sm text-success-fg">
                  {feedback}
                </p>
              )}
              {current.batches.length > 1 && (
                <Select
                  label="Output batch"
                  value={batchId}
                  disabled={blocked || dirty}
                  helper={dirty ? 'Save this batch before switching.' : undefined}
                  onChange={(event) => void load(event.target.value, 1)}
                >
                  {current.batches.map((row) => (
                    <option key={row.id} value={row.id}>
                      {row.work_order_output?.batch_code ?? row.inspection_number} ·{' '}
                      {row.batch_quantity} pieces
                    </option>
                  ))}
                </Select>
              )}
              {canReplaceMethod && !replaceOpen && (
                <Button variant="secondary" onClick={() => setReplaceOpen(true)}>
                  Replace untouched draft with confirmed checks
                </Button>
              )}
              {canReplaceMethod && replaceOpen && (
                <div className="space-y-3 rounded-md border border-default p-3">
                  <Textarea
                    label="Reason for evidence method change"
                    value={replaceReason}
                    maxLength={1000}
                    onChange={(event) => setReplaceReason(event.target.value)}
                  />
                  <div className="flex flex-wrap gap-2">
                    <Button
                      variant="primary"
                      loading={busy}
                      disabled={!replaceReason.trim() || blocked}
                      onClick={() => void replaceMethod()}
                    >
                      Confirm replacement
                    </Button>
                    <Button variant="secondary" disabled={busy} onClick={() => setReplaceOpen(false)}>
                      Keep numeric method
                    </Button>
                  </div>
                </div>
              )}
              <details open={confirmedChecks} className="border-b border-default pb-3">
                <summary className="cursor-pointer text-sm font-medium focus-visible:outline-accent">
                  Inspection requirements · {requirements.length} checks
                  {confirmedChecks ? ' · reference targets' : ''}
                </summary>
                <div className="mt-3 max-h-64 overflow-auto">
                  <table className="w-full text-left text-xs">
                    <thead className="bg-surface text-muted">
                      <tr>
                        <th scope="col" className="p-2 font-medium">
                          Check
                        </th>
                        <th scope="col" className="p-2 font-medium">
                          PASS requirement
                        </th>
                      </tr>
                    </thead>
                    <tbody>
                      {requirements.map((row) => (
                        <tr key={row.id} className="border-b border-subtle">
                          <th scope="row" className="p-2 font-medium">
                            {row.parameter_name}
                          </th>
                          <td className="p-2">
                            {confirmedChecks ? (
                              <>
                                {numeric(row) && (
                                  <span>
                                    Target {row.nominal_value ?? '—'} {row.unit_of_measure ?? ''}; range{' '}
                                    {row.tolerance_min ?? 'No minimum'}–{row.tolerance_max ?? 'No maximum'}{' '}
                                    {row.unit_of_measure ?? ''}
                                  </span>
                                )}
                                {row.acceptance_criterion && (
                                  <span className="block">{row.acceptance_criterion}</span>
                                )}
                                {row.is_critical && <span className="ml-2 font-medium text-danger-fg">Critical</span>}
                              </>
                            ) : numeric(row) ? (
                              `${row.tolerance_min ?? 'No minimum'} – ${row.tolerance_max ?? 'No maximum'} ${row.unit_of_measure ?? ''}`
                            ) : (
                              row.acceptance_criterion ?? 'Refer to the pinned inspection specification.'
                            )}
                          </td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
              </details>
              {editable && (
                <fieldset disabled={blocked} className="min-w-0 space-y-4">
                  <legend className="mb-2 text-sm font-medium">{confirmedChecks ? 'Defect assessment' : 'Visual checks and defects'}</legend>
                  <p className="text-xs text-muted">
                    Record one affected quantity and a shared description with photo evidence for the batch.
                  </p>
                  <InspectionDefectSummaryEditor
                    key={batchId}
                    inspectionId={batchId}
                    sourceQuantity={String(quantity)}
                    unit="PCS"
                    quantity={count}
                    onQuantityChange={(value) => {
                      setCount(value);
                      setPassAllBatchId(null);
                      setDirty(true);
                      setConfirmed(false);
                    }}
                    canManage={editable && !blocked}
                    onSaved={(saved) => {
                      setSummary(saved);
                      if (!saved) {
                        setDirty(true);
                        setConfirmed(false);
                      }
                    }}
                  />
                  <div className="flex flex-wrap items-center gap-3">
                    <Button
                      variant="secondary"
                      aria-pressed={passAllSelected}
                      className={passAllSelected ? 'border-success bg-success-bg text-success-fg hover:bg-success-bg/80' : ''}
                      onClick={() => {
                        setPassAllBatchId(batchId);
                        setCount('0');
                        setSummary(null);
                        setDirty(true);
                        setConfirmed(false);
                      }}
                    >
                      PASS ALL
                    </Button>
                    {countValid && defectCount > 0 && (
                      <p className="text-sm">
                        {defectCount} defective · {quantity - defectCount} without reported defects.
                      </p>
                    )}
                  </div>
                  <div className="space-y-2">
                    <p className="text-xs text-muted">
                      {confirmedChecks
                        ? 'The summary covers the defective group; confirm every listed requirement before submitting.'
                        : 'PASS ALL records zero defects; required size readings still apply.'}
                    </p>
                    <Button
                      variant="secondary"
                      loading={busy}
                      disabled={!canSave}
                      onClick={() => void save()}
                    >
                      Save draft
                    </Button>
                  </div>
                  {!confirmedChecks && sizes.length > 0 && (
                    <details open className="border-t border-default pt-4">
                      <summary className="cursor-pointer text-sm font-medium focus-visible:outline-accent">
                        Size readings · {(batch?.missing_readings ?? 0).toLocaleString()} remaining
                      </summary>
                      <p className="my-3 text-xs text-muted">
                        {sizes.length} size checks × {quantity.toLocaleString()} pieces ={' '}
                        {totalReadings.toLocaleString()} required readings. Enter actual
                        measurements; PASS/FAIL is calculated.
                      </p>
                      <div className="mb-3 space-y-2">
                        <div className="flex flex-wrap justify-between gap-2 text-xs">
                          <span>
                            {savedReadings.toLocaleString()} of {totalReadings.toLocaleString()}{' '}
                            readings saved
                          </span>
                          <span className="text-muted">
                            Pieces {readingStart + 1}–{Math.min(quantity, readingStart + 50)}
                          </span>
                        </div>
                        <ProgressBar
                          value={totalReadings ? (savedReadings / totalReadings) * 100 : 0}
                          ariaLabel="Size readings saved"
                        />
                      </div>
                      <div className="max-h-96 overflow-auto rounded-md border border-default">
                        <table className="w-full text-left text-xs">
                          <thead className="sticky top-0 z-10 bg-surface">
                            <tr>
                              <th scope="col" className="whitespace-nowrap p-3 font-medium">
                                Piece
                              </th>
                              {sizes.map((row) => (
                                <th key={row.id} scope="col" className="min-w-36 p-3 font-medium">
                                  {row.parameter_name}
                                  <span className="mt-1 block font-normal text-muted">
                                    {row.tolerance_min ?? '—'}–{row.tolerance_max ?? '—'}{' '}
                                    {row.unit_of_measure ?? ''}
                                  </span>
                                </th>
                              ))}
                            </tr>
                          </thead>
                          <tbody>
                            {readingNumbers.map((piece) => (
                              <tr key={piece} className="border-t border-subtle">
                                <th scope="row" className="p-3 font-mono font-normal">
                                  {piece}
                                </th>
                                {sizes.map((row) => (
                                  <td key={row.id} className="p-2">
                                    <Input
                                      aria-label={`${row.parameter_name} · piece ${piece}`}
                                      inputMode="decimal"
                                      fieldSize="sm"
                                      value={valueFor(piece, row.id)}
                                      onChange={(event) => {
                                        setReadingValues((previous) => ({
                                          ...previous,
                                          [`${piece}:${row.id}`]: event.target.value,
                                        }));
                                        setDirty(true);
                                        setConfirmed(false);
                                        setFeedback('');
                                      }}
                                    />
                                  </td>
                                ))}
                              </tr>
                            ))}
                          </tbody>
                        </table>
                      </div>
                      <div className="mt-3 flex flex-wrap items-center gap-2">
                        <Button variant="secondary" loading={busy} onClick={() => void save()}>
                          Save readings
                        </Button>
                        <Button
                          variant="secondary"
                          disabled={dirty || readingPage === 1}
                          onClick={() => void load(batchId, readingPage - 1)}
                        >
                          Previous readings
                        </Button>
                        <span className="text-xs text-muted">
                          Page {readingPage} of {Math.ceil(quantity / 50)}
                        </span>
                        <Button
                          variant="secondary"
                          disabled={dirty || readingPage * 50 >= quantity}
                          onClick={() => void load(batchId, readingPage + 1)}
                        >
                          Next readings
                        </Button>
                      </div>
                      {dirty && (
                        <p className="mt-2 text-xs text-muted">
                          Save this page before moving to the next 50 pieces.
                        </p>
                      )}
                    </details>
                  )}
                  <h3 className="border-t border-default pt-4 text-sm font-medium">
                    Submit for QC review
                  </h3>
                  <Checkbox
                    label={confirmedChecks
                      ? confirmationLabel
                      : `I checked all ${drafts.reduce((sum, row) => sum + row.batch_quantity, 0)} pieces`}
                    checked={confirmed}
                    onChange={(event) => setConfirmed(event.target.checked)}
                  />
                  {missingReadings > 0 && (
                    <p className="text-sm text-warning-fg">
                      Save {missingReadings.toLocaleString()} remaining size readings before
                      submitting.
                    </p>
                  )}
                  <div>
                    <Button
                      variant="primary"
                      loading={busy}
                      disabled={!canSubmit}
                      onClick={() => void submit()}
                    >
                      Submit inspection
                    </Button>
                  </div>
                </fieldset>
              )}
              {!editable && batch?.defect_summary && (
                <section aria-label="Saved defect summary" className="space-y-3 rounded-md border border-default p-3">
                  <h3 className="text-sm font-medium">Defect summary</h3>
                  <p className="text-sm">
                    {batch.defect_summary.affected_quantity} PCS · {batch.defect_summary.category_label} · {batch.defect_summary.severity_label}
                  </p>
                  <p className="whitespace-pre-wrap text-sm">{batch.defect_summary.description}</p>
                  <ul className="grid gap-3 sm:grid-cols-2">
                    {batch.defect_summary.photos.map((photo) => (
                      <li key={photo.id}>
                        <img src={photo.file_url} alt={`Defect proof: ${photo.file_name}`} loading="lazy" className="h-32 w-full rounded-md border border-default bg-subtle object-contain" />
                        <p className="mt-1 truncate text-xs text-muted">{photo.file_name}</p>
                      </li>
                    ))}
                  </ul>
                </section>
              )}
              {!editable && (batch?.defective_quantity ?? batch?.sample_defect_count ?? 0) > 0 && (current.defect_page?.pieces.length ?? 0) > 0 && (
                <details className="rounded-md border border-default p-3">
                  <summary className="cursor-pointer text-sm font-medium">Historical per-piece notes</summary>
                  <div className="mt-2 space-y-2">
                    {current.defect_page?.pieces.map((row) => (
                      <div key={row.piece_number} className="border-t border-subtle py-2 text-sm">
                        <p className="font-medium">Piece {row.piece_number}</p>
                        <p>{row.problem_description}</p>
                        <p className="text-xs text-muted">Primary failed check: {requirementName(row.requirement_id)}</p>
                      </div>
                    ))}
                  </div>
                </details>
              )}
              {!editable && confirmedChecks && (
                <section aria-label="Saved confirmed-check evidence" className="space-y-3">
                  <h3 className="text-sm font-medium">Saved inspection evidence</h3>
                  <p>
                    Confirmed all listed requirements
                    {batch?.inspector?.name ? ` by ${batch.inspector.name}` : ''}
                    {(batch?.checks_confirmed_at ?? batch?.coverage_confirmed_at)
                      ? ` · ${dateTime(batch.checks_confirmed_at ?? batch.coverage_confirmed_at)}`
                      : ''}
                  </p>
                  {batch?.proposed_result && (
                    <p className="text-sm font-medium">
                      Submitted result: {batch.proposed_result.toUpperCase()}
                    </p>
                  )}
                </section>
              )}
              {waiting.length > 0 && (
                <div className="space-y-3">
                  <p className="text-sm">Independent QC review required.</p>
                  {canReview && waiting.every((row) => row.can_review) && drafts.length === 0 && (
                    <>
                      <Textarea
                        label="Review remarks"
                        value={remarks}
                        maxLength={2000}
                        onChange={(event) => setRemarks(event.target.value)}
                      />
                      <Button
                        variant="primary"
                        loading={busy}
                        disabled={blocked || Boolean(current.blocker)}
                        onClick={() => void submit(true)}
                      >
                        Approve inspection results
                      </Button>
                    </>
                  )}
                </div>
              )}
              {current.batches
                .filter((row) => row.status === 'failed')
                .map((row) => (
                  <p key={row.id} className="text-sm text-warning-fg">
                    {current.state === 'released' ? 'Original inspection: ' : ''}{row.inspection_number}: FAIL ·{' '}
                    {row.sample_defect_count ?? row.defective_quantity ?? 0} defective.{' '}
                    {row.ncr && (
                      <Link className="underline" to={`/quality/ncrs/${row.ncr.id}`}>
                        {row.sorting_approved_at ? 'View NCR' : 'Segregate defects for release approval'}
                      </Link>
                    )}
                  </p>
                ))}
              {current.state === 'released' && (
                <p className="text-sm text-success-fg">
                  Quality released. Warehouse can prepare approved quantities.
                </p>
              )}
            </div>
          </section>
        </div>
        <aside
          aria-label="Inspection context"
          className="min-w-0 space-y-5 lg:col-start-3 lg:row-start-1"
        >
          <Panel title="Linked records">
            <dl className="space-y-4 text-sm">
              <div>
                <dt className="text-xs text-muted">Product</dt>
                <dd className="mt-1">
                  {context?.product
                    ? `${context.product.part_number} — ${context.product.name}`
                    : batch?.product
                      ? `${batch.product.part_number} — ${batch.product.name}`
                      : '—'}
                </dd>
              </div>
              <div>
                <dt className="text-xs text-muted">Source work order</dt>
                <dd className="mt-1 font-mono">{current.wo_number ?? '—'}</dd>
                <dd className="mt-1 text-xs text-muted">
                  Production finished · {current.produced_quantity.toLocaleString()} pieces
                </dd>
              </div>
              <div>
                <dt className="text-xs text-muted">Production batch / output batch</dt>
                <dd className="mt-1 break-words font-mono">{context?.batch_number ?? '—'}</dd>
                <dd className="mt-1 break-words font-mono">{batchCode ?? '—'}</dd>
              </div>
              <div>
                <dt className="text-xs text-muted">Machine / mold</dt>
                <dd className="mt-1 font-mono">
                  {context?.machine?.code ?? '—'} / {context?.mold?.code ?? '—'}
                </dd>
                <dd className="mt-1 text-xs text-muted">
                  {[context?.machine?.name, context?.mold?.name].filter(Boolean).join(' · ')}
                </dd>
              </div>
              <div>
                <dt className="text-xs text-muted">Inspection specification</dt>
                <dd className="mt-1">
                  {batch?.spec?.version != null
                    ? `v${batch.spec.version} · pinned for this batch`
                    : 'Pinned inspection requirements'}
                </dd>
              </div>
            </dl>
          </Panel>
          <details className="rounded-md border border-default bg-canvas p-4">
            <summary className="cursor-pointer text-sm font-medium focus-visible:outline-accent">
              Order and production details
            </summary>
            <div className="mt-4 space-y-4">
              {context?.sales_order && (
                <dl className="grid gap-3 text-sm sm:grid-cols-2">
                  <div>
                    <dt className="text-xs text-muted">Sales order</dt>
                    <dd className="mt-1 break-words font-mono">{context.sales_order.so_number}</dd>
                  </div>
                  <div>
                    <dt className="text-xs text-muted">Requested / current delivery</dt>
                    <dd className="mt-1">
                      {formatDate(context.sales_order.requested_delivery_date)} /{' '}
                      {formatDate(context.sales_order.current_delivery_date)}
                    </dd>
                  </div>
                </dl>
              )}
              <dl className="grid gap-3 text-sm sm:grid-cols-2">
                <div>
                  <dt className="text-xs text-muted">Production started</dt>
                  <dd className="mt-1">{dateTime(context?.production_started_at)}</dd>
                </div>
                <div>
                  <dt className="text-xs text-muted">Production finished</dt>
                  <dd className="mt-1">{dateTime(context?.production_finished_at)}</dd>
                </div>
                <div>
                  <dt className="text-xs text-muted">Production due</dt>
                  <dd className="mt-1">{dateTime(context?.production_due_at)}</dd>
                </div>
                <div>
                  <dt className="text-xs text-muted">Plant time zone</dt>
                  <dd className="mt-1">{context?.timezone ?? 'Asia/Manila'}</dd>
                </div>
              </dl>
            </div>
          </details>
          <Panel title="Inspection history">
            <dl className="grid grid-cols-2 gap-4 text-sm">
              <div>
                <dt className="text-xs text-muted">Results recorded by</dt>
                <dd className="mt-1">{batch?.inspector?.name ?? 'Not recorded'}</dd>
              </div>
              <div>
                <dt className="text-xs text-muted">Reviewed by</dt>
                <dd className="mt-1">{batch?.reviewer?.name ?? 'Not reviewed'}</dd>
              </div>
              <div>
                <dt className="text-xs text-muted">Created</dt>
                <dd className="mt-1">{dateTime(batch?.created_at)}</dd>
              </div>
              <div>
                <dt className="text-xs text-muted">Inspection started</dt>
                <dd className="mt-1">{dateTime(batch?.started_at)}</dd>
              </div>
              <div>
                <dt className="text-xs text-muted">Completed</dt>
                <dd className="mt-1">{dateTime(batch?.completed_at)}</dd>
              </div>
              <div>
                <dt className="text-xs text-muted">Reviewed</dt>
                <dd className="mt-1">{dateTime(batch?.reviewed_at)}</dd>
              </div>
            </dl>
            {batch?.notes && (
              <p className="mt-4 border-t border-default pt-3 text-sm">{batch.notes}</p>
            )}
          </Panel>
        </aside>
      </div>
    </div>
  );
}
