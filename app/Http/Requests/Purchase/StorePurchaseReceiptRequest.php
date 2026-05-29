<?php

namespace App\Http\Requests\Purchase;

use App\Models\ProductPresentation;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;
use Illuminate\Validation\Validator;

class StorePurchaseReceiptRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $tenantId = $this->user()?->tenant_id;

        return [
            'branch_id' => [
                'required',
                'integer',
                Rule::exists('branches', 'id')->where('tenant_id', $tenantId),
            ],
            'supplier_id' => [
                'required',
                'integer',
                Rule::exists('suppliers', 'id')
                    ->where('tenant_id', $tenantId)
                    ->where('is_active', true),
            ],
            'document_number' => [
                'required',
                'string',
                'max:120',
                Rule::unique('purchase_receipts', 'document_number')
                    ->where('tenant_id', $tenantId)
                    ->where('supplier_id', $this->input('supplier_id')),
            ],
            'document_date' => ['nullable', 'date'],
            'received_at' => ['nullable', 'date'],
            'supplier_cufe' => ['nullable', 'string', 'min:10', 'max:120'],
            'source_file' => ['nullable', File::types(['pdf', 'xml', 'zip', 'jpg', 'jpeg', 'png', 'webp'])->max(8 * 1024)],
            'notes' => ['nullable', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1', 'max:80'],
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
            'items.*.description' => ['required', 'string', 'max:260'],
            'items.*.lot_number' => ['required', 'string', 'max:120'],
            'items.*.expires_on' => ['nullable', 'date'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:1000000'],
            'items.*.unit_cost' => ['required', 'integer', 'min:0', 'max:100000000'],
            'items.*.tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'branch_id' => 'sucursal',
            'supplier_id' => 'proveedor',
            'document_number' => 'numero de documento',
            'document_date' => 'fecha del documento',
            'received_at' => 'fecha de recepcion',
            'source_file' => 'soporte de compra',
            'items' => 'lineas de compra',
            'items.*.product_id' => 'producto',
            'items.*.product_presentation_id' => 'presentacion',
            'items.*.description' => 'descripcion',
            'items.*.lot_number' => 'lote',
            'items.*.expires_on' => 'vencimiento',
            'items.*.quantity' => 'cantidad',
            'items.*.unit_cost' => 'costo unitario',
            'items.*.tax_rate' => 'IVA',
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                collect($this->input('items', []))->each(function (array $item, int $index) use ($validator): void {
                    if (empty($item['product_id']) || empty($item['product_presentation_id'])) {
                        return;
                    }

                    $exists = ProductPresentation::query()
                        ->where('id', $item['product_presentation_id'])
                        ->where('product_id', $item['product_id'])
                        ->exists();

                    if (! $exists) {
                        $validator->errors()->add(
                            "items.{$index}.product_presentation_id",
                            'La presentacion seleccionada no pertenece al producto.'
                        );
                    }
                });
            },
        ];
    }
}
