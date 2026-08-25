<?php

use App\Models\Branch;
use App\Models\InventoryLot;
use App\Models\InventoryTransfer;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

function transferSetup(Tenant $tenant): array
{
    $origin = Branch::factory()->for($tenant)->create(['name' => 'Local Centro']);
    $destination = Branch::factory()->for($tenant)->create(['name' => 'Local Norte']);
    $product = Product::factory()->for($tenant)->create(['commercial_name' => 'Acetaminofen 500mg']);

    $lot = InventoryLot::factory()->for($tenant)->for($product)->create([
        'branch_id' => $origin->id,
        'lot_number' => 'LOT-TRF-1',
        'initial_quantity' => 200,
        'current_quantity' => 200,
    ]);

    return compact('origin', 'destination', 'product', 'lot');
}

test('guests cannot access inventory transfers', function () {
    $this->get('/inventory/transfers')->assertRedirect('/login');
});

test('users without inventory.transfer permission cannot see transfers', function () {
    $tenant = Tenant::factory()->create();
    app(RoleAndPermissionSeeder::class)->run();
    $cashier = User::factory()->for($tenant)->create();
    $cashier->assignRole('cashier');

    $this->actingAs($cashier)
        ->from('/dashboard')
        ->get('/inventory/transfers')
        ->assertRedirect('/dashboard')
        ->assertSessionHas('error');
});

test('warehouse users can create a draft transfer without moving stock', function () {
    $tenant = Tenant::factory()->create();
    app(RoleAndPermissionSeeder::class)->run();
    $user = User::factory()->for($tenant)->create();
    $user->assignRole('warehouse');
    ['origin' => $origin, 'destination' => $destination, 'lot' => $lot] = transferSetup($tenant);

    $this->actingAs($user)
        ->post('/inventory/transfers', [
            'from_branch_id' => $origin->id,
            'to_branch_id' => $destination->id,
            'items' => [[
                'lot_id' => $lot->id,
                'quantity' => 50,
            ]],
        ])
        ->assertRedirect();

    $transfer = InventoryTransfer::first();

    expect($transfer)->not->toBeNull()
        ->and($transfer->status->value)->toBe('draft')
        ->and($lot->fresh()->current_quantity)->toBe(200);
});

test('creating a transfer fails when the requested quantity exceeds available stock', function () {
    $tenant = Tenant::factory()->create();
    $user = createOwnerUser($tenant);
    ['origin' => $origin, 'destination' => $destination, 'lot' => $lot] = transferSetup($tenant);

    $this->actingAs($user)
        ->post('/inventory/transfers', [
            'from_branch_id' => $origin->id,
            'to_branch_id' => $destination->id,
            'items' => [[
                'lot_id' => $lot->id,
                'quantity' => 500,
            ]],
        ])
        ->assertSessionHasErrors('items');

    expect(InventoryTransfer::count())->toBe(0);
});

test('confirming a transfer moves stock out of origin and creates a new lot in destination', function () {
    $tenant = Tenant::factory()->create();
    $user = createOwnerUser($tenant);
    ['destination' => $destination, 'lot' => $lot] = transferSetup($tenant);

    $this->actingAs($user)
        ->post('/inventory/transfers', [
            'from_branch_id' => $lot->branch_id,
            'to_branch_id' => $destination->id,
            'items' => [[
                'lot_id' => $lot->id,
                'quantity' => 80,
            ]],
        ])
        ->assertRedirect();

    $transfer = InventoryTransfer::first();

    $this->actingAs($user)
        ->post("/inventory/transfers/{$transfer->uuid}/confirm")
        ->assertRedirect();

    $transfer->refresh();
    $destinationLot = InventoryLot::where('branch_id', $destination->id)
        ->where('lot_number', $lot->lot_number)
        ->first();

    expect($transfer->status->value)->toBe('confirmed')
        ->and($transfer->confirmed_at)->not->toBeNull()
        ->and($lot->fresh()->current_quantity)->toBe(120)
        ->and($destinationLot)->not->toBeNull()
        ->and($destinationLot->current_quantity)->toBe(80);
});

