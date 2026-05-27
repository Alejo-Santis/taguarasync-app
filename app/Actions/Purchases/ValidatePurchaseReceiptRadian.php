<?php

namespace App\Actions\Purchases;

use App\Enums\PurchaseRadianStatus;
use App\Models\PurchaseReceipt;
use App\Services\Fe\NextpymeClient;
use RuntimeException;
use Throwable;

class ValidatePurchaseReceiptRadian
{
    public function __construct(private readonly NextpymeClient $client) {}

    public function execute(PurchaseReceipt $receipt): PurchaseReceipt
    {
        $receipt->loadMissing(['supplier', 'tenant.feConfig']);

        $cufe = trim((string) $receipt->supplier_cufe);

        if ($cufe === '') {
            $receipt->forceFill([
                'radian_status' => PurchaseRadianStatus::Pending,
                'radian_checked_at' => now(),
                'radian_response' => null,
                'radian_error_message' => 'Ingresa el CUFE del proveedor en el documento de compra para consultar RADIAN.',
            ])->save();

            return $receipt->refresh();
        }

        $client = $this->client->forTenant((string) $receipt->tenant?->feConfig?->api_token);

        try {
            // 1. Consultar eventos existentes en RADIAN para este CUFE
            $eventsResponse = $client->checkRadianEvents($cufe);
            $existingEvents = $this->parseEvents($eventsResponse);

            // 2. Si ya tiene los eventos mínimos requeridos (030 + 032) → validado
            if ($this->hasRequiredEvents($existingEvents)) {
                $receipt->forceFill([
                    'radian_status' => PurchaseRadianStatus::Validated,
                    'radian_checked_at' => now(),
                    'radian_response' => $eventsResponse,
                    'radian_error_message' => null,
                ])->save();

                return $receipt->refresh();
            }

            // 3. Si no tiene evento 030 (acuse de recibo), enviarlo primero
            if (! $this->hasEvent($existingEvents, '030')) {
                $acuseResponse = $client->sendRadianEvent($cufe, eventId: 1);
                $this->assertEventAccepted($acuseResponse, '030');
            }

            // 4. Enviar evento 032 (recibo del bien/servicio)
            $reciboResponse = $client->sendRadianEvent($cufe, eventId: 3);
            $this->assertEventAccepted($reciboResponse, '032');

            $receipt->forceFill([
                'radian_status' => PurchaseRadianStatus::Validated,
                'radian_checked_at' => now(),
                'radian_response' => $reciboResponse,
                'radian_error_message' => null,
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
     * @param  array<string, mixed>  $response
     * @return array<int, array{dian_code: string, cude: string, date: string}>
     */
    private function parseEvents(array $response): array
    {
        if (! ($response['success'] ?? false)) {
            return [];
        }

        $raw = $response['events'] ?? [];

        return collect($raw)->map(function (array|object $e): array {
            $arr = is_array($e) ? $e : (array) $e;

            return [
                'dian_code' => (string) ($arr['dian_code'] ?? ''),
                'cude' => (string) ($arr['cude'] ?? ''),
                'date' => (string) ($arr['date'] ?? ''),
            ];
        })->all();
    }

    /**
     * @param  array<int, array{dian_code: string}>  $events
     */
    private function hasEvent(array $events, string $code): bool
    {
        return collect($events)->contains('dian_code', $code);
    }

    /**
     * @param  array<int, array{dian_code: string}>  $events
     */
    private function hasRequiredEvents(array $events): bool
    {
        return $this->hasEvent($events, '030') && $this->hasEvent($events, '032');
    }

    /**
     * @param  array<string, mixed>  $response
     *
     * @throws RuntimeException
     */
    private function assertEventAccepted(array $response, string $eventCode): void
    {
        // La respuesta puede venir anidada (estructura SOAP de la DIAN) o flat
        $result = $response['ResponseDian']['Envelope']['Body']['SendEventUpdateStatusResponse']['SendEventUpdateStatusResult']
            ?? $response['result']
            ?? $response;

        $isValid = $result['IsValid'] ?? $result['is_valid'] ?? null;

        if ($isValid !== null) {
            $valid = filter_var($isValid, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

            if ($valid === false) {
                $error = $result['ErrorMessage'] ?? $response['message'] ?? "RADIAN rechazó el evento {$eventCode}.";
                $errorText = is_array($error) ? implode(' | ', $error) : (string) $error;

                throw new RuntimeException("[RADIAN {$eventCode}] {$errorText}");
            }
        }
        // Sin IsValid → no hubo excepción HTTP, se asume éxito
    }
}
