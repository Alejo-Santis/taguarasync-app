<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

/**
 * Seeder principal — datos globales que deben existir en TODOS los entornos.
 *
 * Solo incluye catálogos globales (sin tenant_id) y configuración base del sistema.
 * NO incluye datos de demostración ni datos específicos de ninguna farmacia.
 *
 * Para desarrollo y pruebas:
 *   php artisan db:seed --class=DemoSeeder
 *
 * Para registrar una farmacia nueva en producción:
 *   php artisan taguara:setup-tenant
 */
class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $this->call([
            // ── Sistema ────────────────────────────────────────────────────────
            RoleAndPermissionSeeder::class,   // roles y permisos (Spatie)
            SuperAdminSeeder::class,          // super admin del SaaS (sin tenant)

            // ── Catálogos DIAN ─────────────────────────────────────────────────
            DianCatalogsSeeder::class,        // tipos de ID, regímenes, impuestos, unidades DIAN
            DianMunicipalitiesSeeder::class,  // municipios de Colombia

            // ── Catálogos farmacéuticos globales (sin tenant_id) ───────────────
            ProductUnitsSeeder::class,        // und, ml, g, bls, caj, fra, tub, amp, sob, tir
            ActiveIngredientsSeeder::class,   // principios activos con código ATC
        ]);
    }
}
