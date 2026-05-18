<?php

namespace Database\Seeders;

use App\Models\Laboratory;
use App\Models\Tenant;
use Illuminate\Database\Seeder;

class ColombianLaboratoriesSeeder extends Seeder
{
    public function run(): void
    {
        $tenantId = Tenant::first()?->id;

        if (! $tenantId) {
            return;
        }

        $laboratories = [
            ['name' => 'Genfar', 'nit' => '800099953-1', 'country' => 'CO'],
            ['name' => 'MK (Tecnoquimicas)', 'nit' => '890300144-6', 'country' => 'CO'],
            ['name' => 'Bayer Colombia', 'nit' => '860015696-3', 'country' => 'CO'],
            ['name' => 'Novartis Colombia', 'nit' => '900008327-9', 'country' => 'CO'],
            ['name' => 'Pfizer Colombia', 'nit' => '830021192-7', 'country' => 'CO'],
            ['name' => 'Sanofi Colombia', 'nit' => '800155765-4', 'country' => 'CO'],
            ['name' => 'Abbott Laboratories', 'nit' => '800227497-7', 'country' => 'CO'],
            ['name' => 'Roche Colombia', 'nit' => '860008643-0', 'country' => 'CO'],
            ['name' => 'GlaxoSmithKline Colombia', 'nit' => '800251193-1', 'country' => 'CO'],
            ['name' => 'Lafrancol', 'nit' => '890315394-1', 'country' => 'CO'],
            ['name' => 'Eurofarma Colombia', 'nit' => '900399610-7', 'country' => 'CO'],
            ['name' => 'Procaps', 'nit' => '890102122-5', 'country' => 'CO'],
            ['name' => 'Chalver de Colombia', 'nit' => '800069735-9', 'country' => 'CO'],
            ['name' => 'Merck Colombia', 'nit' => '860004840-1', 'country' => 'CO'],
            ['name' => 'AstraZeneca Colombia', 'nit' => '800206661-3', 'country' => 'CO'],
            ['name' => 'Boehringer Ingelheim Colombia', 'nit' => '860002171-6', 'country' => 'CO'],
            ['name' => 'Coaspharma', 'nit' => '890309225-8', 'country' => 'CO'],
            ['name' => 'BIOGEN LABS (marca blanca)', 'nit' => null, 'country' => 'CO'],
        ];

        foreach ($laboratories as $lab) {
            Laboratory::firstOrCreate(
                ['tenant_id' => $tenantId, 'name' => $lab['name']],
                [...$lab, 'is_active' => true]
            );
        }
    }
}
