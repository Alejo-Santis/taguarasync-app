<?php

namespace App\Actions\Fe;

use App\Models\Tenant;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Throwable;

class TestNextpymeConnection
{
    /**
     * @return array<string, mixed>
     */
    public function execute(Tenant $tenant, ?string $providedToken = null): array
    {
        $startedAt = microtime(true);
        $baseUrl = rtrim((string) config('fe.api_url'), '/');
        $ublPrefix = rtrim((string) config('fe.ubl_prefix'), '/');
        $feConfig = $tenant->feConfig;
        $token = $providedToken ?: ($feConfig?->api_token ?: (string) config('fe.api_token'));
        $tokenSource = $providedToken
            ? 'temporal'
            : ($feConfig?->api_token ? 'tenant' : 'global');

        if ($baseUrl === '') {
            return $this->result(false, 'FE_API_URL no está configurado.', $startedAt, [
                'base_url' => null,
                'token_source' => $tokenSource,
            ]);
        }

        if (! $token) {
            return $this->result(false, 'No hay token API configurado.', $startedAt, [
                'base_url' => $baseUrl,
                'token_source' => 'missing',
            ]);
        }

        try {
            $response = Http::withToken($token)
                ->timeout(15)
                ->connectTimeout(6)
                ->retry(1, 400, throw: false)
                ->get("{$baseUrl}/{$ublPrefix}/config/company");

            if ($response->successful()) {
                $company = $response->json() ?? [];

                return $this->result(true, 'Conexión exitosa con Nextpyme.', $startedAt, [
                    'base_url' => $baseUrl,
                    'checked_endpoint' => "{$baseUrl}/{$ublPrefix}/config/company",
                    'status_code' => $response->status(),
                    'token_source' => $tokenSource,
                    'company' => [
                        'name' => $company['business_name'] ?? $company['name'] ?? null,
                        'identification_number' => $company['identification_number'] ?? null,
                    ],
                ]);
            }

            if ($response->status() === 404) {
                return $this->result(true, 'API online; el endpoint de prueba /config/company no está expuesto en esta instalación de Nextpyme.', $startedAt, [
                    'base_url' => $baseUrl,
                    'checked_endpoint' => "{$baseUrl}/{$ublPrefix}/config/company",
                    'status_code' => $response->status(),
                    'token_source' => $tokenSource,
                ]);
            }

            $body = $response->json();
            $message = $body['message'] ?? $body['error'] ?? "Nextpyme respondió HTTP {$response->status()}.";

            return $this->result(false, $message, $startedAt, [
                'base_url' => $baseUrl,
                'checked_endpoint' => "{$baseUrl}/{$ublPrefix}/config/company",
                'status_code' => $response->status(),
                'token_source' => $tokenSource,
            ]);
        } catch (ConnectionException $exception) {
            return $this->result(false, 'No se pudo conectar con Nextpyme: '.$exception->getMessage(), $startedAt, [
                'base_url' => $baseUrl,
                'checked_endpoint' => "{$baseUrl}/{$ublPrefix}/config/company",
                'token_source' => $tokenSource,
            ]);
        } catch (Throwable $exception) {
            return $this->result(false, 'Error verificando la conexión: '.$exception->getMessage(), $startedAt, [
                'base_url' => $baseUrl,
                'checked_endpoint' => "{$baseUrl}/{$ublPrefix}/config/company",
                'token_source' => $tokenSource,
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private function result(bool $ok, string $message, float $startedAt, array $extra = []): array
    {
        return [
            'ok' => $ok,
            'message' => $message,
            'checked_at' => now()->format('Y-m-d H:i:s'),
            'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            ...$extra,
        ];
    }
}
