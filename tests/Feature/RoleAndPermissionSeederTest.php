<?php

use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

test('role and permission seeder creates the base access matrix', function () {
    $this->seed(RoleAndPermissionSeeder::class);

    expect(Permission::count())->toBeGreaterThan(20)
        ->and(Role::whereIn('name', ['owner', 'admin', 'cashier', 'warehouse', 'accountant'])->count())->toBe(5)
        ->and(Role::findByName('owner')->hasPermissionTo('billing.configure'))->toBeTrue()
        ->and(Role::findByName('cashier')->hasPermissionTo('pos.sell'))->toBeTrue()
        ->and(Role::findByName('cashier')->hasPermissionTo('billing.configure'))->toBeFalse();
});
