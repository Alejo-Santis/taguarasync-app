<?php

namespace App\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreActiveIngredientRequest extends FormRequest
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
            'dci_name' => ['required', 'string', 'max:220', Rule::unique('active_ingredients', 'dci_name')],
            'pharmacological_group' => ['nullable', 'string', 'max:220'],
            'atc_classification' => ['nullable', 'string', 'max:30'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['dci_name' => 'nombre DCI', 'pharmacological_group' => 'grupo farmacologico', 'atc_classification' => 'clasificacion ATC'];
    }
}
