<?php

namespace App\Actions\Fe;

use App\Enums\FeStatus;
use App\Jobs\CheckDianStatusJob;
use App\Models\FeResolution;
use App\Models\FeSubmission;
use App\Models\Sale;
use App\Models\Tenant;
use App\Services\Fe\InvoicePayloadBuilder;
use App\Services\Fe\NextpymeClient;
use Illuminate\Http\Client\ConnectionException;
use Throwable;

class EmitElectronicInvoice
{
    /**
     * HTTP codes that indicate a non-recoverable error.
     * No point retrying: our payload is wrong or credentials are invalid.
     *
     * @var int[]
     */
    private const NON_RECOVERABLE_HTTP = [400, 401, 403, 422];

    /**
     * Patterns that identify DIAN Rule 90 in error messages.
     * Rule 90 = document already processed → mark as accepted, do NOT retry.
     *
     * @var string[]
     */
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
        private readonly InvoicePayloadBuilder $builder,
    ) {}

    public function execute(Sale $sale, Tenant $tenant): void
    {
        $sale->load(['items', 'customer']);

        $feConfig = $tenant->feConfig;

        $resolution = FeResolution::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('type', 'invoice')
            ->where('is_active', true)
            ->where('valid_until', '>=', now()->toDateString())
            ->where('environment', $feConfig?->environment?->value ?? 'test')
            ->first();

        if (! $resolution) {
            $sale->update(['fe_status' => FeStatus::NotApplicable]);

            return;
        }

        $invoiceNumber = $resolution->consumeNextNumber();

        $sale->update([
            'invoice_prefix' => $resolution->prefix,
            'fe_status' => FeStatus::Pending,
            'fe_sent_at' => now(),
        ]);

        $payload = $this->builder->build($sale, $tenant, $resolution, $invoiceNumber);
        $submission = $this->recordSubmission($sale, $payload);
        $client = $this->client->forTenant((string) ($feConfig?->api_token ?? ''));

        try {
            $response = $client->createInvoice($payload);

            // ── Parse response fields ─────────────────────────────────────
            $cufe = $response['cufe'] ?? $response['uuid_dian'] ?? $response['uuid'] ?? null;
            $qr = $response['QRStr'] ?? $response['qr'] ?? $response['qrcode'] ?? null;

            // XmlDocumentKey lives inside the nested DIAN response
            $dianResult = $response['ResponseDian']['Envelope']['Body']['SendBillSyncResponse']['SendBillSyncResult'] ?? [];
            $xmlDocumentKey = $dianResult['XmlDocumentKey'] ?? $cufe;
            $dianIsValid = ($dianResult['IsValid'] ?? 'false') === 'true';
            $dianStatusCode = $dianResult['StatusCode'] ?? null;

            // ── Determine final FE status synchronously ───────────────────
            // Nextpyme returns DIAN validation inline — no async polling needed for normal flow.
            if ($dianIsValid || $dianStatusCode === '00') {
                $sale->update([
                    'fe_cufe' => $cufe,
                    'fe_qr_code' => $qr,
                    'fe_status' => FeStatus::Accepted,
                    'fe_sent_at' => now(),
                    'fe_accepted_at' => now(),
                ]);

                $submission->update([
                    'attempts' => $submission->attempts + 1,
                    'response_payload' => $response,
                    'response_status' => 'accepted',
                    'xml_document_key' => $xmlDocumentKey,
                    'responded_at' => now(),
                ]);
            } else {
                // DIAN rejected inline — could be Rule 90 inside the error message
                $dianErrorMsg = data_get($dianResult, 'ErrorMessage.string', '');
                if (is_array($dianErrorMsg)) {
                    $dianErrorMsg = implode('; ', $dianErrorMsg);
                }

                if ($this->isRule90Error((string) $dianErrorMsg)) {
                    $sale->update([
                        'fe_cufe' => $cufe,
                        'fe_qr_code' => $qr,
                        'fe_status' => FeStatus::Accepted,
                        'fe_sent_at' => now(),
                        'fe_accepted_at' => now(),
                    ]);

                    $submission->update([
                        'attempts' => $submission->attempts + 1,
                        'response_payload' => $response,
                        'response_status' => 'accepted_rule90',
                        'is_non_recoverable' => true,
                        'xml_document_key' => $xmlDocumentKey,
                        'responded_at' => now(),
                    ]);
                } else {
                    // Genuine DIAN rejection
                    $sale->update([
                        'fe_cufe' => $cufe,
                        'fe_status' => FeStatus::Rejected,
                        'fe_error_message' => $dianErrorMsg ?: "DIAN StatusCode: {$dianStatusCode}",
                    ]);

                    $submission->update([
                        'attempts' => $submission->attempts + 1,
                        'response_payload' => $response,
                        'response_status' => 'rejected',
                        'xml_document_key' => $xmlDocumentKey,
                        'responded_at' => now(),
                    ]);
                }
            }

            // Keep CheckDianStatusJob only as a fallback for documents that
            // were sent (DIAN connection OK) but validation was inconclusive.
            if ($xmlDocumentKey && ! $dianIsValid && $dianStatusCode !== '99') {
                CheckDianStatusJob::dispatch('invoice', $sale->id, $xmlDocumentKey, $tenant->id)
                    ->delay(now()->addSeconds(60));
            }

        } catch (Throwable $e) {
            $submission->increment('attempts');

            $isRule90 = $this->isRule90Error($e->getMessage());
            $isNonRecoverable = $isRule90 || $this->isNonRecoverableError($e);

            if ($isRule90) {
                $cufe = $this->extractCufeFromError($e->getMessage());

                $sale->update([
                    'fe_status' => FeStatus::Accepted,
                    'fe_accepted_at' => now(),
                    'fe_cufe' => $cufe,
                ]);

                $submission->update([
                    'response_status' => 'accepted_rule90',
                    'is_non_recoverable' => true,
                    'response_payload' => ['error' => $e->getMessage(), 'rule90' => true],
                    'responded_at' => now(),
                ]);

                return;
            }

            $isTransmissionFailure = $e instanceof ConnectionException || ! $isNonRecoverable;

            $sale->update([
                'fe_status' => $isTransmissionFailure ? FeStatus::Contingency : FeStatus::Rejected,
                'fe_error_message' => $e->getMessage(),
            ]);

            $submission->update([
                'response_status' => $isTransmissionFailure ? 'contingency' : 'rejected',
                'is_non_recoverable' => $isNonRecoverable,
                'response_payload' => ['error' => $e->getMessage()],
                'responded_at' => now(),
            ]);

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

    private function recordSubmission(Sale $sale, array $payload): FeSubmission
    {
        return $sale->tenant->feSubmissions()->create([
            'document_type' => 'invoice',
            'document_id' => $sale->id,
            'attempts' => 0,
            'request_payload' => $payload,
            'submitted_at' => now(),
        ]);
    }
}
