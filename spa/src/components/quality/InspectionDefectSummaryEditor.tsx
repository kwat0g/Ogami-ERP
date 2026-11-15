import { useEffect, useMemo, useRef, useState } from 'react';
import { isAxiosError } from 'axios';
import { defectSummaryApi, type SaveInspectionDefectSummaryData } from '@/api/quality/defectSummary';
import { Button } from '@/components/ui/Button';
import { Chip } from '@/components/ui/Chip';
import { FileInput } from '@/components/ui/FileInput';
import { Input } from '@/components/ui/Input';
import { Select } from '@/components/ui/Select';
import { Textarea } from '@/components/ui/Textarea';
import { inspectionDefectQuantityIsValid, inspectionDefectSummaryMatches } from '@/lib/inspectionDefectSummary';
import type {
  InspectionDefectCategory,
  InspectionDefectSeverity,
  InspectionDefectSummary,
} from '@/types/quality';

const categories: Array<[InspectionDefectCategory, string]> = [
  ['surface_damage', 'Surface damage'],
  ['contamination', 'Contamination'],
  ['dimensional', 'Dimensional'],
  ['material', 'Material'],
  ['packaging', 'Packaging'],
  ['other', 'Other'],
];
const maxPhotos = 5;
const maxPhotoBytes = 10 * 1024 * 1024;

function apiErrorMessage(error: unknown): string {
  if (isAxiosError<{ message?: string; errors?: Record<string, string[]> }>(error)) {
    return error.response?.data?.message
      ?? Object.values(error.response?.data?.errors ?? {})[0]?.[0]
      ?? 'The defect summary could not be saved. Your entries are retained.';
  }

  return error instanceof Error
    ? error.message
    : 'The defect summary could not be saved. Your entries are retained.';
}

