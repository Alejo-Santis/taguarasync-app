<?php

namespace App\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreFeResolutionRequest extends FormRequest
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
            'code' => [
                'required', 'string', 'max:30', 'alpha_dash',
                Rule::unique('fe_resolutions', 'code')->where('tenant_id', $tenantId),
            ],
            'type' => ['required', 'string', Rule::in(['invoice', 'credit_note', 'debit_note'])],
            'prefix' => ['nullable', 'string', 'max:10'],
            'resolution_number' => ['required', 'string', 'max:40'],
            'resolution_date' => ['required', 'date'],
            'technical_key' => ['required', 'string'],
            'from_number' => ['required', 'integer', 'min:1'],
            'to_number' => ['required', 'integer', 'gt:from_number'],
            'valid_from' => ['required', 'date'],
            'valid_until' => ['required', 'date', 'after:valid_from'],
            'environment' => ['required', 'string', Rule::in(['test', 'production'])],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'code' => 'código interno',
            'type' => 'tipo de documento',
            'prefix' => 'prefijo',
            'resolution_number' => 'número de resolución',
            'resolution_date' => 'fecha de resolución',
            'technical_key' => 'clave técnica',
            'from_number' => 'número inicial',
            'to_number' => 'número final',
            'valid_from' => 'vigencia desde',
            'valid_until' => 'vigencia hasta',
            'environment' => 'ambiente',
        ];
    }
}
