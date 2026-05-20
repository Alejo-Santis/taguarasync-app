<?php

namespace Database\Seeders;

use App\Enums\CashSessionStatus;
use App\Models\CashRegister;
use App\Models\CashSession;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;

class DemoCashRegisterSeeder extends Seeder
{
    public function run(): void
    {
        $tenant = Tenant::first();

        if (! $tenant) {
            return;
        }

        $registers = [
            ['name' => 'Caja Principal', 'code' => 'CJ-01', 'is_active' => true],
            ['name' => 'Caja Secundaria', 'code' => 'CJ-02', 'is_active' => true],
        ];

        foreach ($registers as $data) {
            CashRegister::firstOrCreate(
                ['tenant_id' => $tenant->id, 'code' => $data['code']],
                [...$data, 'tenant_id' => $tenant->id]
            );
        }

        // Open a demo session on the main register so POS works immediately
        $mainRegister = CashRegister::where('tenant_id', $tenant->id)->where('code', 'CJ-01')->first();
        $owner = User::where('tenant_id', $tenant->id)->first();

        if ($mainRegister && $owner) {
            $hasOpenSession = CashSession::where('cash_register_id', $mainRegister->id)
                ->where('status', CashSessionStatus::Open)
                ->exists();

            if (! $hasOpenSession) {
                CashSession::create([
                    'tenant_id' => $tenant->id,
                    'cash_register_id' => $mainRegister->id,
                    'user_id' => $owner->id,
                    'opening_amount' => 100000,
                    'status' => CashSessionStatus::Open,
                    'notes' => 'Turno de demo — apertura automatica',
                    'opened_at' => now(),
                ]);
            }
        }
    }
}
