<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RoleAndPermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permissions = [
            // ── En uso ────────────────────────────────────────────────────────
            'dashboard.view',
            'users.manage',
            'settings.manage',
            'products.view',
            'products.manage',       // gestión avanzada de catálogo (edición masiva)
            'inventory.view',
            'inventory.adjust',      // ajustes manuales de stock
            'pos.sell',
            'cash.register.open',
            'cash.register.close',
            'purchases.view',
            'purchases.create',
            'sales.view',
            'sales.cancel',
            'billing.view',
            'billing.configure',
            'billing.resend',
            'reports.view',

            // ── Reservados para roadmap (rol warehouse) ───────────────────────
            // inventory.transfer  → traslados entre ubicaciones/bodegas (pendiente)
            // suppliers.view      → vista independiente de proveedores sin acceso a compras
            // suppliers.manage    → CRUD de proveedores sin acceso a compras
            // purchases.receive   → flujo de recepción de órdenes de compra (pendiente)
            'inventory.transfer',
            'suppliers.view',
            'suppliers.manage',
            'purchases.receive',
        ];

        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission);
        }

        $this->role('super_admin', $permissions);
        $this->role('owner', $permissions);
        $this->role('admin', array_diff($permissions, ['billing.configure']));
        $this->role('cashier', [
            'dashboard.view',
            'products.view',
            'inventory.view',
            'pos.sell',
            'cash.register.open',
            'cash.register.close',
            'sales.view',
            'billing.view',
            'billing.resend',
        ]);
        $this->role('warehouse', [
            'dashboard.view',
            'products.view',
            'inventory.view',
            'inventory.adjust',
            'inventory.transfer',
            'suppliers.view',
            'suppliers.manage',
            'purchases.view',
            'purchases.create',
            'purchases.receive',
        ]);
        $this->role('accountant', [
            'dashboard.view',
            'sales.view',
            'billing.view',
            'billing.resend',
            'reports.view',
        ]);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * @param  array<int, string>  $permissions
     */
    private function role(string $name, array $permissions): void
    {
        Role::findOrCreate($name)->syncPermissions(
            Permission::query()
                ->whereIn('name', $permissions)
                ->get()
        );
    }
}
