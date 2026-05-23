<?php

namespace App\Console\Commands\Fe;

use App\Enums\PaymentForm;
use App\Enums\PaymentMethod;
use App\Models\FeResolution;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Tenant;
use App\Services\Fe\InvoicePayloadBuilder;
use App\Services\Fe\NextpymeClient;
use Illuminate\Console\Command;

class TestFeInvoiceCommand extends Command
{
    protected $signature = 'fe:test-invoice
        {--tenant= : UUID del tenant}
        {--dry-run : Solo muestra el payload sin enviarlo}
        {--json : Muestra respuesta en JSON crudo}';

    protected $description = 'Envía una factura de prueba a la API sandbox de Nextpyme y muestra la respuesta completa';

    public function handle(InvoicePayloadBuilder $builder, NextpymeClient $client): int
    {
        $this->newLine();
        $this->line('  <fg=green>━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━</>');
        $this->line('  <fg=green>  Taguara Sync · Test Factura Electrónica Nextpyme</>');
        $this->line('  <fg=green>━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━</>');
        $this->newLine();

        // ── Obtener tenant ─────────────────────────────────────────────────
        $tenantUuid = $this->option('tenant');
        $tenant = $tenantUuid
            ? Tenant::where('uuid', $tenantUuid)->with('feConfig')->first()
            : Tenant::with('feConfig')->first();

        if (! $tenant) {
            $this->error('Tenant no encontrado.');

            return self::FAILURE;
        }

        $feConfig = $tenant->feConfig;

        if (! $feConfig) {
            $this->error("El tenant '{$tenant->name}' no tiene configuración FE. Configúrala en Configuración → Facturación electrónica.");

            return self::FAILURE;
        }

        // ── Obtener resolución activa ──────────────────────────────────────
        $resolution = FeResolution::withoutGlobalScopes()
            ->with('send')
            ->where('tenant_id', $tenant->id)
            ->where('type', 'invoice')
            ->where('is_active', true)
            ->where('valid_until', '>=', now()->toDateString())
            ->where('environment', $feConfig->environment?->value ?? 'test')
            ->first();

        if (! $resolution) {
            $this->error('No hay resolución activa y vigente para facturas en el ambiente configurado.');
            $this->line("  Ambiente actual: <fg=yellow>{$feConfig->environment?->value}</>");
            $this->line('  Ve a Configuración → FE → Resoluciones y crea una.');

            return self::FAILURE;
        }

        $this->line("  Tenant:     <fg=cyan>{$tenant->name}</> ({$tenant->nit}-{$tenant->verification_digit})");
        $this->line("  Ambiente:   <fg=yellow>{$feConfig->environment?->value}</>");
        $this->line("  Resolución: <fg=cyan>{$resolution->code}</> | Prefijo: {$resolution->prefix} | Siguiente: ".($resolution->send?->next_consecutive + 1 ?? $resolution->from_number));
        $this->newLine();

        // ── Construir una venta simulada ───────────────────────────────────
        $fakeSale = $this->buildFakeSale($tenant);

        // ── Construir payload ──────────────────────────────────────────────
        $testNumber = $resolution->send?->next_consecutive ?? ($resolution->from_number - 1);
        $testNumber++; // Siguiente consecutivo (sin consumirlo — es solo una prueba)

        $payload = $builder->build($fakeSale, $tenant, $resolution, $testNumber);

        // ── Mostrar payload ────────────────────────────────────────────────
        $this->line('  <fg=cyan>📋 Payload que se enviará a Nextpyme:</>');
        $this->line('  URL: <fg=yellow>'.rtrim(config('fe.api_url'), '/').'/ubl2.1/invoice</>');
        $this->newLine();

        if ($this->option('json')) {
            $this->line(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        } else {
            $this->table(
                ['Campo', 'Valor'],
                [
                    ['number', $payload['number']],
                    ['prefix', $payload['prefix'] ?? '(sin prefijo)'],
                    ['date', $payload['date']],
                    ['resolution_number', $payload['resolution_number']],
                    ['customer.name', $payload['customer']['name']],
                    ['customer.identification', $payload['customer']['identification_number']],
                    ['customer.municipality_id', $payload['customer']['municipality_id']],
                    ['customer.type_liability_id', $payload['customer']['type_liability_id']],
                    ['invoice_lines (count)', count($payload['invoice_lines'])],
                    ['legal_monetary_totals.payable_amount', '$'.$payload['legal_monetary_totals']['payable_amount']],
                    ['foot_note', $payload['foot_note'] ?? '—'],
                ]
            );
        }

        if ($this->option('dry-run')) {
            $this->info('  Modo --dry-run: payload construido correctamente. No se envió nada.');

            return self::SUCCESS;
        }

        // ── Enviar ─────────────────────────────────────────────────────────
        if (! $this->confirm('  ¿Enviar esta factura de prueba a Nextpyme sandbox?', true)) {
            $this->line('  Cancelado.');

            return self::SUCCESS;
        }

        $this->newLine();
        $this->line('  <fg=yellow>Enviando a Nextpyme...</>');

        try {
            $apiClient = $client->forTenant((string) ($feConfig->api_token ?? ''));
            $response = $apiClient->createInvoice($payload);

            $this->newLine();
            $this->line('  <fg=green>✓ Respuesta recibida:</>');

            $dianResult = $response['ResponseDian']['Envelope']['Body']['SendBillSyncResponse']['SendBillSyncResult'] ?? [];
            $isValid = ($dianResult['IsValid'] ?? 'false') === 'true';
            $statusCode = $dianResult['StatusCode'] ?? '?';
            $cufe = $response['cufe'] ?? $response['uuid_dian'] ?? '—';
            $qr = isset($response['QRStr']) ? substr($response['QRStr'], 0, 60).'...' : '—';
            $xmlKey = $dianResult['XmlDocumentKey'] ?? '—';
            $errorMsg = data_get($dianResult, 'ErrorMessage.string', '—');
            if (is_array($errorMsg)) {
                $errorMsg = implode('; ', $errorMsg);
            }

            $this->table(
                ['Campo', 'Valor'],
                [
                    ['DIAN IsValid', $isValid ? '✓ true — ACEPTADA' : '✗ false — RECHAZADA'],
                    ['DIAN StatusCode', $statusCode.' '.($statusCode === '00' ? '(válida)' : '(inválida)')],
                    ['CUFE', $cufe ? substr($cufe, 0, 32).'...' : '—'],
                    ['QR', $qr],
                    ['XmlDocumentKey', $xmlKey ? substr($xmlKey, 0, 32).'...' : '—'],
                    ['Mensaje DIAN', is_string($errorMsg) ? substr($errorMsg, 0, 80) : '—'],
                    ['send_email_success', $response['send_email_success'] ?? '—'],
                ]
            );

            if ($this->option('json')) {
                $this->newLine();
                $this->line('  <fg=cyan>Respuesta completa:</>');
                $this->line(json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            }

            if ($isValid) {
                $this->newLine();
                $this->info('  ✓ Factura aceptada por la DIAN en ambiente de pruebas. La integración está funcionando correctamente.');
            } else {
                $this->newLine();
                $this->warn('  ⚠ La DIAN rechazó el documento. Revisa el mensaje de error y los datos de la resolución/empresa.');
            }

        } catch (\Exception $e) {
            $this->newLine();
            $this->error("  Error al enviar: {$e->getMessage()}");
            $this->newLine();
            $this->line('  <fg=yellow>Posibles causas:</>');
            $this->line('    · Token incorrecto o expirado');
            $this->line('    · Empresa no registrada en Nextpyme');
            $this->line('    · Resolución no configurada en Nextpyme');
            $this->line('    · URL de sandbox incorrecta');

            return self::FAILURE;
        }

        $this->newLine();

        return self::SUCCESS;
    }

    private function buildFakeSale(Tenant $tenant): Sale
    {
        // Construye una venta en memoria (sin tocar la BD) para generar el payload
        $sale = new Sale;
        $sale->exists = false;
        $sale->created_at = now();

        $sale->setRelation('customer', null); // Consumidor final
        $sale->setRelation('tenant', $tenant);

        // Ítem de ejemplo: 1 unidad a $10,000 sin IVA
        $item = new SaleItem;
        $item->product_id = 1;
        $item->description = 'ACETAMINOFEN 500MG TABLETA (PRUEBA FE)';
        $item->quantity = 1;
        $item->unit_price = 1000000; // $10,000.00 (en centavos)
        $item->tax_rate = 0;
        $item->line_subtotal = 1000000;
        $item->line_tax = 0;
        $item->line_total = 1000000;
        $item->dian_unit_measure_code = null;

        $sale->setRelation('items', collect([$item]));

        $sale->subtotal = 1000000;
        $sale->tax_total = 0;
        $sale->total = 1000000;
        $sale->notes = 'Factura de prueba - Taguara Sync FE Test';
        $sale->payment_method = PaymentMethod::Cash;
        $sale->payment_form = PaymentForm::Cash;
        $sale->payment_due_date = null;

        return $sale;
    }
}
