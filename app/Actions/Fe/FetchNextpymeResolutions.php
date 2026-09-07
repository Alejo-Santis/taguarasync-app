<?php

namespace App\Actions\Fe;

use App\Models\Tenant;
use App\Services\Fe\NextpymeClient;
use Throwable;

class FetchNextpymeResolutions
{
    public function __construct(
        private readonly NextpymeClient $client,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function execute(Tenant $tenant): array
    {
        $feConfig = $tenant->feConfig;
        $token = $feConfig?->api_token ?: (string) config('fe.api_token');

        if (! $token) {
            return [
                'ok' => false,
                'message' => 'No hay token API configurado.',
                'resolutions' => [],
            ];
        }

        try {
            $response = $this->client->forTenant($token)->getResolutions();
        } catch (Throwable $e) {
            return [
                'ok' => false,
                'message' => $e->getMessage(),
                'resolutions' => [],
            ];
        }

        $items = $this->extractList($response);

        return [
            'ok' => true,
            'message' => count($items) === 0
                ? 'NextPyme no tiene resoluciones registradas para esta empresa.'
                : count($items).' resolución(es) encontradas en NextPyme.',
            'resolutions' => array_map($this->normalize(...), $items),
        ];
    }

    /**
     * @param  array<mixed>  $response
     * @return array<int, array<string, mixed>>
     */
    private function extractList(array $response): array
    {
        if (array_is_list($response)) {
            return $response;
        }

        foreach (['data', 'resolutions', 'result'] as $key) {
            if (isset($response[$key]) && is_array($response[$key])) {
                return $response[$key];
            }
        }

        return [];
    }

    /**
     * NextPyme identifica el tipo de documento por type_document_id (1=Factura,
     * 4=Nota crédito, 5=Nota débito — mismo mapeo que config('fe.map.doc_types')).
     * Solo esos tres tipos son soportados hoy por fe_resolutions; el resto
     * (nómina, documento soporte, POS, exportación, contingencia) se muestra
     * pero no se puede importar directamente.
     *
     * @param  array<string, mixed>  $raw
     * @return array<string, mixed>
     */
    private function normalize(array $raw): array
    {
        $typeDocumentId = (int) ($raw['type_document_id'] ?? 0);
        $typeByDocId = array_flip(config('fe.map.doc_types'));
        $type = $typeByDocId[$typeDocumentId] ?? null;

        return [
            'nextpyme_id' => $raw['id'] ?? null,
            'type_document_id' => $typeDocumentId,
            'type' => $type,
            'supported' => $type !== null,
            'prefix' => $raw['prefix'] ?? null,
            'resolution_number' => $raw['resolution'] ?? $raw['resolution_number'] ?? null,
            'resolution_date' => $raw['resolution_date'] ?? null,
            'technical_key' => $raw['technical_key'] ?? null,
            'from_number' => isset($raw['from']) ? (int) $raw['from'] : null,
            'to_number' => isset($raw['to']) ? (int) $raw['to'] : null,
            'valid_from' => $raw['date_from'] ?? null,
            'valid_until' => $raw['date_to'] ?? null,
            'current_number' => isset($raw['number']) ? (int) $raw['number'] : null,
        ];
    }
}
