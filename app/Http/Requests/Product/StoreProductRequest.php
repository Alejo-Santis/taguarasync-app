<?php

namespace App\Http\Requests\Product;

use App\Support\PresentationQuantity;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreProductRequest extends FormRequest
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
            'laboratory_id' => [
                'required',
                'integer',
                Rule::exists('laboratories', 'id')->where('tenant_id', $tenantId),
            ],
            'product_category_id' => [
                'required',
                'integer',
                Rule::exists('product_categories', 'id')->where('tenant_id', $tenantId),
            ],
            'active_ingredient_id' => ['nullable', 'integer', Rule::exists('active_ingredients', 'id')],
            'minimum_unit_id' => ['required', 'integer', Rule::exists('product_units', 'id')->where('is_active', true)],
            'internal_code' => [
                'required',
                'string',
                'max:60',
                Rule::unique('products', 'internal_code')->where('tenant_id', $tenantId),
            ],
            'barcode' => ['nullable', 'string', 'max:120'],
            'commercial_name' => ['required', 'string', 'max:260'],
            'generic_name' => ['nullable', 'string', 'max:260'],
            'cum' => ['nullable', 'string', 'max:80'],
            'health_registration' => ['nullable', 'string', 'max:120'],
            'pharmaceutical_form' => ['nullable', 'string', 'max:120'],
            'concentration' => ['nullable', 'string', 'max:120'],
            'purchase_price' => ['required', 'integer', 'min:0'],
            'sale_price' => ['required', 'integer', 'min:0'],
            'minimum_stock' => ['required', 'integer', 'min:0'],
            'regulated_price' => ['nullable', 'integer', 'min:0'],
            'tax_rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'requires_invima_registration' => ['required', 'boolean'],
            'is_controlled' => ['required', 'boolean'],
            'control_level' => ['nullable', 'integer', 'min:1', 'max:5', Rule::requiredIf($this->boolean('is_controlled'))],
            'status' => ['required', 'string', Rule::in(['active', 'inactive', 'discontinued'])],
            'notes' => ['nullable', 'string', 'max:1000'],
            'presentations' => ['required', 'array', 'min:1', 'max:8'],
            'presentations.*.unit_id' => ['required', 'integer', Rule::exists('product_units', 'id')->where('is_active', true)],
            'presentations.*.name' => ['required', 'string', 'max:160', 'distinct:strict'],
            'presentations.*.barcode' => ['nullable', 'string', 'max:120'],
            'presentations.*.minimum_unit_quantity' => ['required', 'integer', 'min:1'],
            'presentations.*.sale_price' => ['nullable', 'integer', 'min:0'],
            'presentations.*.is_default' => ['required', 'boolean'],
            'presentations.*.is_active' => ['required', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'laboratory_id' => 'laboratorio',
            'product_category_id' => 'categoria',
            'active_ingredient_id' => 'principio activo',
            'minimum_unit_id' => 'unidad minima',
            'internal_code' => 'codigo interno',
            'commercial_name' => 'nombre comercial',
            'sale_price' => 'precio de venta',
            'presentations.*.unit_id' => 'unidad de presentacion',
            'presentations.*.minimum_unit_quantity' => 'cantidad en unidad minima',
            'presentations.*.is_default' => 'presentacion principal',
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $defaultPresentations = collect($this->input('presentations', []))
                    ->filter(fn ($presentation) => filter_var($presentation['is_default'] ?? false, FILTER_VALIDATE_BOOLEAN))
                    ->count();

                if ($defaultPresentations !== 1) {
                    $validator->errors()->add('presentations', 'Debe existir exactamente una presentacion principal.');
                }

                foreach ($this->input('presentations', []) as $index => $presentation) {
                    $name = $presentation['name'] ?? '';
                    $quantity = $presentation['minimum_unit_quantity'] ?? null;

                    if ($name === '' || ! is_numeric($quantity)) {
                        continue;
                    }

                    $expected = PresentationQuantity::expectedFromName($name);

                    if ($expected !== null && (int) $quantity !== $expected) {
                        $validator->errors()->add(
                            "presentations.{$index}.minimum_unit_quantity",
                            "La cantidad minima ({$quantity}) no coincide con lo que indica el nombre \"{$name}\" (se esperaba {$expected})."
                        );
                    }
                }
            },
        ];
    }
}
