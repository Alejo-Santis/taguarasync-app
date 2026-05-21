<?php

namespace App\Http\Requests\Settings;

use App\Models\FeResolution;
use Illuminate\Validation\Rule;

class UpdateFeResolutionRequest extends StoreFeResolutionRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rules = parent::rules();
        $tenantId = $this->user()?->tenant_id;
        $resolution = $this->route('feResolution');

        $rules['code'] = [
            'required', 'string', 'max:30', 'alpha_dash',
            Rule::unique('fe_resolutions', 'code')
                ->where('tenant_id', $tenantId)
                ->ignore($resolution instanceof FeResolution ? $resolution->id : null),
        ];

        return $rules;
    }
}
