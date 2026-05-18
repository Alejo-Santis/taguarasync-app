<?php

namespace App\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProductUnitRequest extends FormRequest
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
        return [
            'name' => ['required', 'string', 'max:80'],
            'code' => ['required', 'string', 'max:40', Rule::unique('product_units', 'code')],
            'kind' => ['required', 'string', Rule::in(['minimum', 'package'])],
            'allows_decimals' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['name' => 'nombre', 'code' => 'codigo', 'kind' => 'tipo'];
    }
}
