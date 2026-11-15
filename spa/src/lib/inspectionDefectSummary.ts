import type { InspectionDefectSummary } from '@/types/quality';

export function inspectionDefectQuantityIsValid(
  quantity: string,
  sourceQuantity: string,
  unit: string,
  allowZero = false,
): boolean {
  if (!/^\d+(?:\.\d{1,3})?$/.test(quantity)) return false;
  const value = Number(quantity);
  const isPieces = unit.trim().toUpperCase() === 'PCS';

  return Number.isFinite(value) && (allowZero ? value >= 0 : value > 0) && value <= Number(sourceQuantity)
    && (!isPieces || Number.isSafeInteger(value));
}

export function inspectionDefectSummaryMatches(summary: InspectionDefectSummary | null, quantity: string): boolean {
  return Boolean(summary && Number(summary.affected_quantity) === Number(quantity)
    && summary.description.trim() && summary.photos.length > 0);
}
