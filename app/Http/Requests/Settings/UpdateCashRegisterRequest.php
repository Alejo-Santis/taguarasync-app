<?php

namespace App\Http\Requests\Settings;

use App\Models\CashRegister;
use Illuminate\Validation\Rule;

class UpdateCashRegisterRequest extends StoreCashRegisterRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rules = parent::rules();
        $tenantId = $this->user()?->tenant_id;
        $register = $this->route('register');

        $rules['code'] = [
            'required', 'string', 'max:30',
            Rule::unique('cash_registers', 'code')
                ->where('tenant_id', $tenantId)
                ->ignore($register instanceof CashRegister ? $register->id : null),
        ];

        return $rules;
    }
}
