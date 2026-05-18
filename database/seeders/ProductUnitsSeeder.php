<?php

namespace Database\Seeders;

use App\Models\ProductUnit;
use Illuminate\Database\Seeder;

class ProductUnitsSeeder extends Seeder
{
    public function run(): void
    {
        $units = [
            ['name' => 'Unidad', 'code' => 'und', 'kind' => 'minimum', 'allows_decimals' => false],
            ['name' => 'Mililitro', 'code' => 'ml', 'kind' => 'minimum', 'allows_decimals' => true],
            ['name' => 'Gramo', 'code' => 'g', 'kind' => 'minimum', 'allows_decimals' => true],
            ['name' => 'Miligramo', 'code' => 'mg', 'kind' => 'minimum', 'allows_decimals' => true],
            ['name' => 'Blister', 'code' => 'bls', 'kind' => 'package', 'allows_decimals' => false],
            ['name' => 'Caja', 'code' => 'caj', 'kind' => 'package', 'allows_decimals' => false],
            ['name' => 'Frasco', 'code' => 'fra', 'kind' => 'package', 'allows_decimals' => false],
            ['name' => 'Tubo', 'code' => 'tub', 'kind' => 'package', 'allows_decimals' => false],
            ['name' => 'Ampolla', 'code' => 'amp', 'kind' => 'package', 'allows_decimals' => false],
            ['name' => 'Sobre', 'code' => 'sob', 'kind' => 'package', 'allows_decimals' => false],
            ['name' => 'Tira', 'code' => 'tir', 'kind' => 'package', 'allows_decimals' => false],
        ];

        foreach ($units as $unit) {
            ProductUnit::firstOrCreate(['code' => $unit['code']], $unit);
        }
    }
}
