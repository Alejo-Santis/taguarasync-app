<?php

namespace App\Services\Fe;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class NextpymeClient
{
    /**
     * Nextpyme API uses /ubl2.1/ prefix for document submission endpoints.
     * Status and utility endpoints do NOT use the /ubl2.1/ prefix.
     * Source: https://developers.nextpyme.plus/docs/home
     */
    private string $baseUrl;

    private string $ublPrefix;

    private string $globalToken;

    public function __construct()
    {
        $this->baseUrl = rtrim((string) config('fe.api_url'), '/');
        $this->ublPrefix = '/'.trim((string) config('fe.ubl_prefix', '/ubl2.1'), '/');
        $this->globalToken = (string) config('fe.api_token');
    }

    /**
     * Returns a client instance using the tenant-specific token if provided,
     * falling back to the global .env token.
     */
    public function forTenant(string $tenantToken): static
    {
        $clone = clone $this;
        $clone->globalToken = $tenantToken ?: $this->globalToken;

        return $clone;
    }

    public function createInvoice(array $payload): array
    {
        return $this->post($this->ublPath('/invoice'), $payload);
    }

    public function createCreditNote(array $payload): array
    {
        return $this->post($this->ublPath('/credit-note'), $payload);
    }

    public function createDebitNote(array $payload): array
    {
        return $this->post($this->ublPath('/debit-note'), $payload);
    }

    public function getDocumentStatus(string $xmlDocumentKey): array
    {
        // Status endpoint does NOT use /ubl2.1/ prefix
        return $this->post("/status/document/{$xmlDocumentKey}", [
            'sendmail' => false,
            'is_payroll' => false,
        ]);
    }

    public function validatePurchaseDocument(array $payload): array
    {
        $path = (string) config('fe.purchase_validation_path');

        if ($path === '') {
            throw new RuntimeException('No hay endpoint de validación de compras configurado. Define FE_PURCHASE_VALIDATION_PATH cuando NextPyme confirme la ruta RADIAN para documentos de proveedor.');
        }

        return $this->post($path, $payload);
    }

    private function post(string $path, array $payload): array
    {
        if (empty($this->globalToken)) {
            throw new RuntimeException('No hay token FE configurado. Agrégalo en Configuración → Facturación electrónica o en FE_API_TOKEN del .env.');
        }

        $response = Http::withToken($this->globalToken)
            ->timeout(30)
            ->connectTimeout(10)
            ->retry(2, 1000, throw: false)
            ->post("{$this->baseUrl}{$path}", $payload);

        $this->assertSuccessful($response, $path);

        return $response->json() ?? [];
    }

    private function ublPath(string $path): string
    {
        return $this->ublPrefix.'/'.ltrim($path, '/');
    }

    private function assertSuccessful(Response $response, string $path): void
    {
        if ($response->failed()) {
            $body = $response->json();
            $message = $body['message'] ?? $body['error'] ?? "Error HTTP {$response->status()} en {$path}";

            throw new RuntimeException("[FE API] {$message}");
        }
    }
}
