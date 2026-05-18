<?php

namespace App\Http\Requests\Product;

use App\Models\Product;
use Illuminate\Validation\Rule;

class UpdateProductRequest extends StoreProductRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rules = parent::rules();
        $tenantId = $this->user()?->tenant_id;
        $product = $this->route('product');

        $rules['internal_code'] = [
            'required',
            'string',
            'max:60',
            Rule::unique('products', 'internal_code')
                ->where('tenant_id', $tenantId)
                ->ignore($product instanceof Product ? $product->id : null),
        ];

        return $rules;
    }
}
