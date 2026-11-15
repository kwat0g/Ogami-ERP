<?php

declare(strict_types=1);

namespace App\Modules\B2B\Requests\Customer;

use Illuminate\Foundation\Http\FormRequest;

class StoreCustomerProductionRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'request_key' => ['required', 'uuid'],
            'product_id' => ['required', 'string'],
            'total_needed_quantity' => ['required', 'integer', 'min:1'],
            'requested_need_by_date' => ['required', 'date', 'after_or_equal:today'],
            'customer_notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
