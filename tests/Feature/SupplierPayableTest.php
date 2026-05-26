<?php

use App\Models\BankAccount;
use App\Models\BankAccountMovement;
use App\Models\PurchaseReceipt;
use App\Models\Supplier;
use App\Models\SupplierPayment;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

function payableContext(): array
{
    $tenant = Tenant::factory()->create();
    $user = User::factory()->for($tenant)->create();
    Permission::findOrCreate('purchases.view');
    Permission::findOrCreate('purchases.create');
    $user->givePermissionTo(['purchases.view', 'purchases.create']);

    $supplier = Supplier::factory()->for($tenant)->create(['name' => 'Proveedor Test']);

    return compact('tenant', 'user', 'supplier');
}

test('guests cannot access payables', function () {
    $this->get('/purchases/payables')->assertRedirect('/login');
});

test('authenticated users can view payables index', function () {
    ['user' => $user] = payableContext();

    $this->actingAs($user)
        ->get('/purchases/payables')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Purchases/Payables/Index')
            ->has('suppliers')
            ->has('totals')
        );
});

test('payables index shows correct balance', function () {
    ['tenant' => $tenant, 'user' => $user, 'supplier' => $supplier] = payableContext();

    PurchaseReceipt::factory()->for($tenant)->for($supplier)->create([
        'total' => 500000,
        'status' => 'received',
        'document_number' => 'FAC-001',
        'document_date' => now(),
    ]);

    SupplierPayment::factory()->for($tenant)->for($supplier)->create([
        'amount' => 200000,
        'payment_date' => now(),
    ]);

    $this->actingAs($user)
        ->get('/purchases/payables')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Purchases/Payables/Index')
            ->where('totals.total_purchased', 500000)
            ->where('totals.total_paid', 200000)
            ->where('totals.balance', 300000)
        );
});

test('users can view supplier account statement', function () {
    ['user' => $user, 'supplier' => $supplier] = payableContext();

    $this->actingAs($user)
        ->get("/purchases/payables/{$supplier->uuid}")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Purchases/Payables/Show')
            ->has('supplier')
            ->has('movements')
            ->has('summary')
            ->has('bankAccounts')
        );
});

test('registering a payment creates supplier payment and bank movement', function () {
    ['tenant' => $tenant, 'user' => $user, 'supplier' => $supplier] = payableContext();
    $bankAccount = BankAccount::factory()->for($tenant)->create(['is_active' => true]);

    $this->actingAs($user)
        ->post("/purchases/payables/{$supplier->uuid}/payments", [
            'bank_account_id' => $bankAccount->id,
            'payment_date' => '2026-05-25',
            'amount' => 300000,
            'reference' => 'TRF-12345',
        ])
        ->assertRedirect();

    $payment = SupplierPayment::where('supplier_id', $supplier->id)->first();
    expect($payment)->not->toBeNull()
        ->and($payment->amount)->toBe(300000)
        ->and($payment->bank_account_id)->toBe($bankAccount->id);

    $movement = BankAccountMovement::where('supplier_payment_id', $payment->id)->first();
    expect($movement)->not->toBeNull()
        ->and($movement->type)->toBe('outflow')
        ->and($movement->amount)->toBe(300000)
        ->and($movement->status)->toBe('confirmed');
});

test('registering a payment without bank account skips bank movement', function () {
    ['user' => $user, 'supplier' => $supplier] = payableContext();

    $this->actingAs($user)
        ->post("/purchases/payables/{$supplier->uuid}/payments", [
            'bank_account_id' => null,
            'payment_date' => '2026-05-25',
            'amount' => 150000,
        ])
        ->assertRedirect();

    expect(SupplierPayment::where('supplier_id', $supplier->id)->count())->toBe(1);
    expect(BankAccountMovement::count())->toBe(0);
});
