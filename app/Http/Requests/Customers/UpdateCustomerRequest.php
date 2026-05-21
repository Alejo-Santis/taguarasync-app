<?php

namespace App\Http\Requests\Customers;

use App\Models\Customer;
use Illuminate\Validation\Rule;

class UpdateCustomerRequest extends StoreCustomerRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rules = parent::rules();
        $tenantId = $this->user()?->tenant_id;
        $customer = $this->route('customer');

        $rules['identification_number'] = [
            'required', 'string', 'max:30',
            Rule::unique('customers', 'identification_number')
                ->where('tenant_id', $tenantId)
                ->where('identification_type_code', $this->identification_type_code)
                ->whereNull('deleted_at')
                ->ignore($customer instanceof Customer ? $customer->id : null),
        ];

        return $rules;
    }
}
