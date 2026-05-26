<?php

use App\Enums\CashSessionStatus;
use App\Models\CashRegister;
use App\Models\CashSession;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function cashierForTenant(Tenant $tenant): User
{
    app(RoleAndPermissionSeeder::class)->run();

    $user = User::factory()->for($tenant)->create();
    $user->assignRole('cashier');

    return $user;
}

test('guests cannot open or close sessions', function () {
    $this->get('/pos/session/open')->assertRedirect('/login');
    $this->post('/pos/session')->assertRedirect('/login');
});

test('authenticated user can open a cash session', function () {
    $tenant = Tenant::factory()->create();
    $user = cashierForTenant($tenant);
    $register = CashRegister::factory()->for($tenant)->create(['code' => 'CJ-01', 'is_active' => true]);

    $this->actingAs($user)
        ->post('/pos/session', [
            'cash_register_id' => $register->id,
            'opening_amount' => 50000,
        ])
        ->assertRedirect(route('pos.index'));

    $session = CashSession::where('user_id', $user->id)->first();

    expect($session)->not->toBeNull()
        ->and($session->status)->toBe(CashSessionStatus::Open)
        ->and($session->opening_amount)->toBe(50000)
        ->and($session->cash_register_id)->toBe($register->id);
});

test('cannot open two sessions simultaneously for same user', function () {
    $tenant = Tenant::factory()->create();
    $user = cashierForTenant($tenant);
    $register1 = CashRegister::factory()->for($tenant)->create(['code' => 'CJ-01', 'is_active' => true]);
    $register2 = CashRegister::factory()->for($tenant)->create(['code' => 'CJ-02', 'is_active' => true]);

    CashSession::create([
        'tenant_id' => $tenant->id,
        'cash_register_id' => $register1->id,
        'user_id' => $user->id,
        'opening_amount' => 50000,
        'status' => CashSessionStatus::Open,
        'opened_at' => now(),
    ]);

    $this->actingAs($user)
        ->post('/pos/session', [
            'cash_register_id' => $register2->id,
            'opening_amount' => 30000,
        ])
        ->assertSessionHasErrors('cash_register_id');

    expect(CashSession::count())->toBe(1);
});

test('cannot open session on register that already has one open', function () {
    $tenant = Tenant::factory()->create();
    $user1 = cashierForTenant($tenant);
    $user2 = cashierForTenant($tenant);
    $register = CashRegister::factory()->for($tenant)->create(['code' => 'CJ-01', 'is_active' => true]);

    CashSession::create([
        'tenant_id' => $tenant->id,
        'cash_register_id' => $register->id,
        'user_id' => $user1->id,
        'opening_amount' => 50000,
        'status' => CashSessionStatus::Open,
        'opened_at' => now(),
    ]);

    $this->actingAs($user2)
        ->post('/pos/session', [
            'cash_register_id' => $register->id,
            'opening_amount' => 30000,
        ])
        ->assertSessionHasErrors('cash_register_id');
});

test('user can close an open session with actual amount', function () {
    $tenant = Tenant::factory()->create();
    $user = cashierForTenant($tenant);
    $register = CashRegister::factory()->for($tenant)->create(['code' => 'CJ-01', 'is_active' => true]);

    $session = CashSession::create([
        'tenant_id' => $tenant->id,
        'cash_register_id' => $register->id,
        'user_id' => $user->id,
        'opening_amount' => 100000,
        'status' => CashSessionStatus::Open,
        'opened_at' => now(),
    ]);

    $this->actingAs($user)
        ->post("/pos/session/{$session->uuid}/close", [
            'actual_closing_amount' => 95000,
        ])
        ->assertRedirect(route('dashboard'));

    $closed = $session->fresh();

    expect($closed->status)->toBe(CashSessionStatus::Closed)
        ->and($closed->actual_closing_amount)->toBe(95000)
        ->and($closed->difference)->toBe(-5000) // 95000 - 100000 = -5000
        ->and($closed->closed_at)->not->toBeNull();
});

test('closing a session calculates expected amount including cash sales', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->for($tenant)->create();
    $register = CashRegister::factory()->for($tenant)->create(['code' => 'CJ-01', 'is_active' => true]);

    $session = CashSession::create([
        'tenant_id' => $tenant->id,
        'cash_register_id' => $register->id,
        'user_id' => $user->id,
        'opening_amount' => 50000,
        'status' => CashSessionStatus::Open,
        'opened_at' => now(),
    ]);

    // expected = 50000 (opening) + 0 (no cash sales) = 50000
    expect($session->expectedClosingAmount())->toBe(50000);
});
