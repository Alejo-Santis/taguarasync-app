<?php

namespace App\Http\Requests\Inventory;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreOpeningStockRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $tenantId = $this->user()?->tenant_id;

        return [
            'items' => ['required', 'array', 'min:1', 'max:200'],
            'items.*.product_id' => [
                'required',
                'integer',
                Rule::exists('products', 'id')->where('tenant_id', $tenantId),
            ],
            'items.*.product_presentation_id' => [
                'nullable',
                'integer',
                Rule::exists('product_presentations', 'id')->where('tenant_id', $tenantId),
            ],
            'items.*.lot_number' => ['required', 'string', 'max:120'],
            'items.*.expires_on' => ['nullable', 'date'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:1000000'],
            'items.*.unit_cost' => ['required', 'integer', 'min:0', 'max:100000000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'items' => 'lineas de inventario',
            'items.*.product_id' => 'producto',
            'items.*.product_presentation_id' => 'presentacion',
            'items.*.lot_number' => 'numero de lote',
            'items.*.expires_on' => 'fecha de vencimiento',
            'items.*.quantity' => 'cantidad',
            'items.*.unit_cost' => 'costo unitario',
        ];
    }
}
