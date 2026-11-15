<?php

declare(strict_types=1);

namespace App\Modules\B2B\Requests\Customer;

use Illuminate\Foundation\Http\FormRequest;

class StoreCustomerOrderLimitIncreaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('customer_portal') !== null;
    }

    public function rules(): array
    {
        return [
            'product_id' => ['required', 'string'],
            'requested_maximum_quantity' => ['required', 'integer', 'min:1', 'max:999999999'],
            'customer_notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
