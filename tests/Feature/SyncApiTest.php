<?php

use App\Models\ActiveIngredient;
use App\Models\InventoryLot;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SyncConflictLog;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

function syncSecret(): string
{
    config(['sync.cloud_sync_secret' => 'test-cloud-secret']);

    return 'test-cloud-secret';
}

test('sync ingest rejects requests without a valid bearer token', function () {
    syncSecret();
    $tenant = Tenant::factory()->create(['offline_sync_enabled' => true]);

    $this->postJson('/api/sync/ingest', [
        'server_id' => 'local-farmacia-norte',
        'tenant_id' => $tenant->id,
        'sales' => [],
        'movements' => [],
    ])->assertUnauthorized();
});

test('sync ingest rejects tenants without offline sync enabled on their plan', function () {
    $secret = syncSecret();
    $tenant = Tenant::factory()->create(['offline_sync_enabled' => false]);

    $this->withHeader('Authorization', "Bearer {$secret}")
        ->postJson('/api/sync/ingest', [
            'server_id' => 'local-farmacia-norte',
            'tenant_id' => $tenant->id,
            'sales' => [],
            'movements' => [],
        ])->assertForbidden();
});

test('sync ingest creates a new sale and preserves its original occurred date', function () {
    $secret = syncSecret();
    $tenant = Tenant::factory()->create(['offline_sync_enabled' => true]);
    $uuid = (string) Str::uuid();

    $this->withHeader('Authorization', "Bearer {$secret}")
        ->postJson('/api/sync/ingest', [
            'server_id' => 'local-farmacia-norte',
            'tenant_id' => $tenant->id,
            'sales' => [[
                'uuid' => $uuid,
                'tenant_id' => $tenant->id,
                'document_number' => 'LOCAL-0001',
                'subtotal' => 10000,
                'tax_total' => 1900,
                'total' => 11900,
                'payment_method' => 'cash',
                'status' => 'completed',
                'created_at' => '2026-05-10 09:30:00',
                'updated_at' => '2026-05-10 09:30:00',
            ]],
            'movements' => [],
        ])
        ->assertOk()
        ->assertJson(['ok' => true, 'ingested' => ['sales' => 1, 'movements' => 0, 'conflicts' => 0]]);

    $sale = Sale::withoutGlobalScopes()->where('uuid', $uuid)->first();

    expect($sale)->not->toBeNull()
        ->and($sale->server_id)->toBe('local-farmacia-norte')
        ->and($sale->synced_at)->not->toBeNull()
        ->and($sale->created_at->toDateTimeString())->toBe('2026-05-10 09:30:00');
});

test('sync ingest resolves conflicts for a sale that already exists and logs the resolution', function () {
    $secret = syncSecret();
    $tenant = Tenant::factory()->create(['offline_sync_enabled' => true]);
    $uuid = (string) Str::uuid();

    $existing = Sale::withoutGlobalScopes()->create([
        'uuid' => $uuid,
        'tenant_id' => $tenant->id,
        'document_number' => 'LOCAL-0002',
        'subtotal' => 5000,
        'tax_total' => 950,
        'total' => 5950,
        'payment_method' => 'cash',
        'status' => 'completed',
        'created_at' => '2026-05-10 08:00:00',
        'updated_at' => '2026-05-10 08:00:00',
    ]);

    $this->withHeader('Authorization', "Bearer {$secret}")
        ->postJson('/api/sync/ingest', [
            'server_id' => 'local-farmacia-norte',
            'tenant_id' => $tenant->id,
            'sales' => [[
                'uuid' => $uuid,
                'tenant_id' => $tenant->id,
                'document_number' => 'LOCAL-0002',
                'status' => 'voided',
                'updated_at' => '2026-05-10 10:00:00',
            ]],
            'movements' => [],
        ])
        ->assertOk()
        ->assertJson(['ingested' => ['sales' => 0, 'movements' => 0, 'conflicts' => 1]]);

    expect($existing->fresh()->status->value)->toBe('voided');

    $log = SyncConflictLog::withoutGlobalScopes()->where('record_uuid', $uuid)->first();
    expect($log)->not->toBeNull()
        ->and($log->table_name)->toBe('sales')
        ->and($log->strategy_applied)->toBe('append');
});

