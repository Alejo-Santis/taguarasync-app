<?php

use App\Models\FeResolution;
use App\Models\FeSend;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function feResolutionSettingsUser(Tenant $tenant): User
{
    app(RoleAndPermissionSeeder::class)->run();

    $user = User::factory()->for($tenant)->create();
    $user->assignRole('owner');

    return $user;
}

test('users can set the next document number when creating a resolution', function () {
    $tenant = Tenant::factory()->create();
    $user = feResolutionSettingsUser($tenant);

    $this->actingAs($user)
        ->post('/settings/fe/resolutions', [
            'code' => 'FV-PROD',
            'type' => 'invoice',
            'prefix' => 'FV',
            'resolution_number' => '18760000001',
            'resolution_date' => '2026-05-01',
            'technical_key' => 'technical-key',
            'from_number' => 1000,
            'to_number' => 2000,
            'next_document_number' => 1520,
            'valid_from' => '2026-05-01',
            'valid_until' => '2027-05-01',
            'environment' => 'production',
        ])
        ->assertRedirect();

    $resolution = FeResolution::withoutGlobalScopes()->firstOrFail();

    $this->assertDatabaseHas('fe_sends', [
        'tenant_id' => $tenant->id,
        'resolution_id' => $resolution->id,
        'next_consecutive' => 1519,
    ]);

    expect($resolution->consumeNextNumber())->toBe(1520);
});

test('users can adjust the next document number when editing a resolution', function () {
    $tenant = Tenant::factory()->create();
    $user = feResolutionSettingsUser($tenant);
    $resolution = FeResolution::create([
        'tenant_id' => $tenant->id,
        'code' => 'FV-PROD',
        'type' => 'invoice',
        'prefix' => 'FV',
        'resolution_number' => '18760000001',
        'resolution_date' => '2026-05-01',
        'technical_key' => 'technical-key',
        'from_number' => 1000,
        'to_number' => 2000,
        'valid_from' => '2026-05-01',
        'valid_until' => '2027-05-01',
        'environment' => 'production',
    ]);

    FeSend::create([
        'tenant_id' => $tenant->id,
        'resolution_id' => $resolution->id,
        'next_consecutive' => 1004,
    ]);

    $this->actingAs($user)
        ->put("/settings/fe/resolutions/{$resolution->id}", [
            'code' => 'FV-PROD',
            'type' => 'invoice',
            'prefix' => 'FV',
            'resolution_number' => '18760000001',
            'resolution_date' => '2026-05-01',
            'technical_key' => 'technical-key',
            'from_number' => 1000,
            'to_number' => 2000,
            'next_document_number' => 1700,
            'valid_from' => '2026-05-01',
            'valid_until' => '2027-05-01',
            'environment' => 'production',
        ])
        ->assertRedirect();

    expect($resolution->send()->first()->next_consecutive)->toBe(1699);
});
