<?php

namespace App\Actions\Settings;

class GetPriceListImportTemplate
{
    /** @return list<string> */
    public static function columns(): array
    {
        return ['codigo_interno', 'precio_especial'];
    }

    public function execute(): string
    {
        $example = ['ACET-500', '2500'];

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
