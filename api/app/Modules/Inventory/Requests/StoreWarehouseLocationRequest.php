<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Requests;

use App\Common\Concerns\ResolvesHashIds;
use App\Modules\Inventory\Models\WarehouseZone;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreWarehouseLocationRequest extends FormRequest
{
    use ResolvesHashIds;

    public function authorize(): bool
    {
        return $this->user()?->hasPermission('inventory.warehouse.manage') ?? false;
    }

    protected function hashIdFields(): array
    {
        return ['zone_id' => WarehouseZone::class];
    }

    public function rules(): array
    {
        $id = $this->route('location')?->id;
        return [
            'zone_id'   => [$this->isMethod('POST') ? 'required' : 'sometimes', 'integer', 'exists:warehouse_zones,id'],
            'code'      => ['required', 'string', 'min:1', 'max:20', 'regex:/^[A-Z0-9-]+$/', Rule::unique('warehouse_locations', 'code')->ignore($id)],
            'rack'      => ['nullable', 'string', 'max:10'],
            'bin'       => ['nullable', 'string', 'max:10'],
            'capacity_kg' => ['sometimes', 'nullable', 'numeric', 'decimal:0,2', 'gt:0', 'max:99999999.99'],
            'capacity_status' => ['sometimes', 'string', Rule::in(['unverified', 'estimated', 'rated'])],
            'is_active' => ['nullable', 'boolean'],
            'is_blocked' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'code.regex' => 'Location code must use uppercase letters, digits, or hyphens only.',
            'code.unique' => 'A location with this code already exists.',
        ];
    }
}
