<?php

use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
 // ->use(RefreshDatabase::class)
    ->in('Feature', 'Browser');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| Helpers shared across all feature tests.
|
*/

/**
 * Seed roles/permissions and return a user with the owner role (all permissions).
 */
function createOwnerUser(Tenant $tenant): User
{
    app(RoleAndPermissionSeeder::class)->run();
    $user = User::factory()->for($tenant)->create();
    $user->assignRole('owner');

    return $user;
}

/**
 * Seed roles/permissions and return a user with the admin role.
 */
function createAdminUser(Tenant $tenant): User
{
    app(RoleAndPermissionSeeder::class)->run();
    $user = User::factory()->for($tenant)->create();
    $user->assignRole('admin');

    return $user;
}