test('confirming a transfer twice is rejected and does not double-move stock', function () {
    $tenant = Tenant::factory()->create();
    $user = createOwnerUser($tenant);
    ['destination' => $destination, 'lot' => $lot] = transferSetup($tenant);

    $this->actingAs($user)->post('/inventory/transfers', [
        'from_branch_id' => $lot->branch_id,
        'to_branch_id' => $destination->id,
        'items' => [['lot_id' => $lot->id, 'quantity' => 30]],
    ]);
    $transfer = InventoryTransfer::first();
    $this->actingAs($user)->post("/inventory/transfers/{$transfer->uuid}/confirm");

    $this->actingAs($user)
        ->post("/inventory/transfers/{$transfer->uuid}/confirm")
        ->assertSessionHasErrors('transfer');

    expect($lot->fresh()->current_quantity)->toBe(170);
});

test('a draft transfer can be cancelled without moving stock', function () {
    $tenant = Tenant::factory()->create();
    $user = createOwnerUser($tenant);
    ['destination' => $destination, 'lot' => $lot] = transferSetup($tenant);

    $this->actingAs($user)->post('/inventory/transfers', [
        'from_branch_id' => $lot->branch_id,
        'to_branch_id' => $destination->id,
        'items' => [['lot_id' => $lot->id, 'quantity' => 30]],
    ]);
    $transfer = InventoryTransfer::first();

    $this->actingAs($user)
        ->post("/inventory/transfers/{$transfer->uuid}/cancel")
        ->assertRedirect();

    expect($transfer->fresh()->status->value)->toBe('cancelled')
        ->and($lot->fresh()->current_quantity)->toBe(200);
});

test('a confirmed transfer cannot be cancelled', function () {
    $tenant = Tenant::factory()->create();
    $user = createOwnerUser($tenant);
    ['destination' => $destination, 'lot' => $lot] = transferSetup($tenant);

    $this->actingAs($user)->post('/inventory/transfers', [
        'from_branch_id' => $lot->branch_id,
        'to_branch_id' => $destination->id,
        'items' => [['lot_id' => $lot->id, 'quantity' => 30]],
    ]);
    $transfer = InventoryTransfer::first();
    $this->actingAs($user)->post("/inventory/transfers/{$transfer->uuid}/confirm");

    $this->actingAs($user)
        ->post("/inventory/transfers/{$transfer->uuid}/cancel")
        ->assertSessionHasErrors('transfer');

    expect($transfer->fresh()->status->value)->toBe('confirmed');
});

test('transfer show returns items with lot and product details', function () {
    $tenant = Tenant::factory()->create();
    $user = createOwnerUser($tenant);
    ['destination' => $destination, 'lot' => $lot] = transferSetup($tenant);

    $this->actingAs($user)->post('/inventory/transfers', [
        'from_branch_id' => $lot->branch_id,
        'to_branch_id' => $destination->id,
        'notes' => 'Reposicion urgente',
        'items' => [['lot_id' => $lot->id, 'quantity' => 30]],
    ]);
    $transfer = InventoryTransfer::first();

    $this->actingAs($user)
        ->get("/inventory/transfers/{$transfer->uuid}")
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Inventory/Transfers/Show')
            ->where('transfer.notes', 'Reposicion urgente')
            ->has('transfer.items', 1)
            ->where('transfer.items.0.product_name', 'Acetaminofen 500mg')
            ->where('transfer.items.0.quantity', 30)
        );
});

test('the create form no longer preloads every lot, and the lots endpoint only returns stock for the requested branch', function () {
    $tenant = Tenant::factory()->create();
    $user = createOwnerUser($tenant);
    ['origin' => $origin, 'destination' => $destination, 'lot' => $lot] = transferSetup($tenant);

    $otherProduct = Product::factory()->for($tenant)->create(['commercial_name' => 'Ibuprofeno 400mg']);
    InventoryLot::factory()->for($tenant)->for($otherProduct)->create([
        'branch_id' => $destination->id,
        'lot_number' => 'LOT-TRF-2',
        'current_quantity' => 40,
    ]);

    $this->actingAs($user)
        ->get('/inventory/transfers/create')
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Inventory/Transfers/Create')
            ->missing('lots')
        );

    $this->actingAs($user)
        ->getJson("/inventory/transfers/lots?branch_id={$origin->id}")
        ->assertSuccessful()
        ->assertJsonCount(1, 'lots')
        ->assertJsonPath('lots.0.lot_number', 'LOT-TRF-1');

    $this->actingAs($user)
        ->getJson("/inventory/transfers/lots?branch_id={$destination->id}")
        ->assertSuccessful()
        ->assertJsonCount(1, 'lots')
        ->assertJsonPath('lots.0.lot_number', 'LOT-TRF-2');
});
