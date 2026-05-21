<?php

namespace App\Jobs;

use App\Enums\FeStatus;
use App\Models\FeSubmission;
use App\Models\Sale;
use App\Services\Fe\NextpymeClient;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class CheckDianStatusJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    /** @var int[] */
    public array $backoff = [60, 120, 300, 600, 1800];

    public function __construct(
        public readonly string $documentType,
        public readonly int $documentId,
        public readonly string $xmlDocumentKey,
        public readonly int $tenantId,
    ) {}

    public function handle(NextpymeClient $client): void
    {
        try {
            $response = $client->getDocumentStatus($this->xmlDocumentKey);
        } catch (Throwable) {
            return;
        }

        FeSubmission::withoutGlobalScopes()
            ->where('document_type', $this->documentType)
            ->where('document_id', $this->documentId)
            ->where('xml_document_key', $this->xmlDocumentKey)
            ->latest()
            ->first()
            ?->update([
                'response_payload' => $response,
                'response_status' => 'status_checked',
                'responded_at' => now(),
            ]);

        $dianStatus = $response['dian_code'] ?? $response['status'] ?? null;

        if ($this->documentType === 'invoice') {
            $this->updateSaleStatus($dianStatus);
        }
    }

    private function updateSaleStatus(?string $dianStatus): void
    {
        $sale = Sale::withoutGlobalScopes()->find($this->documentId);

        if (! $sale) {
            return;
        }

        $feStatus = match (true) {
            in_array($dianStatus, ['00', 'accepted', 'ACCEPTED', '0']) => FeStatus::Accepted,
            in_array($dianStatus, ['99', 'rejected', 'REJECTED']) => FeStatus::Rejected,
            default => null,
        };

        if ($feStatus !== null) {
            $sale->update([
                'fe_status' => $feStatus,
                'fe_accepted_at' => $feStatus === FeStatus::Accepted ? now() : null,
            ]);
        }
    }
}
