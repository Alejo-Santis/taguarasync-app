<?php

namespace App\Actions\Purchases;

use App\Enums\PurchaseRadianStatus;
use App\Models\PurchaseReceipt;
use App\Services\Fe\NextpymeClient;
use Throwable;

class ValidatePurchaseReceiptRadian
{
    public function __construct(private readonly NextpymeClient $client) {}

    public function execute(PurchaseReceipt $receipt): PurchaseReceipt
    {
        $receipt->loadMissing(['supplier', 'tenant.feConfig']);

        try {
            $response = $this->client
                ->forTenant((string) $receipt->tenant?->feConfig?->api_token)
                ->validatePurchaseDocument($this->payload($receipt));

            $status = $this->statusFromResponse($response);

            $receipt->forceFill([
                'radian_status' => $status,
                'radian_checked_at' => now(),
                'radian_response' => $response,
                'radian_error_message' => $status === PurchaseRadianStatus::Rejected
                    ? $this->messageFromResponse($response)
                    : null,
            ])->save();
        } catch (Throwable $e) {
            $receipt->forceFill([
                'radian_status' => PurchaseRadianStatus::Error,
                'radian_checked_at' => now(),
                'radian_response' => null,
                'radian_error_message' => $e->getMessage(),
            ])->save();
        }

        return $receipt->refresh();
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(PurchaseReceipt $receipt): array
    {
        return [
            'document_number' => $receipt->document_number,
            'document_date' => $receipt->document_date?->toDateString(),
            'supplier' => [
                'nit' => $receipt->supplier?->nit,
                'name' => $receipt->supplier?->name,
            ],
            'totals' => [
                'subtotal' => $receipt->subtotal,
                'tax_total' => $receipt->tax_total,
                'total' => $receipt->total,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $response
     */
    private function statusFromResponse(array $response): PurchaseRadianStatus
    {
        $rawStatus = strtolower((string) ($response['status'] ?? $response['state'] ?? $response['validation_status'] ?? ''));
        $isValid = $response['valid'] ?? $response['is_valid'] ?? null;

        if ($isValid === true || in_array($rawStatus, ['valid', 'validated', 'accepted', 'ok', 'success'], true)) {
            return PurchaseRadianStatus::Validated;
        }

        if ($isValid === false || in_array($rawStatus, ['rejected', 'invalid', 'not_found', 'failed'], true)) {
            return PurchaseRadianStatus::Rejected;
        }

        return PurchaseRadianStatus::Pending;
    }

    /**
     * @param  array<string, mixed>  $response
     */
    private function messageFromResponse(array $response): ?string
    {
        return $response['message']
            ?? $response['error']
            ?? $response['description']
            ?? 'La validación RADIAN no fue aceptada.';
    }
}
