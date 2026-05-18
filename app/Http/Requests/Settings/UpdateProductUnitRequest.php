<?php

namespace App\Http\Requests\Settings;

use App\Models\ProductUnit;
use Illuminate\Validation\Rule;

class UpdateProductUnitRequest extends StoreProductUnitRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rules = parent::rules();
        $unit = $this->route('unit');

        $rules['code'] = [
            'required', 'string', 'max:40',
            Rule::unique('product_units', 'code')
                ->ignore($unit instanceof ProductUnit ? $unit->id : null),
        ];

        return $rules;
    }
}
