<?php

declare(strict_types=1);

namespace App\Modules\Quality\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

class InspectionDefectSummaryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->hash_id,
            'category' => $this->category,
            'category_label' => $this->category === 'other' && $this->other_category
                ? $this->other_category
                : Str::headline((string) $this->category),
            'severity' => $this->severity,
            'severity_label' => Str::headline((string) $this->severity),
            'affected_quantity' => (string) $this->affected_quantity,
            'description' => $this->description,
            'photos' => $this->whenLoaded('photos', fn () => $this->photos->map(fn ($photo) => [
                'id' => $photo->hash_id,
                'file_name' => $photo->file_name,
                'mime_type' => $photo->mime_type,
                'file_size' => $photo->file_size,
                'file_url' => "/api/v1/quality/defect-summary-photos/{$photo->hash_id}/file",
                'created_by' => $photo->relationLoaded('uploader') && $photo->uploader ? [
                    'id' => $photo->uploader->hash_id,
                    'name' => $photo->uploader->name,
                ] : null,
                'created_at' => $photo->created_at?->toISOString(),
            ])->values()),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
