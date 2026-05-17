<?php

namespace Database\Seeders;

use App\Enums\TenantStatus;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            RoleAndPermissionSeeder::class,
        ]);

        $tenant = Tenant::firstOrCreate([
            'slug' => 'farmacia-demo',
        ], [
            'uuid' => (string) Str::uuid(),
            'name' => 'Farmacia Demo',
            'legal_name' => 'Farmacia Demo SAS',
            'nit' => '900123456',
            'email' => 'admin@taguara.test',
            'phone' => '3001234567',
            'city' => 'Barranquilla',
            'department' => 'Atlantico',
            'timezone' => 'America/Bogota',
            'status' => TenantStatus::Active,
        ]);

        $owner = User::firstOrCreate([
            'email' => 'admin@taguara.test',
        ], [
            'tenant_id' => $tenant->id,
            'name' => 'Administrador Demo',
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
        ]);

        $owner->assignRole('owner');
    }
}
