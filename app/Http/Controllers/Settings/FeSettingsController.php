<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\UpdateFeSettingsRequest;
use App\Models\DianFiscalResponsibility;
use App\Models\DianIdentificationType;
use App\Models\DianMunicipality;
use App\Models\DianOrganizationType;
use App\Models\DianRegimeType;
use App\Models\EconomicActivity;
use App\Models\FeResolution;
use App\Support\Tenancy\CurrentTenant;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class FeSettingsController extends Controller
{
    public function index(CurrentTenant $currentTenant): Response
    {
        $tenant = $currentTenant->get()->load('feConfig');
        $feConfig = $tenant->feConfig;

        $resolutions = FeResolution::withoutGlobalScopes()
            ->with('send')
            ->where('tenant_id', $tenant->id)
            ->orderBy('type')
            ->orderByDesc('is_active')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (FeResolution $r) => [
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
                'current_number' => $r->send?->next_consecutive ?? 0,
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
                'merchant_registration' => $tenant->merchant_registration ?? '',
                'verification_digit' => $tenant->verification_digit ?? '',
                'email' => $tenant->email ?? '',
                'phone' => $tenant->phone ?? '',
                'address' => $tenant->address ?? '',
                'city' => $tenant->city ?? '',
                'department' => $tenant->department ?? '',
                'municipality_code' => $tenant->municipality_code ?? '',
            ],
            'fe_config' => [
                'electronic_invoicing_enabled' => $feConfig?->electronic_invoicing_enabled ?? false,
                'identification_type_code' => $feConfig?->identification_type_code ?? '',
                'organization_type_code' => $feConfig?->organization_type_code ?? '',
                'regime_type_code' => $feConfig?->regime_type_code ?? '',
                'fiscal_responsibilities' => $feConfig?->fiscal_responsibilities ?? [],
                'economic_activity_code' => $feConfig?->economic_activity_code ?? '',
                'environment' => $feConfig?->environment?->value ?? 'test',
                'api_token_set' => ! empty($feConfig?->api_token),
                'software_id' => $feConfig?->software_id ?? '',
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
                    ->get(['code', 'api_id', 'name', 'department_name']),
                'economic_activities' => EconomicActivity::orderBy('code')
                    ->get(['code', 'name']),
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
        $tenant = $currentTenant->get();

        // Datos básicos del tenant
        $tenantData = collect($data)->only([
            'name', 'legal_name', 'nit', 'merchant_registration',
            'email', 'phone', 'address', 'city', 'department', 'municipality_code',
        ])->filter(fn ($v) => $v !== null)->toArray();

        if (! empty($tenantData['nit'])) {
            $tenantData['verification_digit'] = $this->calculateNitDv($tenantData['nit']);
        }

        $tenant->update($tenantData);

        // Configuración FE — separada en tenant_fe_configs
        $feData = collect($data)->only([
            'electronic_invoicing_enabled',
            'identification_type_code',
            'organization_type_code',
            'regime_type_code',
            'fiscal_responsibilities',
            'economic_activity_code',
            'environment',
            'api_token',
            'software_id',
        ])->filter(fn ($v) => $v !== null)->toArray();

        // Si el token viene vacío, no lo sobreescribimos
        if (isset($feData['api_token']) && $feData['api_token'] === '') {
            unset($feData['api_token']);
        }

        $tenant->feConfig()->updateOrCreate(
            ['tenant_id' => $tenant->id],
            $feData
        );

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
