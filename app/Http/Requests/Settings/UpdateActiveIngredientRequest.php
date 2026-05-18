<?php

namespace App\Http\Requests\Settings;

use App\Models\ActiveIngredient;
use Illuminate\Validation\Rule;

class UpdateActiveIngredientRequest extends StoreActiveIngredientRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rules = parent::rules();
        $ingredient = $this->route('ingredient');

        $rules['dci_name'] = [
            'required', 'string', 'max:220',
            Rule::unique('active_ingredients', 'dci_name')
                ->ignore($ingredient instanceof ActiveIngredient ? $ingredient->id : null),
        ];

        return $rules;
    }
}
