<?php

namespace Database\Seeders;

use App\Models\DianMunicipality;
use Illuminate\Database\Seeder;

class DianMunicipalitiesFromCsvSeeder extends Seeder
{
    public function run(): void
    {
        // Build department name map from departments.csv
        // Format: seq_id | country_id | name | dept_dane_code
        $departments = [];
        $deptFile = public_path('csv/departments.csv');
        if (file_exists($deptFile)) {
            foreach (file($deptFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
                $cols = explode("\t", $line);
                if (count($cols) >= 4) {
                    $deptSeqId = trim($cols[0]);
                    $deptName = trim($cols[2]);
                    $deptDaneCode = trim($cols[3]);
                    $departments[$deptSeqId] = ['name' => $deptName, 'code' => $deptDaneCode];
                }
            }
        }

        // Parse municipalities.csv
        // Format: seq_id | dept_seq_id | name | dane_code | nextpyme_api_id
        $municipalities = [];
        $munFile = public_path('csv/municipalities.csv');
        if (! file_exists($munFile)) {
            $this->command->warn('municipalities.csv not found in public/csv/');

            return;
        }

        foreach (file($munFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            $cols = explode("\t", $line);
            if (count($cols) < 5) {
                continue;
            }

            $deptSeqId = trim($cols[1]);
            $name = trim($cols[2]);
            $daneCode = trim($cols[3]);
            $apiId = (int) trim($cols[4]);

            $dept = $departments[$deptSeqId] ?? null;

            $municipalities[] = [
                'code' => $daneCode,
                'api_id' => $apiId,
                'name' => $name,
                'department_code' => $dept['code'] ?? substr($daneCode, 0, 2),
                'department_name' => $dept['name'] ?? 'Sin departamento',
            ];
        }

        // Upsert in chunks for performance
        foreach (array_chunk($municipalities, 200) as $chunk) {
            DianMunicipality::upsert(
                $chunk,
                ['code'],
                ['api_id', 'name', 'department_code', 'department_name']
            );
        }

        $this->command->info('Municipios cargados: '.count($municipalities));
    }
}