export function InspectionDefectSummaryEditor({
  inspectionId,
  sourceQuantity,
  unit,
  quantity,
  onQuantityChange,
  canManage,
  onSaved,
}: {
  inspectionId: string;
  sourceQuantity: string;
  unit: string;
  quantity: string;
  onQuantityChange: (quantity: string) => void;
  canManage: boolean;
  onSaved: (summary: InspectionDefectSummary | null) => void;
}) {
  const [summary, setSummary] = useState<InspectionDefectSummary | null>(null);
  const [category, setCategory] = useState<InspectionDefectCategory>('surface_damage');
  const [otherCategory, setOtherCategory] = useState('');
  const [severity, setSeverity] = useState<InspectionDefectSeverity>('minor');
  const [description, setDescription] = useState('');
  const [files, setFiles] = useState<File[]>([]);
  const [removedPhotoIds, setRemovedPhotoIds] = useState<string[]>([]);
  const [fileError, setFileError] = useState('');
  const [saveError, setSaveError] = useState('');
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [loadError, setLoadError] = useState(false);
  const quantityEdited = useRef(false);

  const load = async () => {
    setLoading(true);
    setLoadError(false);
    try {
      const saved = await defectSummaryApi.show(inspectionId);
      setSummary(saved);
      if (saved) onSaved(saved);
      if (saved) {
        setCategory(saved.category);
        setOtherCategory(saved.other_category ?? '');
        setSeverity(saved.severity);
        setDescription(saved.description);
        setRemovedPhotoIds([]);
        setFiles([]);
        if (!quantityEdited.current && (quantity.trim() === '' || Number(quantity) === 0)) {
          onQuantityChange(saved.affected_quantity);
        }
      }
    } catch {
      setLoadError(true);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    void load();
    // The inspection changes by key in all parent panels; the initial quantity is used only to hydrate a saved draft.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [inspectionId]);

  const isPositive = quantity !== '' && Number(quantity) > 0;
  const validQuantity = inspectionDefectQuantityIsValid(quantity, sourceQuantity, unit, true);
  const visiblePhotos = useMemo(
    () => (summary?.photos ?? []).filter(photo => !removedPhotoIds.includes(photo.id)),
    [summary?.photos, removedPhotoIds],
  );
  const previews = useMemo(() => files.map(file => {
    try {
      return { file, url: typeof URL.createObjectURL === 'function' ? URL.createObjectURL(file) : null };
    } catch {
      return { file, url: null };
    }
  }), [files]);
  useEffect(() => () => {
    previews.forEach(preview => {
      if (preview.url) URL.revokeObjectURL(preview.url);
    });
  }, [previews]);
  const resultingPhotoCount = visiblePhotos.length + files.length;
  const ready = canManage && !loading && !saving && isPositive && validQuantity
    && description.trim().length > 0 && description.length <= 2000
    && (category !== 'other' || otherCategory.trim().length > 0)
    && resultingPhotoCount > 0 && resultingPhotoCount <= maxPhotos;
  const summaryMatchesForm = Boolean(summary
    && inspectionDefectSummaryMatches(summary, quantity)
    && summary.category === category
    && summary.other_category === (category === 'other' ? otherCategory.trim() || null : null)
    && summary.severity === severity
    && summary.description === description.trim()
    && files.length === 0
    && removedPhotoIds.length === 0
    && visiblePhotos.length === summary.photos.length);
  useEffect(() => {
    if (summary && summaryMatchesForm) onSaved(summary);
  }, [summary, summaryMatchesForm, onSaved]);
  const quantityError = quantity !== '' && !validQuantity
    ? unit.trim().toUpperCase() === 'PCS'
      ? `Enter 0 or a whole number up to ${sourceQuantity} ${unit}.`
      : `Enter 0 or a quantity up to ${sourceQuantity} ${unit} with no more than 3 decimal places.`
    : undefined;
  const remainingPhotoSlots = maxPhotos - resultingPhotoCount;

  function markUnsaved() {
    setSaveError('');
    onSaved(null);
  }

  async function save() {
    if (!ready) return;
    setSaving(true);
    setSaveError('');
    try {
      const saved = await defectSummaryApi.save(inspectionId, {
        category,
        ...(category === 'other' ? { other_category: otherCategory.trim() } : {}),
        severity,
        affected_quantity: quantity,
        description: description.trim(),
        photos: files,
        removePhotoIds: removedPhotoIds,
      } satisfies SaveInspectionDefectSummaryData);
      setSummary(saved);
      setFiles([]);
      setRemovedPhotoIds([]);
      onSaved(saved);
    } catch (error) {
      setSaveError(apiErrorMessage(error));
    } finally {
      setSaving(false);
    }
  }

  function selectPhotos(fileList: FileList | null) {
    if (!fileList) return;
    const selected = Array.from(fileList);
    const invalid = selected.find(file => !['image/jpeg', 'image/png'].includes(file.type) || file.size > maxPhotoBytes);
    if (invalid) {
      setFileError('Choose JPG or PNG photos no larger than 10 MB each.');
      return;
    }
    const available = maxPhotos - visiblePhotos.length - files.length;
    if (selected.length > available) {
      setFileError(`A defect summary can have no more than ${maxPhotos} photos.`);
      return;
    }
    setFiles(current => [...current, ...selected]);
    setFileError('');
    markUnsaved();
  }

  function removeSavedPhoto(id: string) {
    setRemovedPhotoIds(current => [...current, id]);
    setFileError('');
    markUnsaved();
  }

  function removeNewPhoto(file: File) {
    setFiles(current => current.filter(item => item !== file));
    setFileError('');
    markUnsaved();
  }

  return (
    <section aria-label="Defect summary" className="space-y-4 border-t border-default pt-4">
      <div className="flex flex-wrap items-start justify-between gap-2">
        <div className="min-w-0">
          <h3 className="text-subsection font-semibold text-primary">Defect summary</h3>
          <p className="mt-1 max-w-3xl text-sm text-muted">
            One shared record covers the affected items. For mixed categories, choose Other and describe the mix.
          </p>
        </div>
        <Chip variant={quantity === '' ? 'neutral' : !validQuantity ? 'danger' : !isPositive ? 'neutral' : loading ? 'neutral' : summaryMatchesForm ? 'success' : 'warning'}>
          {quantity === '' ? 'Enter quantity' : !validQuantity ? 'Check quantity' : !isPositive ? 'No evidence needed' : loading ? 'Loading evidence' : summaryMatchesForm ? 'Summary saved' : 'Evidence required'}
        </Chip>
      </div>

      <div className="grid gap-3 rounded-md border border-default bg-surface p-3 sm:grid-cols-2 sm:items-center">
        <Input
          label="Affected quantity"
          type="number"
          min={0}
          max={Number(sourceQuantity)}
          step={unit.trim().toUpperCase() === 'PCS' ? 1 : '0.001'}
          fieldSize="lg"
          suffix={unit}
          value={quantity}
          disabled={!canManage || saving}
          onChange={event => {
            quantityEdited.current = true;
            onQuantityChange(event.target.value);
            markUnsaved();
          }}
          helper={`Maximum ${sourceQuantity} ${unit}. Enter 0 if no items are affected.`}
          error={quantityError}
        />
        <p role="status" className="text-sm text-secondary">
          {quantity === ''
            ? 'Enter the total affected amount. Set it to 0 when no defects were found.'
            : !validQuantity
              ? 'Correct the affected quantity before saving or submitting this inspection.'
              : isPositive
                ? `Add one description and photo evidence for all ${quantity} ${unit} affected.`
                : 'No description or photos are needed when the affected quantity is 0.'}
        </p>
      </div>

      {quantity === '' && <p role="status" className="text-sm text-muted">Enter an affected quantity to continue.</p>}
      {isPositive && (
        <div className="space-y-4">
          {loading && <p role="status" className="text-sm text-muted">Loading saved defect summary…</p>}
          {loadError && <div role="alert" className="flex flex-wrap items-center gap-2 text-sm text-danger-fg">
            <span>Saved defect summary could not be loaded.</span>
            <Button type="button" size="sm" variant="secondary" onClick={() => void load()}>Retry</Button>
          </div>}

          <section aria-label="Defect details" className="space-y-3">
            <div>
              <h4 className="text-sm font-semibold text-primary">Describe the shared defect</h4>
              <p className="mt-1 text-sm text-muted">Choose the highest severity found. If defects differ by category, select Other and describe the mix.</p>
            </div>
            <div className="grid gap-3 sm:grid-cols-2">
              <Select label="Defect category" fieldSize="lg" value={category} disabled={!canManage || saving || loading} onChange={event => { setCategory(event.target.value as InspectionDefectCategory); markUnsaved(); }}>
                {categories.map(([value, label]) => <option key={value} value={value}>{label}</option>)}
              </Select>
              <Select label="Severity" fieldSize="lg" value={severity} disabled={!canManage || saving || loading} onChange={event => { setSeverity(event.target.value as InspectionDefectSeverity); markUnsaved(); }}>
                <option value="minor">Minor</option>
                <option value="major">Major</option>
                <option value="critical">Critical</option>
              </Select>
            </div>
            {category === 'other' && <Input label="Other defect category" required maxLength={100} fieldSize="lg" value={otherCategory} disabled={!canManage || saving || loading} onChange={event => { setOtherCategory(event.target.value); markUnsaved(); }} />}
            <Textarea
              label="Shared description"
              required
              maxLength={2000}
              rows={3}
              value={description}
              disabled={!canManage || saving || loading}
              onChange={event => { setDescription(event.target.value); markUnsaved(); }}
              helper="Describe what happened to the affected items as a group."
              className="min-h-24"
            />
          </section>

          <section aria-label="Photo evidence" className="space-y-3 rounded-md border border-default bg-surface p-3 sm:p-4">
            <div className="flex flex-wrap items-center justify-between gap-2">
              <div>
                <h4 className="text-sm font-semibold text-primary">Photo evidence</h4>
                <p className="mt-1 text-sm text-muted">Add clear photos that show the shared defect.</p>
              </div>
              <Chip variant={resultingPhotoCount > 0 ? 'success' : 'warning'}>{resultingPhotoCount} of {maxPhotos} photos</Chip>
            </div>
            <FileInput
              label="Defect photos"
              type="file"
              accept=".jpg,.jpeg,.png,image/jpeg,image/png"
              multiple
              disabled={!canManage || saving || loading || resultingPhotoCount >= maxPhotos}
              onChange={event => {
                selectPhotos(event.target.files);
                event.target.value = '';
              }}
              helper={remainingPhotoSlots > 0
                ? `JPG or PNG, up to 10 MB each. ${remainingPhotoSlots} ${remainingPhotoSlots === 1 ? 'slot' : 'slots'} remaining.`
                : `Maximum ${maxPhotos} photos reached. Remove a photo to replace it.`}
            />
            {resultingPhotoCount === 0 && <p role="status" className="text-sm text-warning-fg">At least one photo is required before you can save.</p>}
            {fileError && <p role="alert" className="text-sm text-danger-fg">{fileError}</p>}
            {(visiblePhotos.length > 0 || files.length > 0) && <ul aria-label="Attached defect photos" className="grid grid-cols-2 gap-3 sm:grid-cols-3">
              {visiblePhotos.map(photo => <li key={photo.id} className="min-w-0 space-y-2">
                <div className="aspect-[4/3] overflow-hidden rounded-md border border-default bg-canvas">
                  <img src={photo.file_url} alt={`Defect proof: ${photo.file_name}`} loading="lazy" decoding="async" className="h-full w-full object-contain" />
                </div>
                <div className="flex min-w-0 items-center justify-between gap-2">
                  <span className="min-w-0 truncate text-sm text-secondary" title={photo.file_name}>{photo.file_name}</span>
                  {canManage && <Button type="button" size="touch" variant="secondary" className="shrink-0" disabled={saving || loading} aria-label={`Remove ${photo.file_name}`} onClick={() => removeSavedPhoto(photo.id)}>Remove</Button>}
                </div>
              </li>)}
              {previews.map(({ file, url }, index) => <li key={`${file.name}-${file.lastModified}-${index}`} className="min-w-0 space-y-2">
                <div className="aspect-[4/3] overflow-hidden rounded-md border border-default bg-canvas">
                  {url && <img src={url} alt={`Selected defect proof: ${file.name}`} decoding="async" className="h-full w-full object-contain" />}
                </div>
                <div className="flex min-w-0 items-center justify-between gap-2">
                  <span className="min-w-0 truncate text-sm text-secondary" title={file.name}>{file.name}</span>
                  <Button type="button" size="touch" variant="secondary" className="shrink-0" disabled={saving || loading} aria-label={`Remove ${file.name}`} onClick={() => removeNewPhoto(file)}>Remove</Button>
                </div>
              </li>)}
            </ul>}
          </section>

          <div className="flex flex-col-reverse gap-3 border-t border-default pt-3 sm:flex-row sm:items-center sm:justify-between">
            <p role="status" className="min-w-0 text-sm text-muted">
              {saveError
                ? 'Your entries are still here. Correct the issue and try saving again.'
                : saving
                  ? 'Saving the shared summary…'
                  : summaryMatchesForm
                    ? 'Summary saved. You can submit the inspection.'
                    : !validQuantity
                      ? 'Correct the affected quantity before saving.'
                      : description.trim() && resultingPhotoCount > 0 && (category !== 'other' || otherCategory.trim())
                        ? 'Save your changes before submitting the inspection.'
                        : `Add ${[
                          !description.trim() ? 'a shared description' : '',
                          category === 'other' && !otherCategory.trim() ? 'the other category' : '',
                          resultingPhotoCount === 0 ? 'at least one photo' : '',
                        ].filter(Boolean).join(' and ')} before submitting.`}
            </p>
            {canManage && <Button type="button" variant="secondary" loading={saving} disabled={!ready} onClick={() => void save()}>Save defect summary</Button>}
          </div>
          {saveError && <p role="alert" className="text-sm text-danger-fg">{saveError}</p>}
        </div>
      )}
    </section>
  );
}
