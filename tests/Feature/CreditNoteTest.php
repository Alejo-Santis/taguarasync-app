<?php

use App\Enums\FeStatus;
use App\Enums\PaymentForm;
use App\Enums\PaymentMethod;
use App\Enums\SaleStatus;
use App\Jobs\EmitCreditNoteJob;
use App\Models\BankAccount;
use App\Models\BankAccountMovement;
use App\Models\CreditNote;
use App\Models\InventoryLot;
use App\Models\Product;
use App\Models\ProductPresentation;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SalePayment;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\DianCatalogsSeeder;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

function creditNoteUser(Tenant $tenant): User
{
    app(RoleAndPermissionSeeder::class)->run();
    app(DianCatalogsSeeder::class)->run();

    $user = User::factory()->for($tenant)->create();
    $user->assignRole('owner');

    return $user;
}

/**
 * @return array{sale: Sale, lot: InventoryLot, item: SaleItem}
 */
function creditNoteSaleSetup(Tenant $tenant, User $user, array $saleOverrides = []): array
{
    $product = Product::factory()->for($tenant)->create(['commercial_name' => 'Ibuprofeno 400mg']);
    $presentation = ProductPresentation::factory()->for($tenant)->for($product)->create([
        'minimum_unit_quantity' => 1,
    ]);

    $lot = InventoryLot::factory()->for($tenant)->for($product)->create([
        'product_presentation_id' => $presentation->id,
        'initial_quantity' => 50,
        'current_quantity' => 40,
    ]);

    $sale = Sale::withoutGlobalScopes()->create([
        'uuid' => (string) Str::uuid(),
        'tenant_id' => $tenant->id,
        'user_id' => $user->id,
        'document_number' => 'FE-'.fake()->unique()->numberBetween(1000, 9999),
        'subtotal' => 20000,
        'tax_total' => 3800,
        'total' => 23800,
        'payment_method' => PaymentMethod::Cash,
        'payment_form' => PaymentForm::Cash,
        'status' => SaleStatus::Completed,
        'fe_status' => FeStatus::Accepted,
        ...$saleOverrides,
    ]);

    $item = SaleItem::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'sale_id' => $sale->id,
        'product_id' => $product->id,
        'product_presentation_id' => $presentation->id,
        'inventory_lot_id' => $lot->id,
        'description' => 'Ibuprofeno 400mg',
        'quantity' => 10,
        'unit_price' => 2000,
        'tax_rate' => 19,
        'line_subtotal' => 20000,
        'line_tax' => 3800,
        'line_total' => 23800,
    ]);

    return ['sale' => $sale, 'lot' => $lot, 'item' => $item];
}

test('guests cannot access the credit note creation form', function () {
    $tenant = Tenant::factory()->create();
    $user = creditNoteUser($tenant);
    ['sale' => $sale] = creditNoteSaleSetup($tenant, $user);

    $this->get("/sales/{$sale->uuid}/credit-notes/create")->assertRedirect('/login');
});

test('credit note creation form shows sale items and discrepancy reasons', function () {
    $tenant = Tenant::factory()->create();
    $user = creditNoteUser($tenant);
    ['sale' => $sale] = creditNoteSaleSetup($tenant, $user);

    $this->actingAs($user)
        ->get("/sales/{$sale->uuid}/credit-notes/create")
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Sales/CreditNote/Create')
            ->where('sale.document_number', $sale->document_number)
            ->has('sale.items', 1)
            ->has('discrepancy_reasons')
        );
});

