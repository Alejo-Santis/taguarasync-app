<?php

namespace Database\Seeders;

use App\Enums\TenantStatus;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Carga datos de demostración para entornos de desarrollo y pruebas.
 *
 * Ejecutar con:
 *   php artisan db:seed --class=DemoSeeder
 *
 * NO ejecutar en producción. Crea una farmacia "farmacia-demo" con
 * credenciales conocidas, productos, proveedores y cajas de prueba.
 *
 * Credenciales del administrador demo:
 *   Email:    admin@taguara.test
 *   Contraseña: Password123!
 */
class DemoSeeder extends Seeder
{
    public function __construct(private readonly PharmacyBaseDataSeeder $baseDataSeeder) {}

    public function run(): void
    {
        $tenant = $this->createDemoTenant();
        $this->createDemoOwner($tenant);

        $this->baseDataSeeder->setupForTenant($tenant);

        $this->call([
            DemoSuppliersSeeder::class,
            OtcProductsSeeder::class,
            DemoPurchaseReceiptsSeeder::class,
            DemoCashRegisterSeeder::class,
        ]);

        $this->command?->info('✅  Farmacia Demo lista.');
        $this->command?->info('   Email:     admin@taguara.test');
        $this->command?->info('   Contraseña: Password123!');
    }

    private function createDemoTenant(): Tenant
    {
        return Tenant::firstOrCreate(
            ['slug' => 'farmacia-demo'],
            [
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
            ]
        );
    }

    private function createDemoOwner(Tenant $tenant): User
    {
        $user = User::firstOrCreate(
            ['email' => 'admin@taguara.test'],
            [
                'tenant_id' => $tenant->id,
                'name' => 'Administrador Demo',
                'password' => Hash::make('Password123!'),
                'email_verified_at' => now(),
            ]
        );

        if (! $user->hasRole('owner')) {
            $user->assignRole('owner');
        }

        return $user;
    }
}
