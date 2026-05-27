<?php

namespace App\Actions\Inventory;

use App\Enums\ProductStatus;
use App\Models\Product;
use Illuminate\Http\UploadedFile;

class ParseOpeningImport
{
    private const EXPECTED_HEADERS = [
        'codigo_interno',
        'nombre_producto',
        'presentacion',
        'no_lote',
        'fecha_vencimiento',
        'cantidad',
        'costo_unitario',
    ];

    /**
     * @return array{
     *     rows: array<int, array<string, mixed>>,
     *     valid_count: int,
     *     error_count: int,
     *     skipped_count: int,
     *     header_error: string|null
     * }
     */
    public function execute(UploadedFile $file): array
    {
        $handle = fopen($file->getRealPath(), 'r');

        if ($handle === false) {
            return $this->fatalError('No se pudo leer el archivo.');
        }

        $rows = [];
        $headerFound = false;
        $columnMap = [];
        $lineNumber = 0;

        // Build product lookup map keyed by internal_code (lowercase)
        $products = Product::query()
            ->select(['id', 'commercial_name', 'internal_code', 'purchase_price'])
            ->with([
                'presentations' => fn ($q) => $q
                    ->where('is_active', true)
                    ->select(['id', 'product_id', 'name']),
            ])
            ->where('status', ProductStatus::Active)
            ->get()
            ->keyBy(fn (Product $p) => mb_strtolower(trim($p->internal_code ?? '')));

        while (($raw = fgetcsv($handle)) !== false) {
            $lineNumber++;

            // Skip empty lines
            if ($raw === null || (count($raw) === 1 && trim($raw[0]) === '')) {
                continue;
            }

            $first = trim($raw[0] ?? '');

            // Skip comment rows (start with #)
            if (str_starts_with($first, '#')) {
                continue;
            }

            // Detect header row
            if (! $headerFound && mb_strtolower($first) === 'codigo_interno') {
                $headerFound = true;
                $normalized = array_map(fn ($h) => mb_strtolower(trim($h)), $raw);
                foreach (self::EXPECTED_HEADERS as $col) {
                    $pos = array_search($col, $normalized, true);
                    if ($pos === false) {
                        fclose($handle);

                        return $this->fatalError("Columna requerida no encontrada: \"{$col}\". Verifica que usas la plantilla oficial.");
                    }
                    $columnMap[$col] = $pos;
                }

                continue;
            }

            if (! $headerFound) {
                continue;
            }

            $get = fn (string $col) => trim($raw[$columnMap[$col]] ?? '');

            $internalCode = $get('codigo_interno');
            $productName = $get('nombre_producto');
            $presentationName = $get('presentacion');
            $lotNumber = $get('no_lote');
            $expiresOn = $get('fecha_vencimiento');
            $quantityRaw = $get('cantidad');
            $unitCostRaw = $get('costo_unitario');

            // Skip rows with no quantity filled in
            if ($quantityRaw === '') {
                $rows[] = $this->skippedRow($lineNumber, $productName, $presentationName);

                continue;
            }

            $quantity = (int) $quantityRaw;
            $unitCost = $unitCostRaw !== '' ? (int) round((float) $unitCostRaw) : 0;

            // Skip zero-quantity rows
            if ($quantity <= 0) {
                $rows[] = $this->skippedRow($lineNumber, $productName, $presentationName);

                continue;
            }

            $errors = [];

            // Resolve product
            $product = $internalCode !== ''
                ? $products->get(mb_strtolower($internalCode))
                : null;

            if (! $product) {
                $errors[] = "Producto no encontrado (código: \"{$internalCode}\"). Verifica el código interno.";
            }

            // Resolve presentation
            $presentation = null;
            if ($product) {
                $presentation = $product->presentations
                    ->first(fn ($p) => mb_strtolower(trim($p->name)) === mb_strtolower($presentationName));

                if (! $presentation && $product->presentations->isNotEmpty()) {
                    // Fallback to first presentation but flag as a warning
                    $presentation = $product->presentations->first();
                    $errors[] = "Presentación \"{$presentationName}\" no encontrada; se usará \"{$presentation->name}\".";
                }

                if (! $presentation) {
                    $errors[] = 'El producto no tiene presentaciones activas.';
                }
            }

            // Validate expiry date format
            if ($expiresOn !== '' && ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $expiresOn)) {
                $errors[] = 'Fecha de vencimiento debe ser YYYY-MM-DD (ej. 2026-12-31).';
                $expiresOn = '';
            }

            $status = count($errors) === 0 ? 'ok' : 'error';
            // Warnings (product found but presentation fell back) are still importable
            $hasWarningOnly = $status === 'error' && $product !== null && $presentation !== null;
            if ($hasWarningOnly) {
                $status = 'warning';
            }

            $rows[] = [
                'row' => $lineNumber,
                'status' => $status,
                'errors' => $errors,
                'product_id' => $product?->id,
                'product_presentation_id' => $presentation?->id,
                'product_name' => $product?->commercial_name ?? $productName,
                'presentation_name' => $presentation?->name ?? $presentationName,
                'internal_code' => $internalCode,
                'lot_number' => $lotNumber !== '' ? $lotNumber : 'INICIAL-'.date('Y'),
                'lot_number_auto' => $lotNumber === '',
                'expires_on' => $expiresOn,
                'quantity' => $quantity,
                'unit_cost' => $unitCost > 0 ? $unitCost : ($product?->purchase_price ?? 0),
            ];
        }

        fclose($handle);

        if (! $headerFound) {
            return $this->fatalError('No se encontró la fila de encabezados. Asegúrate de usar la plantilla oficial.');
        }

        $validCount = count(array_filter($rows, fn ($r) => in_array($r['status'], ['ok', 'warning'], true)));
        $errorCount = count(array_filter($rows, fn ($r) => $r['status'] === 'error'));
        $skippedCount = count(array_filter($rows, fn ($r) => $r['status'] === 'skipped'));

        return [
            'rows' => array_values($rows),
            'valid_count' => $validCount,
            'error_count' => $errorCount,
            'skipped_count' => $skippedCount,
            'header_error' => null,
        ];
    }

    /** @return array{rows: array<empty>, valid_count: 0, error_count: 0, skipped_count: 0, header_error: string} */
    private function fatalError(string $message): array
    {
        return [
            'rows' => [],
            'valid_count' => 0,
            'error_count' => 0,
            'skipped_count' => 0,
            'header_error' => $message,
        ];
    }

    /** @return array<string, mixed> */
    private function skippedRow(int $line, string $productName, string $presentationName): array
    {
        return [
            'row' => $line,
            'status' => 'skipped',
            'errors' => [],
            'product_id' => null,
            'product_presentation_id' => null,
            'product_name' => $productName,
            'presentation_name' => $presentationName,
            'internal_code' => '',
            'lot_number' => '',
            'lot_number_auto' => false,
            'expires_on' => '',
            'quantity' => 0,
            'unit_cost' => 0,
        ];
    }
}
