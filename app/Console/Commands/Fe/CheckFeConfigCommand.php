<?php

namespace App\Console\Commands\Fe;

use App\Models\DianMunicipality;
use App\Models\FeResolution;
use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class CheckFeConfigCommand extends Command
{
    protected $signature = 'fe:check {--tenant= : UUID del tenant (usa el primero si no se especifica)}';

    protected $description = 'Verifica la configuración de Facturación Electrónica y prueba la conexión con Nextpyme';

    public function handle(): int
    {
        $this->newLine();
        $this->line('  <fg=green>━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━</>');
        $this->line('  <fg=green>  Taguara Sync · Verificación FE Nextpyme</>');
        $this->line('  <fg=green>━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━</>');
        $this->newLine();

        // ── 1. Variables de entorno ────────────────────────────────────────
        $this->line('  <fg=cyan>① Variables de entorno</>');

        $apiUrl = config('fe.api_url');
        $apiToken = config('fe.api_token');
        $enabled = config('fe.enabled');

        $this->checkItem('FE_ENABLED', $enabled ? 'true ✓' : 'false ⚠ (emisión desactivada)', $enabled ? 'info' : 'warn');
        $this->checkItem('FE_API_URL', $apiUrl ?: '— sin configurar', (bool) $apiUrl);
        $this->checkItem('FE_API_TOKEN', $apiToken ? '*** configurado ✓' : '— sin configurar', (bool) $apiToken);

        if (! $apiToken) {
            $this->newLine();
            $this->error('  FE_API_TOKEN no está configurado. Agréguelo en el .env antes de continuar.');

            return self::FAILURE;
        }

        // ── 2. Tenant y feConfig ───────────────────────────────────────────
        $this->newLine();
        $this->line('  <fg=cyan>② Configuración del tenant</>');

        $tenantUuid = $this->option('tenant');
        $tenant = $tenantUuid
            ? Tenant::where('uuid', $tenantUuid)->first()
            : Tenant::first();

        if (! $tenant) {
            $this->error('  No se encontró ningún tenant. Asegúrese de tener al menos una farmacia registrada.');

            return self::FAILURE;
        }

        $this->checkItem('Tenant', "{$tenant->name} (NIT: {$tenant->nit})", true);

        $feConfig = $tenant->feConfig;

        $this->checkItem('TenantFeConfig', $feConfig ? 'existe ✓' : '— no creado', (bool) $feConfig);
        $this->checkItem('Ambiente', $feConfig?->environment?->value ?? '— sin configurar', (bool) $feConfig?->environment);
        $this->checkItem('Token propio', $feConfig?->api_token ? 'configurado ✓' : 'usa token global (.env)', true, 'info');
        $this->checkItem('FE habilitado', $feConfig?->electronic_invoicing_enabled ? 'sí ✓' : 'no ⚠', $feConfig?->electronic_invoicing_enabled, 'warn');
        $this->checkItem('NIT', $tenant->nit ?: '— sin configurar', (bool) $tenant->nit);
        $this->checkItem('DV', $tenant->verification_digit ?: '— sin configurar', (bool) $tenant->verification_digit);
        $this->checkItem('Razón social', $tenant->legal_name ?: '— sin configurar', (bool) $tenant->legal_name);
        // Auto-resolve municipality API ID from DANE code
        $municipalityApiId = $feConfig?->municipality_api_id
            ?? DianMunicipality::where('code', $tenant->municipality_code)->value('api_id');
        $municipalityInfo = $municipalityApiId
            ? "ID: {$municipalityApiId} ✓ (auto-derivado del municipio seleccionado)"
            : '— selecciona el municipio en Configuración → FE';
        $this->checkItem('Municipio API ID', $municipalityInfo, (bool) $municipalityApiId, $municipalityApiId ? 'info' : 'warn');

        // ── 3. Resoluciones ────────────────────────────────────────────────
        $this->newLine();
        $this->line('  <fg=cyan>③ Resoluciones DIAN</>');

        $env = $feConfig?->environment?->value ?? 'test';
        $resolutions = FeResolution::withoutGlobalScopes()
            ->with('send')
            ->where('tenant_id', $tenant->id)
            ->where('environment', $env)
            ->get();

        if ($resolutions->isEmpty()) {
            $this->checkItem('Resoluciones', "ninguna en ambiente '{$env}' ⚠", false, 'warn');
        } else {
            foreach ($resolutions as $r) {
                $remaining = $r->to_number - ($r->send?->next_consecutive ?? 0);
                $status = $r->isExpired() ? '⚠ VENCIDA' : ($r->is_active ? '✓ activa' : '✗ inactiva');
                $this->checkItem(
                    "{$r->type->label()} [{$r->code}]",
                    "{$r->prefix} | {$remaining} nums disponibles | {$status}",
                    $r->is_active && ! $r->isExpired(),
                    $r->isExpired() ? 'warn' : 'info'
                );
            }
        }

        // ── 4. Conexión con la API ─────────────────────────────────────────
        $this->newLine();
        $this->line('  <fg=cyan>④ Prueba de conexión con Nextpyme</>');

        $baseUrl = rtrim($apiUrl, '/');
        $token = $feConfig?->api_token ?? $apiToken;

        try {
            $response = Http::withToken($token)
                ->timeout(15)
                ->get("{$baseUrl}/config/company");

            if ($response->successful()) {
                $company = $response->json();
                $this->checkItem('Conexión API', 'exitosa ✓', true);
                $this->checkItem('Empresa en Nextpyme', $company['business_name'] ?? $company['name'] ?? 'sin nombre', true, 'info');
                $this->checkItem('NIT en Nextpyme', $company['identification_number'] ?? '—', true, 'info');
            } elseif ($response->status() === 401) {
                $this->checkItem('Conexión API', 'token inválido ✗ (401)', false);
            } elseif ($response->status() === 404) {
                // Some setups don't expose /config/company — try another endpoint
                $this->checkItem('Conexión API', 'conectado (no expone /config/company)', true, 'info');
            } else {
                $this->checkItem('Conexión API', "HTTP {$response->status()} ✗", false);
                $this->line("     Respuesta: {$response->body()}");
            }
        } catch (\Exception $e) {
            $this->checkItem('Conexión API', "error: {$e->getMessage()}", false);
        }

        // ── 5. Resumen final ───────────────────────────────────────────────
        $this->newLine();
        $this->line('  <fg=green>━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━</>');

        $readyIssues = [];
        if (! $apiToken) {
            $readyIssues[] = 'FE_API_TOKEN no configurado';
        }
        if (! $feConfig?->electronic_invoicing_enabled) {
            $readyIssues[] = 'FE no habilitado en la configuración del tenant';
        }
        if (! $tenant->nit) {
            $readyIssues[] = 'NIT del tenant vacío';
        }
        if ($resolutions->where('is_active', true)->where(fn ($r) => ! $r->isExpired())->isEmpty()) {
            $readyIssues[] = "Sin resolución activa vigente en ambiente '{$env}'";
        }
        $resolvedMunicipalityId = $feConfig?->municipality_api_id
            ?? DianMunicipality::where('code', $tenant->municipality_code)->value('api_id');
        if (! $resolvedMunicipalityId) {
            $readyIssues[] = 'Municipio no configurado — selecciónalo en Configuración → FE para derivar el ID de Nextpyme automáticamente';
        }

        if (empty($readyIssues)) {
            $this->line('  <fg=green>  ✓ Configuración lista para emitir facturas electrónicas</>');
            $this->line('  <fg=green>  Ejecuta: php artisan fe:test-invoice para enviar una factura de prueba</>');
        } else {
            $this->line('  <fg=yellow>  ⚠ Pendientes antes de emitir:</>');
            foreach ($readyIssues as $issue) {
                $this->line("    <fg=yellow>  · {$issue}</>");
            }
        }

        $this->newLine();

        return empty($readyIssues) ? self::SUCCESS : self::FAILURE;
    }

    private function checkItem(string $label, string $value, bool|string $ok, string $type = 'default'): void
    {
        $icon = match (true) {
            $ok === true || $ok === 'ok' => '<fg=green>  ✓</>',
            $ok === false => '<fg=red>  ✗</>',
            default => '<fg=yellow>  ⚠</>',
        };

        if ($type === 'warn' && $ok !== false) {
            $icon = '<fg=yellow>  ⚠</>';
        }
        if ($type === 'info') {
            $icon = '<fg=blue>  i</>';
        }

        $this->line("    {$icon} <fg=white>{$label}</>: {$value}");
    }
}
