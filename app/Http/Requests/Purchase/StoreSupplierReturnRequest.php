<?php

namespace App\Http\Requests\Purchase;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSupplierReturnRequest extends FormRequest
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
            'purchase_receipt_id' => ['nullable', 'integer', Rule::exists('purchase_receipts', 'id')->where('tenant_id', $tenantId)],
            'document_number' => [
                'required', 'string', 'max:120',
                Rule::unique('supplier_returns')->where('tenant_id', $tenantId),
            ],
            'return_date' => ['required', 'date'],
            'reason' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1', 'max:50'],
            'items.*.product_id' => ['required', 'integer', Rule::exists('products', 'id')->where('tenant_id', $tenantId)],
            'items.*.inventory_lot_id' => ['required', 'integer', Rule::exists('inventory_lots', 'id')->where('tenant_id', $tenantId)],
            'items.*.description' => ['required', 'string', 'max:260'],
            'items.*.lot_number' => ['required', 'string', 'max:120'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.unit_cost' => ['required', 'integer', 'min:0'],
            'items.*.tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ];
    }
}
