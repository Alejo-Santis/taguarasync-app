<?php

namespace Database\Seeders;

use App\Models\EconomicActivity;
use Illuminate\Database\Seeder;

class EconomicActivitiesSeeder extends Seeder
{
    public function run(): void
    {
        $file = public_path('csv/economic_activities.csv');
        if (! file_exists($file)) {
            $this->command->warn('economic_activities.csv not found in public/csv/');

            return;
        }

        // Format: seq_id | code | name | rete_ica_rate | is_active
        $activities = [];
        foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            $cols = explode("\t", $line);
            if (count($cols) < 3) {
                continue;
            }

            $code = trim($cols[1]);
            $name = trim($cols[2]);
            $rate = isset($cols[3]) ? (float) trim($cols[3]) : null;

            if (! $code || ! $name) {
                continue;
            }

            // Last occurrence wins for duplicate codes
            $activities[$code] = [
                'code' => $code,
                'name' => mb_substr($name, 0, 300),
                'rete_ica_rate' => $rate ?: null,
            ];
        }

        $rows = array_values($activities);

        foreach (array_chunk($rows, 100) as $chunk) {
            EconomicActivity::upsert($chunk, ['code'], ['name', 'rete_ica_rate']);
        }

        $this->command->info('Actividades económicas cargadas: '.count($rows));
    }
}
