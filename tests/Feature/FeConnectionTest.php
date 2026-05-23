<?php

use App\Actions\Fe\TestNextpymeConnection;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

test('nextpyme connection test reports online company data', function () {
    Config::set('fe.api_url', 'https://api.example.test/api');
    Config::set('fe.api_token', 'global-token');
    Http::fake([
        'api.example.test/api/config/company' => Http::response([
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
    Http::fake([
        'api.example.test/api/config/company' => Http::response(['message' => 'Unauthenticated.'], 401),
    ]);

    $tenant = Tenant::factory()->create();

    $result = app(TestNextpymeConnection::class)->execute($tenant);

    expect($result)
        ->ok->toBeFalse()
        ->status_code->toBe(401)
        ->message->toBe('Unauthenticated.');
});
