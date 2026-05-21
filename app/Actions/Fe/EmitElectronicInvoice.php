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
use Throwable;

class EmitElectronicInvoice
{
    public function __construct(
        private readonly NextpymeClient $client,
        private readonly InvoicePayloadBuilder $builder,
    ) {}

    public function execute(Sale $sale, Tenant $tenant): void
    {
        $sale->load(['items', 'customer']);

        $resolution = FeResolution::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('type', 'invoice')
            ->where('is_active', true)
            ->where('valid_until', '>=', now()->toDateString())
            ->where('environment', $tenant->fe_environment->value)
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

        $this->recordSubmission($sale, $payload);

        try {
            $response = $this->client->createInvoice($payload);

            $cufe = $response['cufe'] ?? $response['uuid'] ?? $response['id'] ?? null;
            $qr = $response['qr'] ?? $response['qrcode'] ?? null;
            $xmlDocumentKey = $response['xml_document_key'] ?? $response['xmlDocumentKey'] ?? $cufe;

            $sale->update([
                'fe_cufe' => $cufe,
                'fe_qr_code' => $qr,
                'fe_status' => FeStatus::Sent,
                'fe_sent_at' => now(),
            ]);

            $submission = $this->updateSubmissionResponse($sale, $response, 'sent');

            if ($submission && $xmlDocumentKey) {
                $submission->update(['xml_document_key' => $xmlDocumentKey]);

                CheckDianStatusJob::dispatch('invoice', $sale->id, $xmlDocumentKey, $tenant->id)
                    ->delay(now()->addSeconds(30));
            }
        } catch (Throwable $e) {
            $sale->update([
                'fe_status' => FeStatus::Rejected,
                'fe_error_message' => $e->getMessage(),
            ]);

            $this->updateSubmissionResponse($sale, ['error' => $e->getMessage()], 'rejected');

            throw $e;
        }
    }

    private function recordSubmission(Sale $sale, array $payload): void
    {
        $sale->tenant->feSubmissions()->create([
            'document_type' => 'invoice',
            'document_id' => $sale->id,
            'request_payload' => $payload,
            'submitted_at' => now(),
        ]);
    }

    private function updateSubmissionResponse(Sale $sale, array $response, string $status): ?FeSubmission
    {
        $submission = $sale->tenant->feSubmissions()
            ->where('document_type', 'invoice')
            ->where('document_id', $sale->id)
            ->latest()
            ->first();

        $submission?->update([
            'response_payload' => $response,
            'response_status' => $status,
            'responded_at' => now(),
        ]);

        return $submission;
    }
}
