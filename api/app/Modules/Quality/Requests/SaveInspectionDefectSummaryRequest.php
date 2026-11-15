<?php

declare(strict_types=1);

namespace App\Modules\Quality\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveInspectionDefectSummaryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->is_active && $this->user()->hasPermission('quality.inspections.manage');
    }

    public function rules(): array
    {
        return [
            'category' => ['required', Rule::in(['surface_damage', 'contamination', 'dimensional', 'material', 'packaging', 'other'])],
            'other_category' => ['required_if:category,other', 'nullable', 'string', 'max:100'],
            'severity' => ['required', Rule::in(['minor', 'major', 'critical'])],
            'affected_quantity' => ['required', 'numeric', 'decimal:0,3', 'gt:0', 'max:999999999999.999'],
            'description' => ['required', 'string', 'min:1', 'max:2000'],
            'photos' => ['sometimes', 'array', 'max:5'],
            'photos.*' => ['required', 'image', 'mimes:jpg,jpeg,png', 'max:10240'],
            'remove_photo_ids' => ['sometimes', 'array', 'max:5'],
            'remove_photo_ids.*' => ['required', 'string', 'distinct'],
        ];
    }
}