test('sync ingest is append-only for inventory movements and ignores duplicates', function () {
    $secret = syncSecret();
    $tenant = Tenant::factory()->create(['offline_sync_enabled' => true]);
    $product = Product::factory()->for($tenant)->create();
    $lot = InventoryLot::factory()->for($tenant)->for($product)->create([
        'current_quantity' => 40,
    ]);
    $uuid = (string) Str::uuid();

    $payload = [
        'server_id' => 'local-farmacia-norte',
        'tenant_id' => $tenant->id,
        'sales' => [],
        'movements' => [[
            'uuid' => $uuid,
            'tenant_id' => $tenant->id,
            'inventory_lot_id' => $lot->id,
            'product_id' => $product->id,
            'type' => 'sale',
            'quantity_delta' => -5,
            'quantity_before' => 40,
            'quantity_after' => 35,
            'occurred_at' => '2026-05-10 09:31:00',
        ]],
    ];

    $this->withHeader('Authorization', "Bearer {$secret}")
        ->postJson('/api/sync/ingest', $payload)
        ->assertOk()
        ->assertJson(['ingested' => ['sales' => 0, 'movements' => 1, 'conflicts' => 0]]);

    expect(InventoryMovement::withoutGlobalScopes()->where('uuid', $uuid)->count())->toBe(1);

    // Re-sending the same movement (retry after a dropped connection) must not duplicate it.
    $this->withHeader('Authorization', "Bearer {$secret}")
        ->postJson('/api/sync/ingest', $payload)
        ->assertOk()
        ->assertJson(['ingested' => ['sales' => 0, 'movements' => 0, 'conflicts' => 0]]);

    expect(InventoryMovement::withoutGlobalScopes()->where('uuid', $uuid)->count())->toBe(1);
});

test('sync delta returns only master data updated after the given timestamp for the requesting tenant', function () {
    $secret = syncSecret();
    $tenant = Tenant::factory()->create(['offline_sync_enabled' => true]);
    $otherTenant = Tenant::factory()->create(['offline_sync_enabled' => true]);

    $stale = Product::factory()->for($tenant)->create(['updated_at' => '2026-01-01 00:00:00']);
    $updated = Product::factory()->for($tenant)->create(['updated_at' => '2026-06-01 00:00:00']);
    Product::factory()->for($otherTenant)->create(['updated_at' => '2026-06-01 00:00:00']);

    $response = $this->withHeader('Authorization', "Bearer {$secret}")
        ->getJson('/api/sync/delta?tenant_id='.$tenant->id.'&since=2026-05-01T00:00:00Z')
        ->assertOk();

    $productUuids = collect($response->json('master_data.products'))->pluck('uuid');

    expect($productUuids)->toContain($updated->uuid)
        ->and($productUuids)->not->toContain($stale->uuid);
});

test('sync delta includes global catalogs shared across tenants without filtering by tenant', function () {
    $secret = syncSecret();
    $tenant = Tenant::factory()->create(['offline_sync_enabled' => true]);

    $ingredient = ActiveIngredient::create([
        'dci_name' => 'Acetaminofen-'.Str::random(8),
        'updated_at' => '2026-06-01 00:00:00',
    ]);

    $response = $this->withHeader('Authorization', "Bearer {$secret}")
        ->getJson('/api/sync/delta?tenant_id='.$tenant->id.'&since=2026-05-01T00:00:00Z')
        ->assertOk();

    $names = collect($response->json('master_data.active_ingredients'))->pluck('dci_name');
    expect($names)->toContain($ingredient->dci_name);
});

test('sync ingest for a tenant on a different plan is rejected even with a valid secret', function () {
    $secret = syncSecret();
    $tenant = Tenant::factory()->create(['offline_sync_enabled' => true]);
    $otherTenant = Tenant::factory()->create(['offline_sync_enabled' => false]);

    $this->withHeader('Authorization', "Bearer {$secret}")
        ->getJson('/api/sync/delta?tenant_id='.$otherTenant->id.'&since='.Carbon::now()->toISOString())
        ->assertForbidden();

    // Sanity check: the enabled tenant is unaffected and still reachable.
    $this->withHeader('Authorization', "Bearer {$secret}")
        ->getJson('/api/sync/delta?tenant_id='.$tenant->id.'&since='.Carbon::now()->toISOString())
        ->assertOk();
});
