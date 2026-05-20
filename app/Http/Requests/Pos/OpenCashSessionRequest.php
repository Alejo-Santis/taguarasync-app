<?php

namespace App\Http\Requests\Pos;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class OpenCashSessionRequest extends FormRequest
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
            'cash_register_id' => [
                'required', 'integer',
                Rule::exists('cash_registers', 'id')->where('tenant_id', $tenantId)->where('is_active', true),
            ],
            'opening_amount' => ['required', 'integer', 'min:0'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['cash_register_id' => 'caja', 'opening_amount' => 'saldo de apertura'];
    }
}
