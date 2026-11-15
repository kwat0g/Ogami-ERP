<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Requests;

use App\Common\Concerns\ResolvesHashIds;
use App\Modules\Inventory\Enums\WarehouseZoneType;
use App\Modules\Inventory\Models\Warehouse;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreWarehouseZoneRequest extends FormRequest
{
    use ResolvesHashIds;

    public function authorize(): bool
    {
        return $this->user()?->hasPermission('inventory.warehouse.manage') ?? false;
    }

    protected function hashIdFields(): array
    {
        return ['warehouse_id' => Warehouse::class];
    }

    public function rules(): array
    {
        $capacityStatuses = ['unverified', 'estimated', 'rated'];
        if (! $this->isMethod('POST')) {
            $capacityStatuses[] = 'derived';
        }

        return [
            'warehouse_id' => [$this->isMethod('POST') ? 'required' : 'sometimes', 'integer', 'exists:warehouses,id'],
            'name'         => ['required', 'string', 'min:2', 'max:50'],
            'code'         => ['required', 'string', 'min:1', 'max:10', 'regex:/^[A-Z0-9-]+$/'],
            'zone_type'    => ['required', Rule::in(WarehouseZoneType::values())],
            'capacity_kg'  => ['sometimes', 'nullable', 'numeric', 'decimal:0,2', 'gt:0', 'max:99999999.99'],
            'capacity_status' => ['sometimes', 'string', Rule::in($capacityStatuses)],
        ];
    }

    public function messages(): array
    {
        return [
            'code.regex' => 'Zone code must use uppercase letters, digits, or hyphens only.',
        ];
    }
}
