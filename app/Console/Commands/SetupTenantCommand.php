<?php

namespace App\Console\Commands;

use App\Enums\TenantStatus;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\OtcProductsSeeder;
use Database\Seeders\PharmacyBaseDataSeeder;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\info;
use function Laravel\Prompts\intro;
use function Laravel\Prompts\note;
use function Laravel\Prompts\outro;
use function Laravel\Prompts\password;
use function Laravel\Prompts\select;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\text;

#[Signature('taguara:setup-tenant')]
#[Description('Configura una nueva farmacia en Taguara Sync con todos sus datos base.')]
class SetupTenantCommand extends Command
{
    public function handle(PharmacyBaseDataSeeder $baseDataSeeder): int
    {
        intro('🏥  Taguara Sync — Configurar nueva farmacia');

        // ── Datos de la farmacia ──────────────────────────────────────────────
        $name = text(
            label: 'Nombre comercial de la farmacia',
            placeholder: 'Farmacia El Caribe',
            required: true,
            validate: fn (string $v) => strlen($v) < 3 ? 'El nombre debe tener al menos 3 caracteres.' : null,
        );

        $legalName = text(
            label: 'Razón social',
            placeholder: 'Farmacia El Caribe SAS',
            default: $name,
            required: true,
        );

        $nit = text(
            label: 'NIT (sin dígito de verificación)',
            placeholder: '900123456',
            required: true,
            validate: fn (string $v) => ! preg_match('/^\d{6,12}$/', preg_replace('/[^0-9]/', '', $v))
                ? 'El NIT debe contener solo dígitos (6–12 números).'
                : null,
        );

        $city = text(
            label: 'Ciudad',
            placeholder: 'Barranquilla',
            required: true,
        );

        $department = select(
            label: 'Departamento',
            options: $this->departments(),
            default: 'Atlantico',
        );

        $phone = text(
            label: 'Teléfono de contacto',
            placeholder: '3001234567',
        );

        // ── Datos del administrador ───────────────────────────────────────────
        info('');
        info('Cuenta del administrador (rol: owner)');

        $email = text(
            label: 'Correo electrónico',
            placeholder: 'admin@farmacia.com',
            required: true,
            validate: fn (string $v) => ! filter_var($v, FILTER_VALIDATE_EMAIL)
                ? 'Ingresa un correo válido.'
                : (User::where('email', $v)->exists() ? 'Este correo ya está registrado.' : null),
        );

        $adminPassword = password(
            label: 'Contraseña',
            required: true,
            validate: fn (string $v) => strlen($v) < 8 ? 'La contraseña debe tener al menos 8 caracteres.' : null,
        );

        $adminName = text(
            label: 'Nombre completo del administrador',
            placeholder: 'Administrador',
            default: 'Administrador',
            required: true,
        );

        // ── Opciones de catálogo ──────────────────────────────────────────────
        info('');
        info('Catálogo base');

        $loadLabs = confirm(
            label: '¿Cargar laboratorios colombianos? (Genfar, Bayer, MK...)',
            default: true,
        );

        $loadCategories = confirm(
            label: '¿Cargar categorías farmacéuticas?',
            default: true,
        );

        $loadExampleProducts = confirm(
            label: '¿Cargar productos de referencia de ejemplo? (se pueden borrar después)',
            default: false,
        );

        // ── Crear tenant y usuario ────────────────────────────────────────────
        info('');

        [$tenant, $user] = spin(
            callback: function () use ($name, $legalName, $nit, $city, $department, $phone, $email, $adminPassword, $adminName, $baseDataSeeder, $loadLabs, $loadCategories): array {
                $slug = Str::slug($name).'-'.Str::random(4);

                $tenant = Tenant::create([
                    'uuid' => (string) Str::uuid(),
                    'name' => $name,
                    'legal_name' => $legalName,
                    'nit' => preg_replace('/[^0-9]/', '', $nit),
                    'email' => $email,
                    'phone' => $phone ?: null,
                    'city' => $city,
                    'department' => $department,
                    'slug' => $slug,
                    'timezone' => 'America/Bogota',
                    'status' => TenantStatus::Active,
                ]);

                $user = User::create([
                    'tenant_id' => $tenant->id,
                    'name' => $adminName,
                    'email' => $email,
                    'password' => Hash::make($adminPassword),
                    'email_verified_at' => now(),
                ]);

                $user->assignRole('owner');

                // Sucursal principal + datos base seleccionados
                $baseDataSeeder->setupForTenant($tenant, $loadLabs, $loadCategories);

                return [$tenant, $user];
            },
            message: 'Creando farmacia...',
        );

        if ($loadExampleProducts) {
            spin(
                callback: fn () => $this->seedExampleProducts($tenant),
                message: 'Cargando productos de referencia...',
            );
        }

        // ── Resumen ───────────────────────────────────────────────────────────
        info('');
        note(implode("\n", [
            "  Farmacia:   {$tenant->name}",
            "  NIT:        {$tenant->nit}",
            "  Email:      {$user->email}",
            '  Contraseña: la que ingresaste',
        ]));

        outro('✅  Farmacia configurada correctamente.');

        return self::SUCCESS;
    }

    private function seedExampleProducts(Tenant $tenant): void
    {
        app(OtcProductsSeeder::class)->runForTenant($tenant);
    }

    /** @return array<string, string> */
    private function departments(): array
    {
        return [
            'Amazonas' => 'Amazonas',
            'Antioquia' => 'Antioquia',
            'Arauca' => 'Arauca',
            'Atlantico' => 'Atlántico',
            'Bolivar' => 'Bolívar',
            'Boyaca' => 'Boyacá',
            'Caldas' => 'Caldas',
            'Caqueta' => 'Caquetá',
            'Casanare' => 'Casanare',
            'Cauca' => 'Cauca',
            'Cesar' => 'Cesar',
            'Choco' => 'Chocó',
            'Cordoba' => 'Córdoba',
            'Cundinamarca' => 'Cundinamarca',
            'Guainia' => 'Guainía',
            'Guaviare' => 'Guaviare',
            'Huila' => 'Huila',
            'La Guajira' => 'La Guajira',
            'Magdalena' => 'Magdalena',
            'Meta' => 'Meta',
            'Narino' => 'Nariño',
            'Norte de Santander' => 'Norte de Santander',
            'Putumayo' => 'Putumayo',
            'Quindio' => 'Quindío',
            'Risaralda' => 'Risaralda',
            'San Andres' => 'San Andrés y Providencia',
            'Santander' => 'Santander',
            'Sucre' => 'Sucre',
            'Tolima' => 'Tolima',
            'Valle del Cauca' => 'Valle del Cauca',
            'Vaupes' => 'Vaupés',
            'Vichada' => 'Vichada',
            'Bogota DC' => 'Bogotá D.C.',
        ];
    }
}
