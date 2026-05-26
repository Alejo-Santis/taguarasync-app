<?php

namespace App\Http\Requests\Purchase;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePurchaseOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $tenantId = $this->user()?->tenant_id;

        return [
            'supplier_id' => ['required', 'integer', Rule::exists('suppliers', 'id')->where('tenant_id', $tenantId)],
            'order_number' => [
                'required', 'string', 'max:120',
                Rule::unique('purchase_orders')->where('tenant_id', $tenantId),
            ],
            'order_date' => ['required', 'date'],
            'expected_date' => ['nullable', 'date', 'after_or_equal:order_date'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*.product_id' => ['required', 'integer', Rule::exists('products', 'id')->where('tenant_id', $tenantId)],
            'items.*.product_presentation_id' => ['nullable', 'integer', Rule::exists('product_presentations', 'id')],
            'items.*.description' => ['required', 'string', 'max:260'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.unit_cost' => ['required', 'integer', 'min:0'],
            'items.*.tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ];
    }
}
