<?php

namespace App\Http\Requests\Settings;

use App\Models\ProductCategory;
use Illuminate\Validation\Rule;

class UpdateProductCategoryRequest extends StoreProductCategoryRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rules = parent::rules();
        $tenantId = $this->user()?->tenant_id;
        $category = $this->route('category');

        $rules['name'] = [
            'required', 'string', 'max:120',
            Rule::unique('product_categories', 'name')
                ->where('tenant_id', $tenantId)
                ->ignore($category instanceof ProductCategory ? $category->id : null),
        ];

        return $rules;
    }
}
