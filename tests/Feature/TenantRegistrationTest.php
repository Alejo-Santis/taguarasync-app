<?php

use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('admin can create a tenant and assigns the owner role', function () {
    $this->seed(RoleAndPermissionSeeder::class);

    $superAdmin = User::factory()->create(['is_super_admin' => true]);

    $response = $this->actingAs($superAdmin)->post('/admin/tenants', [
        'tenant_name' => 'Drogueria La Costa',
        'name' => 'Alejo Santis',
        'email' => 'alejo@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $response->assertRedirect();

    $tenant = Tenant::query()->sole();
    $user = User::where('email', 'alejo@example.com')->sole();

    expect($tenant->name)->toBe('Drogueria La Costa')
        ->and($tenant->slug)->toBe('drogueria-la-costa')
        ->and($user->tenant_id)->toBe($tenant->id)
        ->and($user->hasRole('owner'))->toBeTrue();
});

test('public registration is disabled', function () {
    $this->get('/register')->assertStatus(404);
    $this->post('/register', [])->assertStatus(404);
});
