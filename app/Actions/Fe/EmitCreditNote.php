<?php

namespace App\Actions\Fe;

use App\Enums\FeStatus;
use App\Models\CreditNote;
use App\Models\FeResolution;
use App\Models\Tenant;
use App\Services\Fe\NextpymeClient;
use Throwable;

class EmitCreditNote
{
    public function __construct(
        private readonly NextpymeClient $client,
    ) {}

    public function execute(CreditNote $creditNote, Tenant $tenant): void
    {
        $creditNote->load(['items', 'sale.customer']);

        $resolution = FeResolution::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('type', 'credit_note')
            ->where('is_active', true)
            ->where('valid_until', '>=', now()->toDateString())
            ->where('environment', $tenant->fe_environment->value)
            ->first();

        $noteNumber = $resolution
            ? $resolution->consumeNextNumber()
            : $creditNote->id;

        if ($resolution) {
            $creditNote->update(['prefix' => $resolution->prefix, 'number' => (string) $noteNumber]);
        }

        $creditNote->update(['fe_status' => FeStatus::Pending, 'fe_sent_at' => now()]);

        $payload = $this->buildPayload($creditNote, $tenant, $noteNumber, $resolution);

        $this->recordSubmission($creditNote, $payload, $tenant);

        try {
            $response = $this->client->createCreditNote($payload);

            $cufe = $response['cufe'] ?? $response['uuid'] ?? null;
            $qr = $response['qr'] ?? $response['qrcode'] ?? null;

            $creditNote->update([
                'fe_cufe' => $cufe,
                'fe_qr_code' => $qr,
                'fe_status' => FeStatus::Accepted,
                'fe_accepted_at' => now(),
            ]);

            $this->updateSubmissionResponse($creditNote, $response, 'accepted', $tenant);
        } catch (Throwable $e) {
            $creditNote->update([
                'fe_status' => FeStatus::Rejected,
                'fe_error_message' => $e->getMessage(),
            ]);

            $this->updateSubmissionResponse($creditNote, ['error' => $e->getMessage()], 'rejected', $tenant);

            throw $e;
        }
    }

    /** @return array<string, mixed> */
    private function buildPayload(CreditNote $creditNote, Tenant $tenant, int $number, ?FeResolution $resolution): array
    {
        $sale = $creditNote->sale;
        $customer = $sale->customer;
        $idTypeMap = config('fe.map.id_types');
        $orgTypeMap = config('fe.map.org_types');
        $regimeMap = config('fe.map.regime_types');

        $customerPayload = $customer ? [
            'identification_number' => $customer->identification_number,
            'dv' => $customer->verification_digit,
            'name' => $customer->full_name,
            'email' => $customer->email,
            'phone' => $customer->phone,
            'address' => $customer->address,
            'municipality_id' => $tenant->fe_municipality_api_id ?? 0,
            'merchant_registration' => '0000000-00',
            'type_document_identification_id' => $idTypeMap[$customer->identification_type_code] ?? 3,
            'type_organization_id' => $orgTypeMap[$customer->organization_type_code] ?? 2,
            'type_regime_id' => $regimeMap[$customer->regime_type_code] ?? 2,
        ] : [
            'identification_number' => '222222222222',
            'dv' => null,
            'name' => 'Consumidor Final',
            'email' => null,
            'phone' => null,
            'address' => null,
            'municipality_id' => $tenant->fe_municipality_api_id ?? 0,
            'merchant_registration' => '0000000-00',
            'type_document_identification_id' => 3,
            'type_organization_id' => 2,
            'type_regime_id' => 2,
        ];

        $lines = $creditNote->items->map(function ($item): array {
            $subtotal = $item->line_subtotal / 100;
            $taxRate = (float) $item->tax_rate;

            return [
                'code' => (string) $item->product_id,
                'description' => $item->description,
                'notes' => null,
                'unit_measure_id' => config('fe.map.unit_measure_default'),
                'invoiced_quantity' => (string) $item->quantity,
                'base_quantity' => (string) $item->quantity,
                'price_amount' => number_format($item->unit_price / 100, 2, '.', ''),
                'line_extension_amount' => number_format($subtotal, 2, '.', ''),
                'free_of_charge_indicator' => false,
                'type_item_identification_id' => 4,
                'tax_totals' => $taxRate > 0 ? [[
                    'tax_id' => config('fe.map.iva_tax_id'),
                    'percent' => number_format($taxRate, 2, '.', ''),
                    'taxable_amount' => number_format($subtotal, 2, '.', ''),
                    'tax_amount' => number_format($item->line_tax / 100, 3, '.', ''),
                ]] : [],
            ];
        })->values()->all();

        $total = $creditNote->total / 100;
        $subtotal = $creditNote->subtotal / 100;

        return [
            'date' => $creditNote->created_at->format('Y-m-d'),
            'time' => $creditNote->created_at->format('H:i:s'),
            'number' => $number,
            'prefix' => $resolution?->prefix,
            'type_document_id' => config('fe.map.doc_types.credit_note'),
            'resolution_number' => $resolution?->resolution_number,
            'notes' => $creditNote->notes,
            'sendmail' => $customer?->email !== null,
            'sendmailtome' => true,
            'customer' => $customerPayload,
            'credit_note_lines' => $lines,
            'discrepancyresponsecode' => (int) $creditNote->discrepancy_reason_code,
            'discrepancyresponsedescription' => $this->discrepancyLabel($creditNote->discrepancy_reason_code),
            'billing_reference' => [
                'uuid' => $sale->fe_cufe,
                'number' => ($sale->invoice_prefix ?? '').$sale->document_number,
                'issue_date' => $sale->created_at->format('Y-m-d'),
            ],
            'legal_monetary_totals' => [
                'line_extension_amount' => number_format($subtotal, 2, '.', ''),
                'tax_exclusive_amount' => number_format($subtotal, 2, '.', ''),
                'tax_inclusive_amount' => number_format($total, 2, '.', ''),
                'allowance_total_amount' => '0.00',
                'charge_total_amount' => '0.00',
                'payable_amount' => number_format($total, 2, '.', ''),
            ],
            'operation_mode' => [
                'company' => "{$tenant->legal_name} - NIT: {$tenant->nit}-{$tenant->verification_digit}",
                'software' => 'Taguara Sync',
            ],
        ];
    }

    private function discrepancyLabel(string $code): string
    {
        $labels = [
            '1' => 'Devolución de parte de los bienes; no aceptación de partes del servicio',
            '2' => 'Anulación de factura electrónica',
            '3' => 'Rebaja total aplicada',
            '4' => 'Descuento total aplicado',
            '5' => 'Rescisión: nulidad por falta de requisitos',
            '6' => 'Otros',
        ];

        return $labels[$code] ?? 'Otros';
    }

    private function recordSubmission(CreditNote $creditNote, array $payload, Tenant $tenant): void
    {
        $tenant->feSubmissions()->create([
            'document_type' => 'credit_note',
            'document_id' => $creditNote->id,
            'request_payload' => $payload,
            'submitted_at' => now(),
        ]);
    }

    private function updateSubmissionResponse(CreditNote $creditNote, array $response, string $status, Tenant $tenant): void
    {
        $tenant->feSubmissions()
            ->where('document_type', 'credit_note')
            ->where('document_id', $creditNote->id)
            ->latest()
            ->first()
            ?->update([
                'response_payload' => $response,
                'response_status' => $status,
                'responded_at' => now(),
            ]);
    }
}
