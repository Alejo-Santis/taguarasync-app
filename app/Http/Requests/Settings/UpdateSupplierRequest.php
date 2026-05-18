<?php

namespace App\Http\Requests\Settings;

use App\Models\Supplier;
use Illuminate\Validation\Rule;

class UpdateSupplierRequest extends StoreSupplierRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rules = parent::rules();
        $tenantId = $this->user()?->tenant_id;
        $supplier = $this->route('supplier');

        $rules['name'] = [
            'required', 'string', 'max:220',
            Rule::unique('suppliers', 'name')
                ->where('tenant_id', $tenantId)
                ->ignore($supplier instanceof Supplier ? $supplier->id : null),
        ];

        return $rules;
    }
}
