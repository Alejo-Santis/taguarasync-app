<?php

use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('registration creates a tenant and assigns the owner role', function () {
    $this->seed(RoleAndPermissionSeeder::class);

    $response = $this->post('/register', [
        'tenant_name' => 'Drogueria La Costa',
        'name' => 'Alejo Santis',
        'email' => 'alejo@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $response->assertRedirect('/dashboard');
    $this->assertAuthenticated();

    $tenant = Tenant::query()->sole();
    $user = User::query()->sole();

    expect($tenant->name)->toBe('Drogueria La Costa')
        ->and($tenant->slug)->toBe('drogueria-la-costa')
        ->and($user->tenant_id)->toBe($tenant->id)
        ->and($user->hasRole('owner'))->toBeTrue();
});
