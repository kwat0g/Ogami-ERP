<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ImportGovTableRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('payroll.gov_tables.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'csv' => ['required', 'file', 'mimes:csv,txt', 'max:1024'],
            'source_title' => ['required', 'string', 'max:200'],
            'source_url' => ['required', 'url', 'starts_with:https://', 'max:2048'],
        ];
    }
}
