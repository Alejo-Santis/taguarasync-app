<?php

namespace App\Http\Requests\Customers;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCustomerRequest extends FormRequest
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
            'identification_type_code' => ['required', 'string', Rule::exists('dian_identification_types', 'code')],
            'identification_number' => [
                'required', 'string', 'max:30',
                Rule::unique('customers', 'identification_number')
                    ->where('tenant_id', $tenantId)
                    ->where('identification_type_code', $this->identification_type_code)
                    ->whereNull('deleted_at'),
            ],
            'merchant_registration' => ['nullable', 'string', 'max:30'],
            'first_name' => ['nullable', 'string', 'max:120'],
            'last_name' => ['nullable', 'string', 'max:120'],
            'business_name' => ['nullable', 'string', 'max:220'],
            'organization_type_code' => ['nullable', 'string', Rule::exists('dian_organization_types', 'code')],
            'regime_type_code' => ['nullable', 'string', Rule::exists('dian_regime_types', 'code')],
            'email' => ['nullable', 'email', 'max:180'],
            'phone' => ['nullable', 'string', 'max:40'],
            'address' => ['nullable', 'string', 'max:500'],
            'municipality_code' => ['nullable', 'string', Rule::exists('dian_municipalities', 'code')],
            'price_list_id' => ['nullable', 'integer', Rule::exists('price_lists', 'id')->where('tenant_id', $tenantId)],
            'is_active' => ['boolean'],
            'notes' => ['nullable', 'string'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'identification_type_code' => 'tipo de documento',
            'identification_number' => 'número de documento',
            'merchant_registration' => 'registro mercantil',
            'first_name' => 'nombre',
            'last_name' => 'apellido',
            'business_name' => 'razón social',
            'organization_type_code' => 'tipo de organización',
            'regime_type_code' => 'régimen',
            'email' => 'correo electrónico',
            'phone' => 'teléfono',
            'municipality_code' => 'municipio',
        ];
    }
}
