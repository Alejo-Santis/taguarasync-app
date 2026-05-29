<?php

namespace App\Actions\Fe;

use App\Enums\FeStatus;
use App\Models\CreditNote;
use App\Models\FeResolution;
use App\Models\Tenant;
use App\Services\Fe\NextpymeClient;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Log;
use Throwable;

class EmitCreditNote
{
    /** @var int[] */
    private const NON_RECOVERABLE_HTTP = [400, 401, 403, 422];

    /** @var string[] */
    private const RULE_90_PATTERNS = [
        'regla: 90',
        'regla 90',
        'rule 90',
        'ya fue enviado',
        'ya fue procesado',
        'already processed',
        'document already',
        'duplicado',
        'duplicate',
        'documento procesado anteriormente',
    ];

    public function __construct(
        private readonly NextpymeClient $client,
    ) {}

    public function execute(CreditNote $creditNote, Tenant $tenant): void
    {
        $creditNote->load(['items', 'sale.customer']);

        $feConfig = $tenant->feConfig;

        $resolution = FeResolution::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('type', 'credit_note')
            ->where('is_active', true)
            ->where('valid_until', '>=', now()->toDateString())
            ->where('environment', $feConfig?->environment?->value ?? 'test')
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

        $client = $this->client->forTenant((string) ($feConfig?->api_token ?? ''));

        try {
            $response = $client->createCreditNote($payload);

            $cufe = $response['cufe'] ?? $response['uuid'] ?? null;
            $qr = $response['QRStr'] ?? $response['qr'] ?? $response['qrcode'] ?? null;

            $dianResult = $response['ResponseDian']['Envelope']['Body']['SendBillSyncResponse']['SendBillSyncResult'] ?? [];
            $dianIsValid = ($dianResult['IsValid'] ?? 'false') === 'true';
            $dianStatusCode = $dianResult['StatusCode'] ?? null;

            if ($dianIsValid || $dianStatusCode === '00') {
                $creditNote->update([
                    'fe_cufe' => $cufe,
                    'fe_qr_code' => $qr,
                    'fe_status' => FeStatus::Accepted,
                    'fe_accepted_at' => now(),
                ]);

                $this->updateSubmissionResponse($creditNote, $response, 'accepted', $tenant);
            } else {
                $dianErrorMsg = data_get($dianResult, 'ErrorMessage.string', '');
                if (is_array($dianErrorMsg)) {
                    $dianErrorMsg = implode('; ', $dianErrorMsg);
                }

                if ($this->isRule90Error((string) $dianErrorMsg)) {
                    $creditNote->update([
                        'fe_cufe' => $cufe,
                        'fe_qr_code' => $qr,
                        'fe_status' => FeStatus::Accepted,
                        'fe_accepted_at' => now(),
                    ]);

                    $this->updateSubmissionResponse($creditNote, $response, 'accepted_rule90', $tenant);
                } else {
                    $creditNote->update([
                        'fe_cufe' => $cufe,
                        'fe_status' => FeStatus::Rejected,
                        'fe_error_message' => $dianErrorMsg ?: "DIAN StatusCode: {$dianStatusCode}",
                    ]);

                    $this->updateSubmissionResponse($creditNote, $response, 'rejected', $tenant);
                }
            }
        } catch (Throwable $e) {
            $isRule90 = $this->isRule90Error($e->getMessage());
            $isNonRecoverable = $isRule90 || $this->isNonRecoverableError($e);

            if ($isRule90) {
                $cufe = $this->extractCufeFromError($e->getMessage());

                $creditNote->update([
                    'fe_status' => FeStatus::Accepted,
                    'fe_accepted_at' => now(),
                    'fe_cufe' => $cufe,
                ]);

                $this->updateSubmissionResponse($creditNote, ['error' => $e->getMessage(), 'rule90' => true], 'accepted_rule90', $tenant);

                return;
            }

            $isTransmissionFailure = $e instanceof ConnectionException || ! $isNonRecoverable;

            if ($isNonRecoverable) {
                Log::error('[FE] Error no-recuperable en emisión de nota crédito', [
                    'credit_note_id' => $creditNote->id,
                    'error' => $e->getMessage(),
                ]);
            }

            $creditNote->update([
                'fe_status' => $isTransmissionFailure ? FeStatus::Contingency : FeStatus::Rejected,
                'fe_error_message' => $e->getMessage(),
            ]);

            $this->updateSubmissionResponse($creditNote, ['error' => $e->getMessage()], $isTransmissionFailure ? 'contingency' : 'rejected', $tenant);

            if (! $isNonRecoverable) {
                throw $e;
            }
        }
    }

    private function isRule90Error(string $message): bool
    {
        $lower = mb_strtolower($message);

        foreach (self::RULE_90_PATTERNS as $pattern) {
            if (str_contains($lower, $pattern)) {
                return true;
            }
        }

        return false;
    }

    private function isNonRecoverableError(Throwable $e): bool
    {
        $message = $e->getMessage();

        foreach (self::NON_RECOVERABLE_HTTP as $code) {
            if (str_contains($message, "HTTP {$code}") || str_contains($message, "Error {$code}")) {
                return true;
            }
        }

        return false;
    }

    private function extractCufeFromError(string $message): ?string
    {
        if (preg_match('/[0-9a-f]{64,96}/i', $message, $matches)) {
            return $matches[0];
        }

        return null;
    }

    /** @return array<string, mixed> */
    private function buildPayload(CreditNote $creditNote, Tenant $tenant, int $number, ?FeResolution $resolution): array
    {
        $sale = $creditNote->sale;
        $customer = $sale->customer;
        $feConfig = $tenant->feConfig;
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
            'municipality_id' => $feConfig?->municipality_api_id ?? 0,
            'merchant_registration' => $customer->merchant_registration ?? '0000000-00',
            'type_document_identification_id' => $idTypeMap[$customer->identification_type_code] ?? 3,
            'type_organization_id' => $orgTypeMap[$customer->organization_type_code] ?? 2,
            'type_regime_id' => $regimeMap[$customer->regime_type_code] ?? 2,
        ] : [
            'identification_number' => '222222222222',
            'dv' => null,
            'name' => 'Consumidor Final',
            'email' => null,
            'phone' => null,
            'address' => $tenant->address,
            'municipality_id' => $feConfig?->municipality_api_id ?? 0,
            'merchant_registration' => $tenant->merchant_registration ?? '0000000-00',
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
