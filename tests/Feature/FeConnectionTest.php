<?php

use App\Actions\Fe\TestNextpymeConnection;
use App\Models\Tenant;
use App\Models\TenantFeConfig;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

function feSettingsUser(Tenant $tenant): User
{
    app(RoleAndPermissionSeeder::class)->run();

    $user = User::factory()->for($tenant)->create();
    $user->assignRole('owner');

    return $user;
}

test('nextpyme connection test reports online company data', function () {
    Config::set('fe.api_url', 'https://api.example.test/api');
    Config::set('fe.api_token', 'global-token');
    Config::set('fe.ubl_prefix', '/ubl2.1');
    Http::fake([
        'api.example.test/api/ubl2.1/config/company' => Http::response([
            'business_name' => 'Farmacia Demo SAS',
            'identification_number' => '900123456',
        ]),
    ]);

    $tenant = Tenant::factory()->create();

    $result = app(TestNextpymeConnection::class)->execute($tenant);

    expect($result)
        ->ok->toBeTrue()
        ->message->toBe('Conexión exitosa con Nextpyme.')
        ->token_source->toBe('global')
        ->and($result['company']['name'])->toBe('Farmacia Demo SAS');
});

test('nextpyme connection test reports missing token', function () {
    Config::set('fe.api_url', 'https://api.example.test/api');
    Config::set('fe.api_token', '');

    $tenant = Tenant::factory()->create();

    $result = app(TestNextpymeConnection::class)->execute($tenant);

    expect($result)
        ->ok->toBeFalse()
        ->message->toBe('No hay token API configurado.')
        ->token_source->toBe('missing');
});

test('nextpyme connection test reports invalid token responses', function () {
    Config::set('fe.api_url', 'https://api.example.test/api');
    Config::set('fe.api_token', 'bad-token');
    Config::set('fe.ubl_prefix', '/ubl2.1');
    Http::fake([
        'api.example.test/api/ubl2.1/config/company' => Http::response(['message' => 'Unauthenticated.'], 401),
    ]);

    $tenant = Tenant::factory()->create();

    $result = app(TestNextpymeConnection::class)->execute($tenant);

    expect($result)
        ->ok->toBeFalse()
        ->status_code->toBe(401)
        ->message->toBe('Unauthenticated.');
});

test('fe settings page exposes the effective token for authorized users', function () {
    Config::set('fe.api_token', 'global-token-visible');
    $tenant = Tenant::factory()->create();
    $user = feSettingsUser($tenant);

    $this->actingAs($user)
        ->get('/settings/fe')
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Settings/Fe/Index')
            ->where('fe_config.api_token_value', 'global-token-visible')
            ->where('fe_config.api_token_source', 'global')
        );
});

test('fe connection endpoint uses temporary token from the form', function () {
    Config::set('fe.api_url', 'https://api.example.test/api');
    Config::set('fe.api_token', 'global-token');
    Config::set('fe.ubl_prefix', '/ubl2.1');
    Http::fake([
        'api.example.test/api/ubl2.1/config/company' => Http::response(['name' => 'Temporal Token Company']),
    ]);

    $tenant = Tenant::factory()->create();
    TenantFeConfig::create([
        'tenant_id' => $tenant->id,
        'api_token' => 'saved-token',
        'environment' => 'test',
    ]);
    $user = feSettingsUser($tenant);

    $this->actingAs($user)
        ->postJson('/settings/fe/test-connection', ['api_token' => 'temporary-token'])
        ->assertOk()
        ->assertJsonPath('ok', true)
        ->assertJsonPath('token_source', 'temporal')
        ->assertJsonPath('company.name', 'Temporal Token Company');
});
