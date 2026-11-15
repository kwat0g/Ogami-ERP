<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Resources;

use App\Modules\Payroll\Models\GovernmentScheduleVersion;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin GovernmentScheduleVersion */
class GovernmentScheduleVersionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->hash_id,
            'agency' => $this->agency?->value,
            'agency_label' => $this->agency?->label(),
            'effective_date' => $this->effective_date?->toDateString(),
            'version_number' => $this->version_number,
            'status' => $this->status?->value,
            'source_title' => $this->source_title,
            'source_url' => $this->source_url,
            'source_file_name' => $this->source_file_name,
            'source_file_sha256' => $this->source_file_sha256,
            'row_count' => $this->row_count,
            'imported_at' => $this->imported_at?->toIso8601String(),
            'importer' => $this->whenLoaded('importer', fn () => [
                'id' => $this->importer?->hash_id,
                'name' => $this->importer?->name,
            ]),
            'published_at' => $this->published_at?->toIso8601String(),
            'publisher' => $this->whenLoaded('publisher', fn () => [
                'id' => $this->publisher?->hash_id,
                'name' => $this->publisher?->name,
            ]),
            'completeness_confirmed' => (bool) $this->completeness_confirmed,
            'can_publish' => $this->status?->value === 'draft'
                && ($this->imported_by === null || (int) $this->imported_by !== (int) $request->user()?->id),
            'superseded_at' => $this->superseded_at?->toIso8601String(),
        ];
    }
}
