<?php

namespace App\Actions\Products;

use App\Enums\ProductStatus;
use App\Models\ActiveIngredient;
use App\Models\Laboratory;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductUnit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ImportProducts
{
    /**
     * @return array{errors: list<array{row: int, message: string}>, created: int}
     */
    public function execute(string $filePath): array
    {
        $parseResult = $this->parseCsv($filePath);

        if (isset($parseResult['error'])) {
            return ['errors' => [['row' => 0, 'message' => $parseResult['error']]], 'created' => 0];
        }

        $rows = $parseResult['rows'];

        if (empty($rows)) {
            return ['errors' => [['row' => 0, 'message' => 'El archivo no contiene filas de datos.']], 'created' => 0];
        }

        if (count($rows) > 500) {
            return ['errors' => [['row' => 0, 'message' => 'El archivo supera el limite de 500 productos por importacion.']], 'created' => 0];
        }

        $lookups = $this->loadLookups();
        [$errors, $resolved] = $this->validateRows($rows, $lookups);

        if (! empty($errors)) {
            return ['errors' => $errors, 'created' => 0];
        }

        $created = 0;

        DB::transaction(function () use ($resolved, &$created): void {
            foreach ($resolved as $item) {
                $this->createProduct($item);
                $created++;
            }
        });

        return ['errors' => [], 'created' => $created];
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

            return ['error' => 'El archivo esta vacio.'];
        }

        $delimiter = substr_count($firstLine, ';') > substr_count($firstLine, ',') ? ';' : ',';

        fseek($handle, $dataStart);

        $rawHeaders = fgetcsv($handle, 0, $delimiter);

        if ($rawHeaders === false || $rawHeaders === null) {
            fclose($handle);

            return ['error' => 'No se pudo leer la cabecera del archivo.'];
        }

        $headers = array_map(fn ($h) => mb_strtolower(trim((string) $h)), $rawHeaders);

        $missing = array_diff(GetProductImportTemplate::columns(), $headers);
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
     * @return array{
     *     labs: array<string, int>,
     *     categories: array<string, int>,
     *     units: array<string, int>,
     *     ingredients: array<string, int>,
     *     existingCodes: array<string, bool>
     * }
     */
    private function loadLookups(): array
    {
        return [
            'labs' => Laboratory::where('is_active', true)
                ->pluck('id', 'name')
                ->mapWithKeys(fn ($id, $name) => [mb_strtolower($name) => $id])
                ->all(),
            'categories' => ProductCategory::where('is_active', true)
                ->pluck('id', 'name')
                ->mapWithKeys(fn ($id, $name) => [mb_strtolower($name) => $id])
                ->all(),
            'units' => ProductUnit::where('is_active', true)
                ->pluck('id', 'code')
                ->mapWithKeys(fn ($id, $code) => [mb_strtolower($code) => $id])
                ->all(),
            'ingredients' => ActiveIngredient::pluck('id', 'dci_name')
                ->mapWithKeys(fn ($id, $name) => [mb_strtolower($name) => $id])
                ->all(),
            'existingCodes' => Product::pluck('internal_code')
                ->mapWithKeys(fn ($code) => [mb_strtolower($code) => true])
                ->all(),
        ];
    }

    /**
     * @param  list<array<string, string>>  $rows
     * @param  array{labs: array<string, int>, categories: array<string, int>, units: array<string, int>, ingredients: array<string, int>, existingCodes: array<string, bool>}  $lookups
     * @return array{
     *     0: list<array{row: int, message: string}>,
     *     1: list<array<string, mixed>>
     * }
     */
    private function validateRows(array $rows, array $lookups): array
    {
        $errors = [];
        $resolved = [];
        $batchCodes = [];
        $rowNumber = 2;

        foreach ($rows as $row) {
            $rowErrors = [];

            $commercialName = $row['nombre_comercial'];
            $internalCode = $row['codigo_interno'];
            $labKey = mb_strtolower($row['laboratorio']);
            $catKey = mb_strtolower($row['categoria']);
            $minUnitKey = mb_strtolower($row['unidad_minima_codigo']);
            $presUnitKey = mb_strtolower($row['presentacion_unidad_codigo']);
            $ingredientKey = mb_strtolower($row['principio_activo']);
            $estado = mb_strtolower($row['estado']);
            $esControlado = mb_strtolower($row['es_controlado']);
            $presQty = $row['presentacion_cantidad'];
            $presSalePrice = $row['presentacion_precio'];

            if ($commercialName === '') {
                $rowErrors[] = ['row' => $rowNumber, 'message' => 'El nombre comercial es requerido.'];
            } elseif (mb_strlen($commercialName) > 260) {
                $rowErrors[] = ['row' => $rowNumber, 'message' => 'El nombre comercial no puede superar 260 caracteres.'];
            }

            if ($internalCode === '') {
                $rowErrors[] = ['row' => $rowNumber, 'message' => 'El codigo interno es requerido.'];
            } elseif (mb_strlen($internalCode) > 60) {
                $rowErrors[] = ['row' => $rowNumber, 'message' => 'El codigo interno no puede superar 60 caracteres.'];
            } elseif (isset($lookups['existingCodes'][mb_strtolower($internalCode)])) {
                $rowErrors[] = ['row' => $rowNumber, 'message' => "El codigo interno \"{$internalCode}\" ya existe en el catalogo."];
            } elseif (isset($batchCodes[mb_strtolower($internalCode)])) {
                $rowErrors[] = ['row' => $rowNumber, 'message' => "El codigo interno \"{$internalCode}\" esta duplicado en este archivo."];
            }

            if ($row['laboratorio'] === '') {
                $rowErrors[] = ['row' => $rowNumber, 'message' => 'El laboratorio es requerido.'];
            } elseif (! isset($lookups['labs'][$labKey])) {
                $rowErrors[] = ['row' => $rowNumber, 'message' => "El laboratorio \"{$row['laboratorio']}\" no existe en el sistema."];
            }

            if ($row['categoria'] === '') {
                $rowErrors[] = ['row' => $rowNumber, 'message' => 'La categoria es requerida.'];
            } elseif (! isset($lookups['categories'][$catKey])) {
                $rowErrors[] = ['row' => $rowNumber, 'message' => "La categoria \"{$row['categoria']}\" no existe en el sistema."];
            }

            if ($row['unidad_minima_codigo'] === '') {
                $rowErrors[] = ['row' => $rowNumber, 'message' => 'El codigo de unidad minima es requerido.'];
            } elseif (! isset($lookups['units'][$minUnitKey])) {
                $rowErrors[] = ['row' => $rowNumber, 'message' => "El codigo de unidad \"{$row['unidad_minima_codigo']}\" no existe."];
            }

            if (! is_numeric($row['precio_compra']) || (int) $row['precio_compra'] < 0) {
                $rowErrors[] = ['row' => $rowNumber, 'message' => 'El precio de compra debe ser un numero entero mayor o igual a 0.'];
            }

            if (! is_numeric($row['precio_venta']) || (int) $row['precio_venta'] < 0) {
                $rowErrors[] = ['row' => $rowNumber, 'message' => 'El precio de venta debe ser un numero entero mayor o igual a 0.'];
            }

            if (! is_numeric($row['iva_porcentaje']) || (float) $row['iva_porcentaje'] < 0 || (float) $row['iva_porcentaje'] > 100) {
                $rowErrors[] = ['row' => $rowNumber, 'message' => 'El IVA debe ser un numero entre 0 y 100.'];
            }

            if (! in_array($esControlado, ['si', 'sí', 'no'])) {
                $rowErrors[] = ['row' => $rowNumber, 'message' => 'El campo es_controlado debe ser "si" o "no".'];
            }

            if (! in_array($estado, ['activo', 'inactivo', 'descontinuado'])) {
                $rowErrors[] = ['row' => $rowNumber, 'message' => 'El estado debe ser "activo", "inactivo" o "descontinuado".'];
            }

            if ($row['presentacion_nombre'] === '') {
                $rowErrors[] = ['row' => $rowNumber, 'message' => 'El nombre de presentacion es requerido.'];
            } elseif (mb_strlen($row['presentacion_nombre']) > 160) {
                $rowErrors[] = ['row' => $rowNumber, 'message' => 'El nombre de presentacion no puede superar 160 caracteres.'];
            }

            if ($row['presentacion_unidad_codigo'] === '') {
                $rowErrors[] = ['row' => $rowNumber, 'message' => 'El codigo de unidad de presentacion es requerido.'];
            } elseif (! isset($lookups['units'][$presUnitKey])) {
                $rowErrors[] = ['row' => $rowNumber, 'message' => "El codigo de unidad de presentacion \"{$row['presentacion_unidad_codigo']}\" no existe."];
            }

            if (! is_numeric($presQty) || (int) $presQty < 1) {
                $rowErrors[] = ['row' => $rowNumber, 'message' => 'La cantidad de presentacion debe ser un entero mayor o igual a 1.'];
            }

            if ($presSalePrice !== '' && (! is_numeric($presSalePrice) || (int) $presSalePrice < 0)) {
                $rowErrors[] = ['row' => $rowNumber, 'message' => 'El precio de presentacion debe ser un entero mayor o igual a 0.'];
            }

            if (! empty($rowErrors)) {
                array_push($errors, ...$rowErrors);
            } else {
                $batchCodes[mb_strtolower($internalCode)] = true;
                $resolved[] = [
                    'row' => $row,
                    'lab_id' => $lookups['labs'][$labKey],
                    'category_id' => $lookups['categories'][$catKey],
                    'min_unit_id' => $lookups['units'][$minUnitKey],
                    'pres_unit_id' => $lookups['units'][$presUnitKey],
                    'ingredient_id' => ($ingredientKey !== '' && isset($lookups['ingredients'][$ingredientKey]))
                        ? $lookups['ingredients'][$ingredientKey]
                        : null,
                    'is_controlled' => in_array($esControlado, ['si', 'sí']),
                    'status' => match ($estado) {
                        'inactivo' => ProductStatus::Inactive,
                        'descontinuado' => ProductStatus::Discontinued,
                        default => ProductStatus::Active,
                    },
                ];
            }

            $rowNumber++;
        }

        return [$errors, $resolved];
    }

    /**
     * @param  array{row: array<string, string>, lab_id: int, category_id: int, min_unit_id: int, pres_unit_id: int, ingredient_id: int|null, is_controlled: bool, status: ProductStatus}  $item
     */
    private function createProduct(array $item): void
    {
        $row = $item['row'];

        $product = Product::create([
            'uuid' => (string) Str::uuid(),
            'laboratory_id' => $item['lab_id'],
            'product_category_id' => $item['category_id'],
            'active_ingredient_id' => $item['ingredient_id'],
            'minimum_unit_id' => $item['min_unit_id'],
            'internal_code' => $row['codigo_interno'],
            'barcode' => $row['codigo_barras'] !== '' ? $row['codigo_barras'] : null,
            'commercial_name' => $row['nombre_comercial'],
            'generic_name' => $row['nombre_generico'] !== '' ? $row['nombre_generico'] : null,
            'pharmaceutical_form' => $row['forma_farmaceutica'] !== '' ? $row['forma_farmaceutica'] : null,
            'concentration' => $row['concentracion'] !== '' ? $row['concentracion'] : null,
            'purchase_price' => (int) $row['precio_compra'],
            'sale_price' => (int) $row['precio_venta'],
            'tax_rate' => (float) $row['iva_porcentaje'],
            'requires_invima_registration' => false,
            'is_controlled' => $item['is_controlled'],
            'status' => $item['status'],
        ]);

        $product->presentations()->create([
            'unit_id' => $item['pres_unit_id'],
            'name' => $row['presentacion_nombre'],
            'minimum_unit_quantity' => (int) $row['presentacion_cantidad'],
            'sale_price' => $row['presentacion_precio'] !== '' ? (int) $row['presentacion_precio'] : null,
            'is_default' => true,
            'is_active' => true,
        ]);
    }
}
