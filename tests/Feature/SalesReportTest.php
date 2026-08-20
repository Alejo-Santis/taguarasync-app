<?php

use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

function reportSale(Tenant $tenant, array $overrides = []): Sale
{
    return Sale::withoutGlobalScopes()->create([
        'uuid' => (string) Str::uuid(),
        'tenant_id' => $tenant->id,
        'document_number' => 'FE-'.fake()->unique()->numberBetween(1000, 9999),
        'subtotal' => 10000,
        'tax_total' => 1900,
        'total' => 11900,
        'payment_method' => 'cash',
        'status' => 'completed',
        'created_at' => Carbon::parse('2026-06-10 10:00:00'),
        'updated_at' => Carbon::parse('2026-06-10 10:00:00'),
        ...$overrides,
    ]);
}

test('guests cannot access the sales report', function () {
    $this->get('/reports/sales')->assertRedirect('/login');
});

test('sales report totals and breaks down by payment method within the date range', function () {
    $tenant = Tenant::factory()->create();
    $user = createOwnerUser($tenant);
    $product = Product::factory()->for($tenant)->create(['commercial_name' => 'Ibuprofeno 400mg']);

    $cashSale = reportSale($tenant, ['payment_method' => 'cash', 'total' => 11900]);
    SaleItem::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'sale_id' => $cashSale->id,
        'product_id' => $product->id,
        'description' => 'Ibuprofeno 400mg',
        'quantity' => 2,
        'unit_price' => 5000,
        'tax_rate' => 19,
        'line_subtotal' => 10000,
        'line_tax' => 1900,
        'line_total' => 11900,
        'created_at' => Carbon::parse('2026-06-10 10:00:00'),
    ]);

    reportSale($tenant, ['payment_method' => 'card', 'total' => 50000, 'subtotal' => 42017, 'tax_total' => 7983]);

    // Outside the filtered range — must not be counted.
    reportSale($tenant, ['created_at' => Carbon::parse('2026-01-01 10:00:00'), 'updated_at' => Carbon::parse('2026-01-01 10:00:00')]);

    $this->actingAs($user)
        ->get('/reports/sales?from=2026-06-01&to=2026-06-30')
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Reports/Sales')
            ->where('totals.sales_count', 2)
            ->where('totals.gross_total', 61900)
            ->has('byDay', 1)
            ->where('byDay.0.total', 61900)
            ->has('topProducts', 1)
            ->where('topProducts.0.description', 'Ibuprofeno 400mg')
            ->where('topProducts.0.qty_sold', 2)
        );
});

test('sales report does not leak sales from another tenant', function () {
    $tenant = Tenant::factory()->create();
    $otherTenant = Tenant::factory()->create();
    $user = createOwnerUser($tenant);

    reportSale($otherTenant);

    $this->actingAs($user)
        ->get('/reports/sales?from=2026-06-01&to=2026-06-30')
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->where('totals.sales_count', 0)
        );
});
