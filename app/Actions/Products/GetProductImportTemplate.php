<?php

namespace App\Actions\Products;

class GetProductImportTemplate
{
    /** @return list<string> */
    public static function columns(): array
    {
        return [
            'nombre_comercial',
            'nombre_generico',
            'codigo_interno',
            'codigo_barras',
            'laboratorio',
            'categoria',
            'principio_activo',
            'forma_farmaceutica',
            'concentracion',
            'unidad_minima_codigo',
            'precio_compra',
            'precio_venta',
            'iva_porcentaje',
            'es_controlado',
            'estado',
            'presentacion_nombre',
            'presentacion_unidad_codigo',
            'presentacion_cantidad',
            'presentacion_precio',
        ];
    }

    public function execute(): string
    {
        $example = [
            'Acetaminofen 500mg',
            'Acetaminofen',
            'ACET-500',
            '7700000001234',
            'Genfar',
            'Analgesicos',
            'Acetaminofen',
            'Tableta',
            '500mg',
            'und',
            '180',
            '300',
            '0',
            'no',
            'activo',
            'Unidad',
            'und',
            '1',
            '300',
        ];

        $output = fopen('php://temp', 'r+');

        fwrite($output, "\xEF\xBB\xBF");
        fputcsv($output, self::columns());
        fputcsv($output, $example);

        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);

        return $csv ?: '';
    }
}