test('storing a full return credit note reverses inventory and totals', function () {
    $tenant = Tenant::factory()->create();
    $user = creditNoteUser($tenant);
    ['sale' => $sale, 'lot' => $lot, 'item' => $item] = creditNoteSaleSetup($tenant, $user);

    $this->actingAs($user)
        ->post("/sales/{$sale->uuid}/credit-notes", [
            'discrepancy_reason_code' => '1',
            'items' => [[
                'sale_item_id' => $item->id,
                'description' => $item->description,
                'quantity' => 10,
                'unit_price' => 2000,
                'tax_rate' => 19,
            ]],
        ])
        ->assertRedirect(route('sales.index'));

    expect(CreditNote::count())->toBe(1);

    $creditNote = CreditNote::first();

    expect($creditNote->subtotal)->toBe(20000)
        ->and($creditNote->tax_total)->toBe(3800)
        ->and($creditNote->total)->toBe(23800)
        ->and($creditNote->inventory_returned_at)->not->toBeNull()
        ->and($creditNote->payments_reversed_at)->not->toBeNull()
        ->and($lot->fresh()->current_quantity)->toBe(50);
});

test('storing a credit note for a sale paid by bank transfer creates a pending outflow movement', function () {
    $tenant = Tenant::factory()->create();
    $user = creditNoteUser($tenant);
    $bankAccount = BankAccount::factory()->for($tenant)->create(['is_active' => true]);

    ['sale' => $sale, 'item' => $item] = creditNoteSaleSetup($tenant, $user);

    SalePayment::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'sale_id' => $sale->id,
        'bank_account_id' => $bankAccount->id,
        'user_id' => $user->id,
        'amount' => 23800,
        'status' => 'pending',
    ]);

    $this->actingAs($user)
        ->post("/sales/{$sale->uuid}/credit-notes", [
            'discrepancy_reason_code' => '1',
            'items' => [[
                'sale_item_id' => $item->id,
                'description' => $item->description,
                'quantity' => 10,
                'unit_price' => 2000,
                'tax_rate' => 19,
            ]],
        ])
        ->assertRedirect(route('sales.index'));

    $creditNote = CreditNote::first();
    $movement = BankAccountMovement::withoutGlobalScopes()->where('credit_note_id', $creditNote->id)->first();

    expect($movement)->not->toBeNull()
        ->and($movement->type)->toBe('outflow')
        ->and($movement->amount)->toBe(23800)
        ->and($movement->bank_account_id)->toBe($bankAccount->id);
});

test('credit note items must belong to the sale being credited', function () {
    $tenant = Tenant::factory()->create();
    $user = creditNoteUser($tenant);
    ['sale' => $sale] = creditNoteSaleSetup($tenant, $user);
    ['item' => $foreignItem] = creditNoteSaleSetup($tenant, $user);

    $this->actingAs($user)
        ->post("/sales/{$sale->uuid}/credit-notes", [
            'discrepancy_reason_code' => '1',
            'items' => [[
                'sale_item_id' => $foreignItem->id,
                'description' => $foreignItem->description,
                'quantity' => 1,
                'unit_price' => 2000,
                'tax_rate' => 19,
            ]],
        ])
        ->assertSessionHasErrors('items.0.sale_item_id');

    expect(CreditNote::count())->toBe(0);
});

test('storing a credit note dispatches fe emission when the sale has a cufe and fe is enabled', function () {
    Queue::fake();
    config(['fe.enabled' => true]);

    $tenant = Tenant::factory()->create();
    $user = creditNoteUser($tenant);
    ['sale' => $sale, 'item' => $item] = creditNoteSaleSetup($tenant, $user, ['fe_cufe' => 'CUFE-TEST-1234']);

    $this->actingAs($user)
        ->post("/sales/{$sale->uuid}/credit-notes", [
            'discrepancy_reason_code' => '2',
            'items' => [[
                'sale_item_id' => $item->id,
                'description' => $item->description,
                'quantity' => 10,
                'unit_price' => 2000,
                'tax_rate' => 19,
            ]],
        ])
        ->assertRedirect(route('sales.index'));

    $creditNote = CreditNote::first();

    Queue::assertPushed(
        EmitCreditNoteJob::class,
        fn (EmitCreditNoteJob $job): bool => $job->creditNoteId === $creditNote->id
    );
});
