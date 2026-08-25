<?php

namespace App\Actions\Fe;

use App\Enums\FeStatus;
use App\Exceptions\FeResolutionExhaustedException;
use App\Jobs\CheckDianStatusJob;
use App\Models\FeResolution;
use App\Models\FeSubmission;
use App\Models\Sale;
use App\Models\Tenant;
use App\Services\Fe\InvoicePayloadBuilder;
use App\Services\Fe\NextpymeClient;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Log;
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

        // Reuse the number already reserved on a prior attempt (retry of the same
        // sale) instead of consuming a new one — keeps the DIAN consecutive stable
        // across retries so a resend of an already-accepted document is caught by
        // the Rule 90 detector below instead of producing a second accepted document.
        if ($sale->fe_resolution_id && $sale->invoice_number) {
            $resolution = FeResolution::withoutGlobalScopes()->find($sale->fe_resolution_id);
            $invoiceNumber = $sale->invoice_number;
        } else {
            $candidates = FeResolution::withoutGlobalScopes()
                ->where('tenant_id', $tenant->id)
                ->where('type', 'invoice')
                ->where('is_active', true)
                ->where('valid_until', '>=', now()->toDateString())
                ->where('environment', $feConfig?->environment?->value ?? 'test')
                ->orderBy('valid_from')
                ->orderBy('id')
                ->get();

            if ($candidates->isEmpty()) {
                $sale->update(['fe_status' => FeStatus::NotApplicable]);
                $this->recordNotApplicable($sale, 'No hay una resolución DIAN activa configurada para facturas.');

                return;
            }

            $resolution = $candidates->first(fn (FeResolution $r): bool => $r->hasRemainingNumbers());

            if (! $resolution) {
                $this->markResolutionExhausted($sale, 'Todas las resoluciones activas de facturación están agotadas. Activa una nueva resolución.');

                return;
            }

            try {
                $invoiceNumber = $resolution->consumeNextNumber();
            } catch (FeResolutionExhaustedException $e) {
                // Race: another process consumed the last number between our
                // check above and the row lock inside consumeNextNumber().
                $this->markResolutionExhausted($sale, $e->getMessage());

                return;
            }

            $sale->update([
                'invoice_prefix' => $resolution->prefix,
                'invoice_number' => $invoiceNumber,
                'fe_resolution_id' => $resolution->id,
            ]);
        }

        $sale->update([
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

            if ($isNonRecoverable) {
                Log::error('[FE] Error no-recuperable en emisión de factura', [
                    'sale_id' => $sale->id,
                    'error' => $e->getMessage(),
                ]);
            }

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

    private function markResolutionExhausted(Sale $sale, string $message): void
    {
        Log::critical('[FE] Resolución agotada al emitir factura', [
            'sale_id' => $sale->id,
            'error' => $message,
        ]);

        $sale->update([
            'fe_status' => FeStatus::Contingency,
            'fe_error_message' => $message,
        ]);
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

    /**
     * Leaves an auditable trail for a sale that was never actually
     * transmitted, so it doesn't disappear silently from /fe/submissions.
     */
    private function recordNotApplicable(Sale $sale, string $reason): void
    {
        $sale->tenant->feSubmissions()->create([
            'document_type' => 'invoice',
            'document_id' => $sale->id,
            'attempts' => 0,
            'response_status' => 'not_applicable',
            'response_payload' => ['error' => $reason],
            'responded_at' => now(),
        ]);
    }
}
