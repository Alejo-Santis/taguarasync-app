<?php

namespace App\Http\Requests\Sales;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCreditNoteRequest extends FormRequest
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
        return [
            'discrepancy_reason_code' => ['required', 'string', Rule::exists('dian_discrepancy_reasons', 'code')->where('applies_to', 'credit_note')],
            'notes' => ['nullable', 'string', 'max:500'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.sale_item_id' => ['required', 'integer', Rule::exists('sale_items', 'id')->where('sale_id', $this->route('sale')?->id)],
            'items.*.description' => ['required', 'string', 'max:260'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.unit_price' => ['required', 'integer', 'min:0'],
            'items.*.tax_rate' => ['required', 'numeric', 'min:0', 'max:100'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'discrepancy_reason_code' => 'motivo de la nota crédito',
            'items' => 'ítems',
        ];
    }
}
