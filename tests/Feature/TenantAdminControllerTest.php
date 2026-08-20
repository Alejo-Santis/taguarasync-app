<?php

use App\Enums\TenantPlan;
use App\Enums\TenantStatus;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

function superAdmin(): User
{
    return User::factory()->create(['is_super_admin' => true, 'tenant_id' => null]);
}

test('regular tenant users cannot access the admin panel', function () {
    $tenant = Tenant::factory()->create();
    $user = createOwnerUser($tenant);

    $this->actingAs($user)->get('/admin/tenants')->assertForbidden();
});

test('super admins can list all tenants', function () {
    $admin = superAdmin();
    Tenant::factory()->create(['name' => 'Farmacia Uno']);
    Tenant::factory()->create(['name' => 'Farmacia Dos']);

    $this->actingAs($admin)
        ->get('/admin/tenants')
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Tenants/Index')
            ->has('tenants', 2)
        );
});

test('super admins can suspend and reactivate a tenant', function () {
    $admin = superAdmin();
    $tenant = Tenant::factory()->create(['status' => TenantStatus::Active]);

    $this->actingAs($admin)
        ->patch("/admin/tenants/{$tenant->uuid}/toggle-status")
        ->assertRedirect();

    expect($tenant->fresh()->status)->toBe(TenantStatus::Suspended);

    $this->actingAs($admin)
        ->patch("/admin/tenants/{$tenant->uuid}/toggle-status")
        ->assertRedirect();

    expect($tenant->fresh()->status)->toBe(TenantStatus::Active);
});

test('super admins can record a payment and it extends the subscription and applies plan limits', function () {
    $admin = superAdmin();
    $tenant = Tenant::factory()->create(['status' => TenantStatus::Suspended, 'subscribed_until' => null]);

    $this->actingAs($admin)
        ->post("/admin/tenants/{$tenant->uuid}/record-payment", [
            'plan' => TenantPlan::Professional->value,
            'billing_cycle' => 'monthly',
        ])
        ->assertRedirect();

    $tenant->refresh();

    expect($tenant->status)->toBe(TenantStatus::Active)
        ->and($tenant->plan)->toBe(TenantPlan::Professional)
        ->and($tenant->max_users)->toBe(10)
        ->and($tenant->offline_sync_enabled)->toBeTrue()
        ->and($tenant->subscribed_until->isFuture())->toBeTrue();
});

test('super admins can list, update and reset the password of a tenant user', function () {
    $admin = superAdmin();
    app(RoleAndPermissionSeeder::class)->run();
    $tenant = Tenant::factory()->create();
    $member = User::factory()->for($tenant)->create(['name' => 'Antes']);
    $member->assignRole('cashier');

    $this->actingAs($admin)
        ->getJson("/admin/tenants/{$tenant->uuid}/users")
        ->assertOk()
        ->assertJsonFragment(['name' => 'Antes']);

    $this->actingAs($admin)
        ->putJson("/admin/tenants/{$tenant->uuid}/users/{$member->id}", [
            'name' => 'Despues',
            'role' => 'warehouse',
        ])
        ->assertOk()
        ->assertJsonFragment(['name' => 'Despues', 'role' => 'warehouse']);

    expect($member->fresh()->name)->toBe('Despues');

    $originalPassword = $member->fresh()->password;

    $this->actingAs($admin)
        ->postJson("/admin/tenants/{$tenant->uuid}/users/{$member->id}/reset-password")
        ->assertOk()
        ->assertJsonStructure(['password', 'user_name']);

    expect($member->fresh()->password)->not->toBe($originalPassword);
});

test('a user from another tenant cannot be updated through a mismatched tenant', function () {
    $admin = superAdmin();
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();
    $member = User::factory()->for($tenantB)->create();

    $this->actingAs($admin)
        ->putJson("/admin/tenants/{$tenantA->uuid}/users/{$member->id}", [
            'name' => 'Hackeado',
            'role' => 'cashier',
        ])
        ->assertNotFound();

    expect($member->fresh()->name)->not->toBe('Hackeado');
});
