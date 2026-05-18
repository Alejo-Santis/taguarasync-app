<?php

namespace App\Http\Requests\Settings;

use App\Models\Laboratory;
use Illuminate\Validation\Rule;

class UpdateLaboratoryRequest extends StoreLaboratoryRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rules = parent::rules();
        $tenantId = $this->user()?->tenant_id;
        $laboratory = $this->route('laboratory');

        $rules['name'] = [
            'required', 'string', 'max:200',
            Rule::unique('laboratories', 'name')
                ->where('tenant_id', $tenantId)
                ->whereNull('deleted_at')
                ->ignore($laboratory instanceof Laboratory ? $laboratory->id : null),
        ];

        return $rules;
    }
}
