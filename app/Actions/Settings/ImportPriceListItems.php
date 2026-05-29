<?php

namespace App\Actions\Settings;

use App\Models\PriceList;
use App\Models\PriceListItem;
use App\Models\Product;
use Illuminate\Support\Facades\DB;

class ImportPriceListItems
{
    /**
     * @return array{errors: list<array{row: int, message: string}>, upserted: int}
     */
    public function execute(PriceList $priceList, string $filePath): array
    {
        $parseResult = $this->parseCsv($filePath);

        if (isset($parseResult['error'])) {
            return ['errors' => [['row' => 0, 'message' => $parseResult['error']]], 'upserted' => 0];
        }

        $rows = $parseResult['rows'];

        if (empty($rows)) {
            return ['errors' => [['row' => 0, 'message' => 'El archivo no contiene filas de datos.']], 'upserted' => 0];
        }

        if (count($rows) > 2000) {
            return ['errors' => [['row' => 0, 'message' => 'El archivo supera el límite de 2 000 filas por importación.']], 'upserted' => 0];
        }

        $productsByCode = Product::where('tenant_id', $priceList->tenant_id)
            ->pluck('id', 'internal_code')
            ->mapWithKeys(fn ($id, $code) => [mb_strtolower($code) => $id])
            ->all();

        [$errors, $resolved] = $this->validateRows($rows, $productsByCode);

        if (! empty($errors)) {
            return ['errors' => $errors, 'upserted' => 0];
        }

        $upserted = 0;

        DB::transaction(function () use ($priceList, $resolved, &$upserted): void {
            foreach ($resolved as $item) {
                PriceListItem::updateOrCreate(
                    ['price_list_id' => $priceList->id, 'product_id' => $item['product_id']],
                    ['tenant_id' => $priceList->tenant_id, 'sale_price' => $item['sale_price']]
                );
                $upserted++;
            }
        });

        return ['errors' => [], 'upserted' => $upserted];
    }

    /**
     * @return array{rows?: list<array<string, string>>, error?: string}
     */
    private function parseCsv(string $filePath): array
    {
        $handle = fopen($filePath, 'r');

        if ($handle === false) {
            return ['error' => 'No se pudo leer el archivo.'];
        }

        $firstBytes = fread($handle, 3);
        if ($firstBytes !== "\xEF\xBB\xBF") {
            rewind($handle);
        }

        $dataStart = ftell($handle);

        $firstLine = fgets($handle);
        if ($firstLine === false) {
            fclose($handle);

            return ['error' => 'El archivo está vacío.'];
        }

        $delimiter = substr_count($firstLine, ';') > substr_count($firstLine, ',') ? ';' : ',';

        fseek($handle, $dataStart);

        $rawHeaders = fgetcsv($handle, 0, $delimiter);

        if ($rawHeaders === false || $rawHeaders === null) {
            fclose($handle);

            return ['error' => 'No se pudo leer la cabecera del archivo.'];
        }

        $headers = array_map(fn ($h) => mb_strtolower(trim((string) $h)), $rawHeaders);

        $missing = array_diff(GetPriceListImportTemplate::columns(), $headers);
        if (! empty($missing)) {
            fclose($handle);

            return ['error' => 'Columnas faltantes: '.implode(', ', $missing).'. Usa la plantilla oficial.'];
        }

        $rows = [];
        while (($values = fgetcsv($handle, 0, $delimiter)) !== false) {
            if ($values === [null] || implode('', $values) === '') {
                continue;
            }

            if (count($values) >= count($headers)) {
                $rows[] = array_combine($headers, array_map('trim', array_slice($values, 0, count($headers))));
            }
        }

        fclose($handle);

        return ['rows' => $rows];
    }

    /**
     * @param  list<array<string, string>>  $rows
     * @param  array<string, int>  $productsByCode
     * @return array{
     *     0: list<array{row: int, message: string}>,
     *     1: list<array{product_id: int, sale_price: int}>
     * }
     */
    private function validateRows(array $rows, array $productsByCode): array
    {
        $errors = [];
        $resolved = [];
        $seenCodes = [];
        $rowNumber = 2;

        foreach ($rows as $row) {
            $code = $row['codigo_interno'];
            $priceRaw = $row['precio_especial'];
            $codeKey = mb_strtolower($code);
            $rowErrors = [];

            if ($code === '') {
                $rowErrors[] = ['row' => $rowNumber, 'message' => 'El código interno es requerido.'];
            } elseif (! isset($productsByCode[$codeKey])) {
                $rowErrors[] = ['row' => $rowNumber, 'message' => "El código \"{$code}\" no existe en el catálogo de esta farmacia."];
            } elseif (isset($seenCodes[$codeKey])) {
                $rowErrors[] = ['row' => $rowNumber, 'message' => "El código \"{$code}\" está duplicado en el archivo."];
            }

            if ($priceRaw === '') {
                $rowErrors[] = ['row' => $rowNumber, 'message' => 'El precio especial es requerido.'];
            } elseif (! is_numeric($priceRaw) || (int) $priceRaw < 0) {
                $rowErrors[] = ['row' => $rowNumber, 'message' => 'El precio especial debe ser un número entero mayor o igual a 0.'];
            }

            if (! empty($rowErrors)) {
                array_push($errors, ...$rowErrors);
            } else {
                $seenCodes[$codeKey] = true;
                $resolved[] = [
                    'product_id' => $productsByCode[$codeKey],
                    'sale_price' => (int) $priceRaw,
                ];
            }

            $rowNumber++;
        }

        return [$errors, $resolved];
    }
}
