<?php

use App\Models\InventoryLot;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

test('guests cannot access the profitability report', function () {
    $this->get('/reports/profitability')->assertRedirect('/login');
});

test('profitability report computes margin from sale price minus lot cost', function () {
    $tenant = Tenant::factory()->create();
    $user = createOwnerUser($tenant);
    $product = Product::factory()->for($tenant)->create([
        'commercial_name' => 'Amoxicilina 500mg',
        'generic_name' => 'Amoxicilina',
    ]);

    $lot = InventoryLot::factory()->for($tenant)->for($product)->create(['unit_cost' => 3000]);

    $sale = Sale::withoutGlobalScopes()->create([
        'uuid' => (string) Str::uuid(),
        'tenant_id' => $tenant->id,
        'document_number' => 'FE-5001',
        'subtotal' => 10000,
        'tax_total' => 1900,
        'total' => 11900,
        'payment_method' => 'cash',
        'status' => 'completed',
        'created_at' => Carbon::parse('2026-06-10 10:00:00'),
        'updated_at' => Carbon::parse('2026-06-10 10:00:00'),
    ]);

    SaleItem::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'sale_id' => $sale->id,
        'product_id' => $product->id,
        'inventory_lot_id' => $lot->id,
        'description' => 'Amoxicilina 500mg',
        'quantity' => 2,
        'unit_price' => 5000,
        'tax_rate' => 19,
        'line_subtotal' => 10000,
        'line_tax' => 1900,
        'line_total' => 11900,
    ]);

    $this->actingAs($user)
        ->get('/reports/profitability?from=2026-06-01&to=2026-06-30')
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Reports/Profitability')
            ->where('summary.revenue', 10000)
            ->where('summary.cost', 6000)
            ->where('summary.margin', 4000)
            ->has('rows', 1)
            ->where('rows.0.product', 'Amoxicilina 500mg')
            ->where('rows.0.units_sold', 2)
            ->where('rows.0.margin', 4000)
        );
});

test('profitability report swaps an inverted date range instead of erroring', function () {
    $tenant = Tenant::factory()->create();
    $user = createOwnerUser($tenant);

    $this->actingAs($user)
        ->get('/reports/profitability?from=2026-06-30&to=2026-06-01')
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->where('filters.from', '2026-06-01')
            ->where('filters.to', '2026-06-30')
        );
});
