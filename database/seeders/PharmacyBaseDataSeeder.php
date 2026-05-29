<?php

namespace Database\Seeders;

use App\Actions\Payments\EnsureDefaultPaymentMethods;
use App\Models\Branch;
use App\Models\Laboratory;
use App\Models\ProductCategory;
use App\Models\Tenant;
use Illuminate\Database\Seeder;

/**
 * Carga los datos base que toda farmacia necesita al registrarse:
 * laboratorios colombianos, categorías farmacéuticas, métodos de pago,
 * cliente Consumidor Final y sucursal Principal.
 *
 * Es idempotente: se puede llamar múltiples veces sin duplicar registros.
 * Acepta un tenant específico o recorre todos los tenants si no se pasa ninguno.
 */
class PharmacyBaseDataSeeder extends Seeder
{
    public function __construct(private readonly EnsureDefaultPaymentMethods $ensurePaymentMethods) {}

    public function run(): void
    {
        Tenant::all()->each(fn (Tenant $tenant) => $this->setupForTenant($tenant));
    }

    public function setupForTenant(Tenant $tenant, bool $withLabs = true, bool $withCategories = true): void
    {
        $this->seedBranch($tenant);

        if ($withLabs) {
            $this->seedLaboratories($tenant->id);
        }

        if ($withCategories) {
            $this->seedCategories($tenant->id);
        }

        $this->ensurePaymentMethods->execute($tenant);
        ConsumidorFinalSeeder::createForTenant($tenant->id);
    }

    private function seedBranch(Tenant $tenant): void
    {
        Branch::firstOrCreate(
            ['tenant_id' => $tenant->id, 'is_main' => true],
            ['name' => 'Principal', 'is_active' => true]
        );
    }

    private function seedLaboratories(int $tenantId): void
    {
        $laboratories = [
            ['name' => 'Genfar',                    'nit' => '800099953-1',  'country' => 'CO'],
            ['name' => 'MK (Tecnoquimicas)',         'nit' => '890300144-6',  'country' => 'CO'],
            ['name' => 'Bayer Colombia',             'nit' => '860015696-3',  'country' => 'CO'],
            ['name' => 'Novartis Colombia',          'nit' => '900008327-9',  'country' => 'CO'],
            ['name' => 'Pfizer Colombia',            'nit' => '830021192-7',  'country' => 'CO'],
            ['name' => 'Sanofi Colombia',            'nit' => '800155765-4',  'country' => 'CO'],
            ['name' => 'Abbott Laboratories',        'nit' => '800227497-7',  'country' => 'CO'],
            ['name' => 'Roche Colombia',             'nit' => '860008643-0',  'country' => 'CO'],
            ['name' => 'GlaxoSmithKline Colombia',   'nit' => '800251193-1',  'country' => 'CO'],
            ['name' => 'Lafrancol',                  'nit' => '890315394-1',  'country' => 'CO'],
            ['name' => 'Eurofarma Colombia',         'nit' => '900399610-7',  'country' => 'CO'],
            ['name' => 'Procaps',                    'nit' => '890102122-5',  'country' => 'CO'],
            ['name' => 'Chalver de Colombia',        'nit' => '800069735-9',  'country' => 'CO'],
            ['name' => 'Merck Colombia',             'nit' => '860004840-1',  'country' => 'CO'],
            ['name' => 'AstraZeneca Colombia',       'nit' => '800206661-3',  'country' => 'CO'],
            ['name' => 'Boehringer Ingelheim',       'nit' => '860002171-6',  'country' => 'CO'],
            ['name' => 'Coaspharma',                 'nit' => '890309225-8',  'country' => 'CO'],
            ['name' => 'Laboratorio Propio',         'nit' => null,           'country' => 'CO'],
        ];

        foreach ($laboratories as $lab) {
            Laboratory::firstOrCreate(
                ['tenant_id' => $tenantId, 'name' => $lab['name']],
                [...$lab, 'tenant_id' => $tenantId, 'is_active' => true]
            );
        }
    }

    private function seedCategories(int $tenantId): void
    {
        $categories = [
            'Analgesicos y antiinflamatorios',
            'Antibioticos',
            'Antihipertensivos',
            'Antihistaminicos',
            'Antidiabeticos',
            'Antifungicos',
            'Antiparasitarios',
            'Cardiovasculares',
            'Dermatologicos',
            'Digestivos y gastricos',
            'Medicamentos controlados',
            'Oftalmologicos',
            'Otologicos',
            'Respiratorios y broncodilatadores',
            'Suplementos y vitaminas',
            'Tiroides y hormonas',
            'Urologicos',
            'Miscelaneos',
        ];

        foreach ($categories as $name) {
            ProductCategory::firstOrCreate(
                ['tenant_id' => $tenantId, 'name' => $name],
                ['tenant_id' => $tenantId, 'is_active' => true]
            );
        }
    }
}
