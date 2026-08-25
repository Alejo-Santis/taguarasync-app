<?php

namespace App\Http\Requests\Settings;

use App\Support\Sanitize;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSupplierRequest extends FormRequest
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
                'required', 'string', 'max:220',
                Rule::unique('suppliers', 'name')->where('tenant_id', $tenantId),
            ],
            'nit' => ['nullable', 'string', 'max:60'],
            'contact_name' => ['nullable', 'string', 'max:160'],
            'contact_email' => ['nullable', 'email', 'max:160'],
            'contact_phone' => ['nullable', 'string', 'max:80'],
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
