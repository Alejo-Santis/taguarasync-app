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
            'dashboard.view',
            'users.manage',
            'settings.manage',
            'products.view',
            'products.manage',
            'inventory.view',
            'inventory.adjust',
            'inventory.transfer',
            'suppliers.view',
            'suppliers.manage',
            'purchases.view',
            'purchases.create',
            'purchases.receive',
            'pos.sell',
            'cash.register.open',
            'cash.register.close',
            'sales.view',
            'sales.cancel',
            'billing.view',
            'billing.configure',
            'billing.resend',
            'reports.view',
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
