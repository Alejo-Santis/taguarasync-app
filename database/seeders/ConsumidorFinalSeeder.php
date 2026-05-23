<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\Tenant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ConsumidorFinalSeeder extends Seeder
{
    /**
     * Número estándar colombiano para Consumidor Final en FE.
     * La DIAN acepta 222222222222 como identificador de cliente anónimo.
     */
    public const IDENTIFICATION_NUMBER = '222222222222';

    public function run(): void
    {
        Tenant::all()->each(function (Tenant $tenant): void {
            static::createForTenant($tenant->id);
        });
    }

    /**
     * Crea el cliente Consumidor Final para un tenant.
     * Usa firstOrCreate para ser idempotente — se puede llamar múltiples veces sin duplicar.
     */
    public static function createForTenant(int $tenantId): Customer
    {
        return Customer::withoutGlobalScopes()->firstOrCreate(
            [
                'tenant_id' => $tenantId,
                'identification_number' => self::IDENTIFICATION_NUMBER,
                'identification_type_code' => '13',
            ],
            [
                'uuid' => (string) Str::uuid(),
                'tenant_id' => $tenantId,
                'identification_type_code' => '13',
                'identification_number' => self::IDENTIFICATION_NUMBER,
                'verification_digit' => null,
                'first_name' => null,
                'last_name' => null,
                'business_name' => 'Consumidor Final',
                'organization_type_code' => '2',
                'regime_type_code' => '49',
                'fiscal_responsibilities' => null,
                'email' => null,
                'phone' => null,
                'address' => null,
                'municipality_code' => null,
                'is_active' => true,
                'notes' => 'Cliente por defecto para ventas sin identificación del comprador.',
            ]
        );
    }
}
