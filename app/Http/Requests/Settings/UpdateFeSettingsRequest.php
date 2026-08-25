<?php

namespace App\Http\Requests\Settings;

use App\Support\Sanitize;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateFeSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('nit')) {
            $this->merge(['nit' => Sanitize::identification($this->input('nit'))]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // ── Datos básicos del tenant ──────────────────────────────────
            'name' => ['required', 'string', 'max:180'],
            'legal_name' => ['nullable', 'string', 'max:220'],
            'nit' => ['nullable', 'string', 'max:30'],
            'merchant_registration' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:180'],
            'phone' => ['nullable', 'string', 'max:40'],
            'address' => ['nullable', 'string', 'max:500'],
            'city' => ['nullable', 'string', 'max:120'],
            'department' => ['nullable', 'string', 'max:120'],
            'municipality_code' => ['nullable', 'string', Rule::exists('dian_municipalities', 'code')],

            // ── Configuración FE (tenant_fe_configs) ──────────────────────
            'electronic_invoicing_enabled' => ['boolean'],
            'identification_type_code' => ['nullable', 'string', Rule::exists('dian_identification_types', 'code')],
            'organization_type_code' => ['nullable', 'string', Rule::exists('dian_organization_types', 'code')],
            'regime_type_code' => ['nullable', 'string', Rule::exists('dian_regime_types', 'code')],
            'fiscal_responsibilities' => ['nullable', 'array'],
            'fiscal_responsibilities.*' => ['string', Rule::exists('dian_fiscal_responsibilities', 'code')],
            'economic_activity_code' => ['nullable', 'string', Rule::exists('economic_activities', 'code')],
            'environment' => ['required', 'string', Rule::in(['test', 'production'])],
            'api_token' => ['nullable', 'string', 'max:500'],
            'software_id' => ['nullable', 'string', 'max:100'],
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
            'merchant_registration' => 'registro mercantil',
            'email' => 'correo electrónico',
            'phone' => 'teléfono',
            'address' => 'dirección',
            'city' => 'ciudad',
            'department' => 'departamento',
            'municipality_code' => 'municipio',
            'identification_type_code' => 'tipo de identificación',
            'organization_type_code' => 'tipo de organización',
            'regime_type_code' => 'tipo de régimen',
            'fiscal_responsibilities' => 'responsabilidades fiscales',
            'municipality_api_id' => 'ID municipio API',
            'economic_activity_code' => 'código de actividad económica',
            'environment' => 'ambiente FE',
            'api_token' => 'token API',
            'software_id' => 'ID de software',
        ];
    }
}
