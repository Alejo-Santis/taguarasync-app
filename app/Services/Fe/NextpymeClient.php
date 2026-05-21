<?php

namespace App\Services\Fe;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class NextpymeClient
{
    private string $baseUrl;

    private string $token;

    public function __construct()
    {
        $this->baseUrl = rtrim((string) config('fe.api_url'), '/');
        $this->token = (string) config('fe.api_token');
    }

    public function createInvoice(array $payload): array
    {
        return $this->post('/invoice', $payload);
    }

    public function createCreditNote(array $payload): array
    {
        return $this->post('/credit-note', $payload);
    }

    public function getDocumentStatus(string $xmlDocumentKey): array
    {
        return $this->post("/status/document/{$xmlDocumentKey}", [
            'sendmail' => false,
            'is_payroll' => false,
        ]);
    }

    private function post(string $path, array $payload): array
    {
        if (empty($this->token)) {
            throw new RuntimeException('FE_API_TOKEN no está configurado.');
        }

        $response = Http::withToken($this->token)
            ->timeout(30)
            ->connectTimeout(10)
            ->retry(2, 1000, throw: false)
            ->post("{$this->baseUrl}{$path}", $payload);

        $this->assertSuccessful($response, $path);

        return $response->json() ?? [];
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
