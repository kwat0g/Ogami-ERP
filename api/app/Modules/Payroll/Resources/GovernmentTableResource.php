<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Modules\Payroll\Models\GovernmentContributionTable
 */
class GovernmentTableResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $scheduleVersion = $this->relationLoaded('scheduleVersion') ? $this->scheduleVersion : null;

        return [
            'id'             => $this->hash_id,
            'agency'         => $this->agency?->value,
            'agency_label'   => $this->agency?->label(),
            'bracket_min'    => $this->bracket_min,
            'bracket_max'    => $this->bracket_max,
            'ee_amount'      => $this->ee_amount,
            'er_amount'      => $this->er_amount,
            'ec_amount'      => $this->ec_amount,
            'effective_date' => optional($this->effective_date)->toDateString(),
            'is_active'      => $this->is_active,
            'schedule_version_id' => $scheduleVersion?->hash_id,
            'schedule_version_number' => $scheduleVersion?->version_number,
            'schedule_version_status' => $scheduleVersion?->status?->value
                ?? ($this->schedule_version_id === null ? 'legacy_unverified' : null),
            'schedule_version_source_title' => $scheduleVersion?->source_title,
            'is_immutable' => $this->schedule_version_id !== null,
            'created_at'     => optional($this->created_at)->toIso8601String(),
            'updated_at'     => optional($this->updated_at)->toIso8601String(),
        ];
    }
}
