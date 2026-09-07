<?php

use App\Models\Tenant;
use App\Models\TenantFeConfig;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

function nextpymeSyncUser(Tenant $tenant): User
{
    app(RoleAndPermissionSeeder::class)->run();

    $user = User::factory()->for($tenant)->create();
    $user->assignRole('owner');

    return $user;
}

test('nextpyme resolutions endpoint normalizes supported and unsupported document types', function () {
    Config::set('fe.api_url', 'https://api.example.test/api');

    $tenant = Tenant::factory()->create();
    TenantFeConfig::create([
        'tenant_id' => $tenant->id,
        'api_token' => 'tenant-token',
        'environment' => 'production',
    ]);
    $user = nextpymeSyncUser($tenant);

    Http::fake([
        'api.example.test/api/reports/resolutions' => Http::response([
            [
                'id' => 10,
                'type_document_id' => 1,
                'prefix' => 'FV',
                'resolution' => '18760000001',
                'resolution_date' => '2026-01-01',
                'technical_key' => 'abc123',
                'from' => 1,
                'to' => 5000,
                'date_from' => '2026-01-01',
                'date_to' => '2027-01-01',
                'number' => 42,
            ],
            [
                'id' => 11,
                'type_document_id' => 4,
                'prefix' => 'NC',
                'resolution' => '18760000002',
                'from' => 1,
                'to' => 99999999,
            ],
            [
                'id' => 12,
                'type_document_id' => 9,
                'prefix' => 'NOM',
                'from' => 1,
                'to' => 99999999,
            ],
        ]),
    ]);

    $response = $this->actingAs($user)
        ->getJson('/settings/fe/resolutions/nextpyme')
        ->assertOk()
        ->assertJsonPath('ok', true)
        ->json();

    expect($response['resolutions'])->toHaveCount(3);

    $invoice = $response['resolutions'][0];
    expect($invoice['type'])->toBe('invoice')
        ->and($invoice['supported'])->toBeTrue()
        ->and($invoice['technical_key'])->toBe('abc123')
        ->and($invoice['current_number'])->toBe(42);

    $creditNote = $response['resolutions'][1];
    expect($creditNote['type'])->toBe('credit_note')
        ->and($creditNote['supported'])->toBeTrue()
        ->and($creditNote['technical_key'])->toBeNull();

    $payroll = $response['resolutions'][2];
    expect($payroll['type'])->toBeNull()
        ->and($payroll['supported'])->toBeFalse();

    Http::assertSent(fn ($request): bool => $request->url() === 'https://api.example.test/api/reports/resolutions'
        && $request->hasHeader('Authorization', 'Bearer tenant-token'));
});

test('nextpyme resolutions endpoint reports a missing token', function () {
    Config::set('fe.api_url', 'https://api.example.test/api');
    Config::set('fe.api_token', '');

    $tenant = Tenant::factory()->create();
    $user = nextpymeSyncUser($tenant);

    $this->actingAs($user)
        ->getJson('/settings/fe/resolutions/nextpyme')
        ->assertOk()
        ->assertJsonPath('ok', false)
        ->assertJsonPath('message', 'No hay token API configurado.');
});
