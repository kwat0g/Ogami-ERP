<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PublishGovScheduleVersionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('admin.gov_tables.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'confirm_completeness' => ['required', 'accepted'],
        ];
    }
}
