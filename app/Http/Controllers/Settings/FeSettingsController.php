<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\UpdateFeSettingsRequest;
use App\Models\DianFiscalResponsibility;
use App\Models\DianIdentificationType;
use App\Models\DianMunicipality;
use App\Models\DianOrganizationType;
use App\Models\DianRegimeType;
use App\Models\FeResolution;
use App\Support\Tenancy\CurrentTenant;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class FeSettingsController extends Controller
{
    public function index(CurrentTenant $currentTenant): Response
    {
        $tenant = $currentTenant->get();

        $resolutions = FeResolution::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->orderBy('type')
            ->orderByDesc('is_active')
            ->orderByDesc('created_at')
            ->paginate(10)
            ->withQueryString()
            ->through(fn (FeResolution $r) => [
                'id' => $r->id,
                'code' => $r->code,
                'type' => $r->type->value,
                'type_label' => $r->type->label(),
                'prefix' => $r->prefix,
                'resolution_number' => $r->resolution_number,
                'resolution_date' => $r->resolution_date->format('Y-m-d'),
                'technical_key' => $r->technical_key,
                'from_number' => $r->from_number,
                'to_number' => $r->to_number,
                'current_number' => $r->current_number,
                'valid_from' => $r->valid_from->format('Y-m-d'),
                'valid_until' => $r->valid_until->format('Y-m-d'),
                'environment' => $r->environment->value,
                'environment_label' => $r->environment->label(),
                'is_active' => $r->is_active,
                'is_expired' => $r->isExpired(),
                'has_remaining' => $r->hasRemainingNumbers(),
            ]);

        return Inertia::render('Settings/Fe/Index', [
            'tenant' => [
                'name' => $tenant->name ?? '',
                'legal_name' => $tenant->legal_name ?? '',
                'nit' => $tenant->nit ?? '',
                'verification_digit' => $tenant->verification_digit ?? '',
                'email' => $tenant->email ?? '',
                'phone' => $tenant->phone ?? '',
                'address' => $tenant->address ?? '',
                'city' => $tenant->city ?? '',
                'department' => $tenant->department ?? '',
                'identification_type_code' => $tenant->identification_type_code ?? '',
                'organization_type_code' => $tenant->organization_type_code ?? '',
                'regime_type_code' => $tenant->regime_type_code ?? '',
                'fiscal_responsibilities' => $tenant->fiscal_responsibilities ?? [],
                'municipality_code' => $tenant->municipality_code ?? '',
                'fe_municipality_api_id' => $tenant->fe_municipality_api_id ?? '',
                'economic_activity_code' => $tenant->economic_activity_code ?? '',
                'fe_environment' => $tenant->fe_environment->value ?? 'test',
            ],
            'resolutions' => $resolutions,
            'options' => [
                'identification_types' => DianIdentificationType::where('is_active', true)
                    ->orderBy('name')->get(['code', 'name']),
                'organization_types' => DianOrganizationType::orderBy('name')->get(['code', 'name']),
                'regime_types' => DianRegimeType::orderBy('name')->get(['code', 'name']),
                'fiscal_responsibilities' => DianFiscalResponsibility::orderBy('code')
                    ->get(['code', 'name']),
                'municipalities' => DianMunicipality::orderBy('department_name')->orderBy('name')
                    ->get(['code', 'name', 'department_name']),
                'resolution_types' => [
                    ['value' => 'invoice', 'label' => 'Factura de venta'],
                    ['value' => 'credit_note', 'label' => 'Nota crédito'],
                    ['value' => 'debit_note', 'label' => 'Nota débito'],
                ],
                'environments' => [
                    ['value' => 'test', 'label' => 'Pruebas (DIAN Habilitación)'],
                    ['value' => 'production', 'label' => 'Producción'],
                ],
            ],
        ]);
    }

    public function update(UpdateFeSettingsRequest $request, CurrentTenant $currentTenant): RedirectResponse
    {
        $data = $request->validated();

        if (! empty($data['nit'])) {
            $data['verification_digit'] = $this->calculateNitDv($data['nit']);
        }

        $currentTenant->get()->update($data);

        return back()->with('success', 'Configuración actualizada correctamente.');
    }

    private function calculateNitDv(string $nit): string
    {
        $primes = [71, 67, 59, 53, 47, 43, 41, 37, 29, 23, 19, 17, 13, 7, 3];
        $digits = preg_replace('/\D/', '', $nit);

        if (empty($digits) || strlen($digits) > 15) {
            return '';
        }

        $sum = 0;
        $offset = count($primes) - strlen($digits);

        for ($i = 0; $i < strlen($digits); $i++) {
            $sum += (int) $digits[$i] * $primes[$offset + $i];
        }

        $remainder = $sum % 11;

        return (string) ($remainder === 0 || $remainder === 1 ? $remainder : 11 - $remainder);
    }
}
