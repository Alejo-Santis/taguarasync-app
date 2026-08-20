<?php

use App\Enums\FeStatus;
use App\Enums\PaymentForm;
use App\Enums\PaymentMethod;
use App\Enums\SaleStatus;
use App\Jobs\EmitElectronicInvoiceJob;
use App\Models\InventoryLot;
use App\Models\Product;
use App\Models\ProductPresentation;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

function saleManagementUser(Tenant $tenant, string $role = 'owner'): User
{
    app(RoleAndPermissionSeeder::class)->run();

    $user = User::factory()->for($tenant)->create();
    $user->assignRole($role);

    return $user;
}

/**
 * @return array{sale: Sale, lot: InventoryLot, item: SaleItem}
 */
function saleWithLotAndItem(Tenant $tenant, User $user, FeStatus $feStatus = FeStatus::Accepted, array $saleOverrides = []): array
{
    $product = Product::factory()->for($tenant)->create(['commercial_name' => 'Acetaminofen 500mg']);
    $presentation = ProductPresentation::factory()->for($tenant)->for($product)->create([
        'minimum_unit_quantity' => 1,
    ]);

    $lot = InventoryLot::factory()->for($tenant)->for($product)->create([
        'product_presentation_id' => $presentation->id,
        'initial_quantity' => 100,
        'current_quantity' => 90,
    ]);

    $sale = Sale::withoutGlobalScopes()->create([
        'uuid' => (string) Str::uuid(),
        'tenant_id' => $tenant->id,
        'user_id' => $user->id,
        'document_number' => 'FE-'.fake()->unique()->numberBetween(1000, 9999),
        'subtotal' => 10000,
        'tax_total' => 1900,
        'total' => 11900,
        'payment_method' => PaymentMethod::Cash,
        'payment_form' => PaymentForm::Cash,
        'status' => SaleStatus::Completed,
        'fe_status' => $feStatus,
        ...$saleOverrides,
    ]);

    $item = SaleItem::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'sale_id' => $sale->id,
        'product_id' => $product->id,
        'product_presentation_id' => $presentation->id,
        'inventory_lot_id' => $lot->id,
        'description' => 'Acetaminofen 500mg',
        'quantity' => 10,
        'unit_price' => 1000,
        'tax_rate' => 19,
        'line_subtotal' => 10000,
        'line_tax' => 1900,
        'line_total' => 11900,
    ]);

    return ['sale' => $sale, 'lot' => $lot, 'item' => $item];
}

test('guests cannot access the sales index or a sale detail', function () {
    $tenant = Tenant::factory()->create();
    $user = saleManagementUser($tenant);
    ['sale' => $sale] = saleWithLotAndItem($tenant, $user);

    $this->get('/sales')->assertRedirect('/login');
    $this->get("/sales/{$sale->uuid}")->assertRedirect('/login');
});

test('users without sales.view permission are redirected away from the sales index', function () {
    $tenant = Tenant::factory()->create();
    app(RoleAndPermissionSeeder::class)->run();
    $user = User::factory()->for($tenant)->create();
    $user->assignRole('warehouse');

    $this->actingAs($user)
        ->from('/dashboard')
        ->get('/sales')
        ->assertRedirect('/dashboard')
        ->assertSessionHas('error');
});

test('sales index lists completed sales for the tenant', function () {
    $tenant = Tenant::factory()->create();
    $user = saleManagementUser($tenant);
    ['sale' => $sale] = saleWithLotAndItem($tenant, $user);

    $this->actingAs($user)
        ->get('/sales')
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Sales/Index')
            ->has('sales.data', 1)
            ->where('sales.data.0.document_number', $sale->document_number)
            ->where('stats.count_today', 1)
        );
});

test('sales index filters by document number', function () {
    $tenant = Tenant::factory()->create();
    $user = saleManagementUser($tenant);
    saleWithLotAndItem($tenant, $user, saleOverrides: ['document_number' => 'FE-4001']);
    saleWithLotAndItem($tenant, $user, saleOverrides: ['document_number' => 'FE-9002']);

    $this->actingAs($user)
        ->get('/sales?q=4001')
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Sales/Index')
            ->has('sales.data', 1)
            ->where('sales.data.0.document_number', 'FE-4001')
        );
});

