<?php

namespace App\Http\Requests\Settings;

use App\Support\Sanitize;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreLaboratoryRequest extends FormRequest
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
        $tenantId = $this->user()?->tenant_id;

        return [
            'name' => [
                'required', 'string', 'max:200',
                Rule::unique('laboratories', 'name')->where('tenant_id', $tenantId)->whereNull('deleted_at'),
            ],
            'nit' => ['nullable', 'string', 'max:30'],
            'country' => ['nullable', 'string', 'size:2'],
            'contact_name' => ['nullable', 'string', 'max:150'],
            'contact_email' => ['nullable', 'email', 'max:180'],
            'contact_phone' => ['nullable', 'string', 'max:40'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['name' => 'nombre', 'contact_email' => 'email de contacto'];
    }
}
