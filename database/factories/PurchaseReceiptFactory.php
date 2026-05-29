<?php

namespace Database\Factories;

use App\Enums\PurchaseReceiptStatus;
use App\Models\Branch;
use App\Models\PurchaseReceipt;
use App\Models\Supplier;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<PurchaseReceipt>
 */
class PurchaseReceiptFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $tenant = Tenant::factory();

        return [
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $tenant,
            'branch_id' => Branch::factory()->for($tenant),
            'supplier_id' => Supplier::factory()->for($tenant),
            'user_id' => User::factory()->for($tenant),
            'document_number' => fake()->unique()->bothify('REM-####'),
            'document_date' => today(),
            'received_at' => now(),
            'subtotal' => 0,
            'tax_total' => 0,
            'total' => 0,
            'status' => PurchaseReceiptStatus::Received,
            'source_file_path' => null,
            'notes' => null,
        ];
    }
}
