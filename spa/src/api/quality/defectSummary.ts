import { client } from '../client';
import type {
  InspectionDefectCategory,
  InspectionDefectSeverity,
  InspectionDefectSummary,
} from '@/types/quality';

export interface SaveInspectionDefectSummaryData {
  category: InspectionDefectCategory;
  other_category?: string;
  severity: InspectionDefectSeverity;
  affected_quantity: string;
  description: string;
  photos: File[];
  removePhotoIds: string[];
}

export const defectSummaryApi = {
  show: (inspectionId: string) =>
    client.get<{ data: InspectionDefectSummary | null }>(`/quality/inspections/${inspectionId}/defect-summary`)
      .then(response => response.data.data),
  save: (inspectionId: string, data: SaveInspectionDefectSummaryData) => {
    const form = new FormData();
    form.append('category', data.category);
    if (data.other_category) form.append('other_category', data.other_category);
    form.append('severity', data.severity);
    form.append('affected_quantity', data.affected_quantity);
    form.append('description', data.description);
    data.photos.forEach(photo => form.append('photos[]', photo));
    data.removePhotoIds.forEach(id => form.append('remove_photo_ids[]', id));
    // PHP parses multipart uploads on POST. Laravel's method override routes this to PUT.
    form.append('_method', 'PUT');

    return client.post<{ data: InspectionDefectSummary }>(`/quality/inspections/${inspectionId}/defect-summary`, form)
      .then(response => response.data.data);
  },
  clear: (inspectionId: string) =>
    client.delete<{ data: null }>(`/quality/inspections/${inspectionId}/defect-summary`)
      .then(response => response.data.data),
};
