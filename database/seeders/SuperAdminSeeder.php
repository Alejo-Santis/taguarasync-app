<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class SuperAdminSeeder extends Seeder
{
    /**
     * Crea (o actualiza) el usuario super admin del sistema.
     *
     * El super admin:
     *  - No pertenece a ningún tenant (tenant_id = null).
     *  - Tiene is_super_admin = true  → Gate::before devuelve true, bypasea
     *    todas las políticas de autorización.
     *  - Tiene el rol `super_admin`   → getAllPermissions() devuelve todos
     *    los permisos, lo que hace que el sidebar del frontend muestre
     *    todos los items de navegación.
     *
     * Credenciales por defecto (solo para desarrollo):
     *   Email:    superadmin@taguara.local
     *   Password: SuperAdmin2024!
     *
     * Cambia la contraseña inmediatamente en producción.
     */
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        // Garantiza que el rol super_admin existe con todos los permisos
        // (RoleAndPermissionSeeder debe haber corrido antes).
        $role = Role::findOrCreate('super_admin');

        $superAdmin = User::updateOrCreate(
            ['email' => 'superadmin@taguara.local'],
            [
                'name' => 'Super Administrador',
                'password' => Hash::make('SuperAdmin2026#**'),
                'is_super_admin' => true,
                'tenant_id' => null,
                'email_verified_at' => now(),
            ]
        );

        // Sincroniza el rol para que getAllPermissions() devuelva todo
        $superAdmin->syncRoles([$role]);

        $this->command->info("Super admin listo: {$superAdmin->email}");
    }
}