test('sales index does not leak sales from another tenant', function () {
    $tenant = Tenant::factory()->create();
    $otherTenant = Tenant::factory()->create();
    $user = saleManagementUser($tenant);
    $otherUser = saleManagementUser($otherTenant);

    saleWithLotAndItem($otherTenant, $otherUser);

    $this->actingAs($user)
        ->get('/sales')
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Sales/Index')
            ->has('sales.data', 0)
        );
});

test('sale show returns items and fe details', function () {
    $tenant = Tenant::factory()->create();
    $user = saleManagementUser($tenant);
    ['sale' => $sale] = saleWithLotAndItem($tenant, $user);

    $this->actingAs($user)
        ->get("/sales/{$sale->uuid}")
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Sales/Show')
            ->where('sale.document_number', $sale->document_number)
            ->where('sale.total', 11900)
            ->has('sale.items', 1)
            ->where('sale.fe.status', 'accepted')
        );
});

test('voiding a completed sale restores inventory and marks it voided', function () {
    $tenant = Tenant::factory()->create();
    $user = saleManagementUser($tenant);
    ['sale' => $sale, 'lot' => $lot] = saleWithLotAndItem($tenant, $user);

    $this->actingAs($user)
        ->post("/sales/{$sale->uuid}/void")
        ->assertRedirect();

    expect($sale->fresh()->status)->toBe(SaleStatus::Voided)
        ->and($lot->fresh()->current_quantity)->toBe(100);
});

test('a sale cannot be voided twice', function () {
    $tenant = Tenant::factory()->create();
    $user = saleManagementUser($tenant);
    ['sale' => $sale] = saleWithLotAndItem($tenant, $user, saleOverrides: ['status' => SaleStatus::Voided]);

    $this->actingAs($user)
        ->post("/sales/{$sale->uuid}/void")
        ->assertSessionHasErrors('void');

    expect($sale->fresh()->status)->toBe(SaleStatus::Voided);
});

test('users without sales.cancel permission cannot void a sale', function () {
    $tenant = Tenant::factory()->create();
    $owner = saleManagementUser($tenant);
    app(RoleAndPermissionSeeder::class)->run();
    $cashier = User::factory()->for($tenant)->create();
    $cashier->assignRole('cashier');

    ['sale' => $sale] = saleWithLotAndItem($tenant, $owner);

    $this->actingAs($cashier)
        ->from('/dashboard')
        ->post("/sales/{$sale->uuid}/void")
        ->assertRedirect('/dashboard')
        ->assertSessionHas('error');

    expect($sale->fresh()->status)->toBe(SaleStatus::Completed);
});

test('retrying fe for a pending sale dispatches the emission job', function () {
    Queue::fake();

    $tenant = Tenant::factory()->create();
    $user = saleManagementUser($tenant);
    ['sale' => $sale] = saleWithLotAndItem($tenant, $user, FeStatus::Rejected, [
        'fe_error_message' => 'CUFE inválido.',
    ]);

    $this->actingAs($user)
        ->post("/sales/{$sale->uuid}/retry-fe")
        ->assertRedirect();

    $sale->refresh();

    expect($sale->fe_status)->toBe(FeStatus::Pending)
        ->and($sale->fe_error_message)->toBeNull();

    Queue::assertPushed(
        EmitElectronicInvoiceJob::class,
        fn (EmitElectronicInvoiceJob $job): bool => $job->saleId === $sale->id && $job->tenantId === $tenant->id
    );
});

test('retrying fe for an already accepted sale is rejected', function () {
    Queue::fake();

    $tenant = Tenant::factory()->create();
    $user = saleManagementUser($tenant);
    ['sale' => $sale] = saleWithLotAndItem($tenant, $user, FeStatus::Accepted);

    $this->actingAs($user)
        ->post("/sales/{$sale->uuid}/retry-fe")
        ->assertSessionHasErrors('fe');

    Queue::assertNotPushed(EmitElectronicInvoiceJob::class);
});
