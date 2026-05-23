<?php

use App\Enums\CashSessionStatus;
use App\Enums\PaymentMethod;
use App\Enums\SaleStatus;
use App\Models\CashRegister;
use App\Models\CashSession;
use App\Models\Sale;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

test('cash session report shows audited sessions and totals', function () {
    $tenant = Tenant::factory()->create();
    $otherTenant = Tenant::factory()->create();
    $user = User::factory()->for($tenant)->create(['name' => 'Cajero principal']);
    Permission::findOrCreate('reports.view');
    $user->givePermissionTo('reports.view');
    $register = CashRegister::factory()->for($tenant)->create(['name' => 'Caja principal', 'code' => 'CJ-01']);

    $session = CashSession::create([
        'tenant_id' => $tenant->id,
        'cash_register_id' => $register->id,
        'user_id' => $user->id,
        'closed_by_user_id' => $user->id,
        'opening_amount' => 50000,
        'actual_closing_amount' => 64000,
        'difference' => -1000,
        'status' => CashSessionStatus::Closed,
        'opened_at' => now()->subHour(),
        'closed_at' => now(),
    ]);

    Sale::create([
        'uuid' => (string) Str::uuid(),
        'tenant_id' => $tenant->id,
        'user_id' => $user->id,
        'cash_session_id' => $session->id,
        'document_number' => 'VTA-00000001',
        'subtotal' => 15000,
        'tax_total' => 0,
        'total' => 15000,
        'payment_method' => PaymentMethod::Cash,
        'status' => SaleStatus::Completed,
    ]);

    Sale::create([
        'uuid' => (string) Str::uuid(),
        'tenant_id' => $tenant->id,
        'user_id' => $user->id,
        'cash_session_id' => $session->id,
        'document_number' => 'VTA-00000002',
        'subtotal' => 5000,
        'tax_total' => 0,
        'total' => 5000,
        'payment_method' => PaymentMethod::Card,
        'status' => SaleStatus::Completed,
    ]);

    CashSession::create([
        'tenant_id' => $otherTenant->id,
        'cash_register_id' => CashRegister::factory()->for($otherTenant)->create()->id,
        'user_id' => User::factory()->for($otherTenant)->create()->id,
        'opening_amount' => 999999,
        'status' => CashSessionStatus::Open,
        'opened_at' => now(),
    ]);

    $this->actingAs($user)
        ->get('/reports/cash-sessions?difference=short')
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Reports/CashSessions')
            ->where('summary.sessions_count', 1)
            ->where('summary.sales_total', 20000)
            ->where('summary.difference_total', -1000)
            ->has('sessions.data', 1)
            ->where('sessions.data.0.register.name', 'Caja principal')
            ->where('sessions.data.0.cashier', 'Cajero principal')
            ->where('sessions.data.0.expected_closing', 65000)
            ->where('sessions.data.0.difference', -1000)
            ->etc()
        );
});

test('cash session detail lists sales for the session', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->for($tenant)->create();
    Permission::findOrCreate('reports.view');
    $user->givePermissionTo('reports.view');
    $register = CashRegister::factory()->for($tenant)->create(['name' => 'Caja 2', 'code' => 'CJ-02']);

    $session = CashSession::create([
        'tenant_id' => $tenant->id,
        'cash_register_id' => $register->id,
        'user_id' => $user->id,
        'opening_amount' => 100000,
        'status' => CashSessionStatus::Open,
        'opened_at' => now(),
    ]);

    Sale::create([
        'uuid' => (string) Str::uuid(),
        'tenant_id' => $tenant->id,
        'user_id' => $user->id,
        'cash_session_id' => $session->id,
        'document_number' => 'VTA-DETALLE',
        'subtotal' => 12000,
        'tax_total' => 0,
        'total' => 12000,
        'payment_method' => PaymentMethod::Transfer,
        'status' => SaleStatus::Completed,
    ]);

    $this->actingAs($user)
        ->get("/reports/cash-sessions/{$session->uuid}")
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Reports/CashSessionShow')
            ->where('session.uuid', $session->uuid)
            ->where('session.transfer_sales_total', 12000)
            ->has('sales.data', 1)
            ->where('sales.data.0.document_number', 'VTA-DETALLE')
            ->etc()
        );
});
