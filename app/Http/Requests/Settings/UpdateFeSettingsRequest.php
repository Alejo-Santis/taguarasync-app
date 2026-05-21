<?php

namespace App\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateFeSettingsRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:180'],
            'legal_name' => ['nullable', 'string', 'max:220'],
            'nit' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:180'],
            'phone' => ['nullable', 'string', 'max:40'],
            'address' => ['nullable', 'string', 'max:500'],
            'city' => ['nullable', 'string', 'max:120'],
            'department' => ['nullable', 'string', 'max:120'],
            'identification_type_code' => ['nullable', 'string', Rule::exists('dian_identification_types', 'code')],
            'organization_type_code' => ['nullable', 'string', Rule::exists('dian_organization_types', 'code')],
            'regime_type_code' => ['nullable', 'string', Rule::exists('dian_regime_types', 'code')],
            'fiscal_responsibilities' => ['nullable', 'array'],
            'fiscal_responsibilities.*' => ['string', Rule::exists('dian_fiscal_responsibilities', 'code')],
            'municipality_code' => ['nullable', 'string', Rule::exists('dian_municipalities', 'code')],
            'fe_municipality_api_id' => ['nullable', 'integer', 'min:0'],
            'economic_activity_code' => ['nullable', 'string', 'max:10'],
            'fe_environment' => ['required', 'string', Rule::in(['test', 'production'])],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => 'nombre comercial',
            'legal_name' => 'razón social',
            'nit' => 'NIT',
            'email' => 'correo electrónico',
            'phone' => 'teléfono',
            'address' => 'dirección',
            'city' => 'ciudad',
            'department' => 'departamento',
            'identification_type_code' => 'tipo de identificación',
            'organization_type_code' => 'tipo de organización',
            'regime_type_code' => 'tipo de régimen',
            'fiscal_responsibilities' => 'responsabilidades fiscales',
            'municipality_code' => 'municipio',
            'economic_activity_code' => 'código de actividad económica',
            'fe_environment' => 'ambiente FE',
        ];
    }
}
