<?php

declare(strict_types=1);

namespace App\Modules\B2B\Requests\Supplier;

use App\Modules\B2B\Support\SupplierShipmentDatePolicy;
use Illuminate\Foundation\Http\FormRequest;

class ShipmentUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'shipped_date'      => ['nullable', 'date_format:Y-m-d', 'before_or_equal:today'],
            'carrier'           => ['nullable', 'string', 'max:100'],
            'tracking_number'   => ['nullable', 'string', 'max:100'],
            'estimated_arrival' => [
                'nullable',
                'date_format:Y-m-d',
                'after_or_equal:today',
                'before_or_equal:'.SupplierShipmentDatePolicy::latestEstimatedArrivalDate(),
            ],
            'notes'             => ['nullable', 'string', 'max:500'],
            'vehicle'           => ['nullable', 'string', 'max:120'],
            'driver_name'       => ['nullable', 'string', 'max:120'],
            'driver_phone'      => ['nullable', 'string', 'max:40'],
            'plate_number'      => ['nullable', 'string', 'max:32'],
            'items' => ['sometimes', 'array', 'min:1'],
            'items.*.purchase_order_item_id' => ['required_with:items', 'string'],
            'items.*.quantity_shipped' => ['required_with:items', 'numeric', 'gt:0', 'max:9999999999.99', 'regex:/^\d+(\.\d{1,2})?$/'],
        ];
    }
}
