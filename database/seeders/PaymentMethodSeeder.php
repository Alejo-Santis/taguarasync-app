<?php

namespace Database\Seeders;

use App\Actions\Payments\EnsureDefaultPaymentMethods;
use App\Models\Tenant;
use Illuminate\Database\Seeder;

class PaymentMethodSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $ensureMethods = app(EnsureDefaultPaymentMethods::class);

        Tenant::query()->each(fn (Tenant $tenant) => $ensureMethods->execute($tenant));
    }
}
