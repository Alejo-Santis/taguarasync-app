<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\CashRegister;
use App\Models\Tenant;
use Illuminate\Database\Seeder;

class DemoCashRegisterSeeder extends Seeder
{
    public function run(): void
    {
        $tenant = Tenant::where('slug', 'farmacia-demo')->first();

        if (! $tenant) {
            return;
        }

        $branch = Branch::where('tenant_id', $tenant->id)->where('is_main', true)->first();

        $registers = [
            ['name' => 'Caja Principal',   'code' => 'CJ-01', 'is_active' => true],
            ['name' => 'Caja Secundaria',  'code' => 'CJ-02', 'is_active' => true],
        ];

        foreach ($registers as $data) {
            CashRegister::firstOrCreate(
                ['tenant_id' => $tenant->id, 'code' => $data['code']],
                [
                    ...$data,
                    'tenant_id' => $tenant->id,
                    'branch_id' => $branch?->id,
                ]
            );
        }
    }
}
