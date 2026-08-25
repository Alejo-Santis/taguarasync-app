<?php

use App\Models\BankAccount;
use App\Models\Customer;
use App\Models\CustomerCollection;
use App\Models\Sale;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

function receivableUser(Tenant $tenant, string $role = 'owner'): User
{
    app(RoleAndPermissionSeeder::class)->run();

    $user = User::factory()->for($tenant)->create();
    $user->assignRole($role);

    return $user;
}

function receivableCustomer(Tenant $tenant, array $overrides = []): Customer
{
    return Customer::create([
        'tenant_id' => $tenant->id,
        'uuid' => (string) Str::uuid(),
        'identification_type_code' => 'CC',
        'identification_number' => (string) fake()->unique()->numberBetween(1000000, 9999999),
        'first_name' => 'Carlos',
        'last_name' => 'Ramirez',
        'is_active' => true,
        ...$overrides,
    ]);
}

function creditSaleFor(Tenant $tenant, User $user, Customer $customer, int $total, array $overrides = []): Sale
{
    return Sale::withoutGlobalScopes()->create([
        'uuid' => (string) Str::uuid(),
        'tenant_id' => $tenant->id,
        'user_id' => $user->id,
        'customer_id' => $customer->id,
        'document_number' => 'FE-'.fake()->unique()->numberBetween(1000, 9999),
        'subtotal' => $total,
        'tax_total' => 0,
        'total' => $total,
        'payment_method' => 'cash',
        'payment_form' => '2',
        'status' => 'completed',
        ...$overrides,
    ]);
}

test('guests cannot access the receivables index or a customer statement', function () {
    $tenant = Tenant::factory()->create();
    $customer = receivableCustomer($tenant);

    $this->get('/sales/receivables')->assertRedirect('/login');
    $this->get("/sales/receivables/{$customer->uuid}")->assertRedirect('/login');
});

test('receivables index lists only customers with credit sales or collections and computes balance', function () {
    $tenant = Tenant::factory()->create();
    $user = receivableUser($tenant);

    $withCredit = receivableCustomer($tenant, ['first_name' => 'Con', 'last_name' => 'Credito']);
    creditSaleFor($tenant, $user, $withCredit, 100000);

    receivableCustomer($tenant, ['first_name' => 'Sin', 'last_name' => 'Compras']);

    $this->actingAs($user)
        ->get('/sales/receivables')
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Sales/Receivables/Index')
            ->has('customers.data', 1)
            ->where('customers.data.0.full_name', 'Con Credito')
            ->where('customers.data.0.total_invoiced', 100000)
            ->where('customers.data.0.balance', 100000)
            ->where('totals.balance', 100000)
        );
});

test('customer statement shows invoices and collections with a running balance', function () {
    $tenant = Tenant::factory()->create();
    $user = receivableUser($tenant);
    $customer = receivableCustomer($tenant);

    creditSaleFor($tenant, $user, $customer, 150000);

    CustomerCollection::create([
        'tenant_id' => $tenant->id,
        'customer_id' => $customer->id,
        'user_id' => $user->id,
        'collection_date' => '2026-06-01',
        'amount' => 50000,
        'reference' => 'CONS-001',
    ]);

    $this->actingAs($user)
        ->get("/sales/receivables/{$customer->uuid}")
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Sales/Receivables/Show')
            ->has('movements.data', 2)
            ->where('summary.total_invoiced', 150000)
            ->where('summary.total_collected', 50000)
            ->where('summary.balance', 100000)
        );
});

test('registering a collection reduces the outstanding balance', function () {
    $tenant = Tenant::factory()->create();
    $user = receivableUser($tenant);
    $customer = receivableCustomer($tenant);
    $bankAccount = BankAccount::factory()->for($tenant)->create(['is_active' => true]);

    creditSaleFor($tenant, $user, $customer, 80000);

    $this->actingAs($user)
        ->post("/sales/receivables/{$customer->uuid}/collections", [
            'bank_account_id' => $bankAccount->id,
            'collection_date' => '2026-06-02',
            'amount' => 30000,
            'reference' => 'TRF-778',
        ])
        ->assertRedirect();

    expect((int) CustomerCollection::where('customer_id', $customer->id)->sum('amount'))->toBe(30000);

    $this->actingAs($user)
        ->get("/sales/receivables/{$customer->uuid}")
        ->assertInertia(fn (Assert $page) => $page
            ->where('summary.balance', 50000)
        );
});

test('a collection amount must be at least 1', function () {
    $tenant = Tenant::factory()->create();
    $user = receivableUser($tenant);
    $customer = receivableCustomer($tenant);
    creditSaleFor($tenant, $user, $customer, 80000);

    $this->actingAs($user)
        ->post("/sales/receivables/{$customer->uuid}/collections", [
            'collection_date' => '2026-06-02',
            'amount' => 0,
        ])
        ->assertSessionHasErrors('amount');

    expect(CustomerCollection::where('customer_id', $customer->id)->count())->toBe(0);
});

test('users without sales.cancel permission cannot register a collection', function () {
    $tenant = Tenant::factory()->create();
    $owner = receivableUser($tenant);
    app(RoleAndPermissionSeeder::class)->run();
    $cashier = User::factory()->for($tenant)->create();
    $cashier->assignRole('cashier');

    $customer = receivableCustomer($tenant);
    creditSaleFor($tenant, $owner, $customer, 80000);

    $this->actingAs($cashier)
        ->from('/dashboard')
        ->post("/sales/receivables/{$customer->uuid}/collections", [
            'collection_date' => '2026-06-02',
            'amount' => 10000,
        ])
        ->assertRedirect('/dashboard')
        ->assertSessionHas('error');

    expect(CustomerCollection::where('customer_id', $customer->id)->count())->toBe(0);
});
