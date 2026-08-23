<?php

use App\Services\Fe\NextpymeClient;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;

test('does not retry a permanent client error (4xx)', function () {
    Config::set('fe.api_url', 'https://api.example.test/api');
    Config::set('fe.ubl_prefix', '/ubl2.1');
    Http::fake(['api.example.test/*' => Http::response(['message' => 'NIT inválido'], 422)]);

    $client = app(NextpymeClient::class)->forTenant('tenant-token');

    try {
        $client->createInvoice(['number' => 1]);
    } catch (RuntimeException) {
        // Expected: 422 is a permanent rejection.
    }

    Http::assertSentCount(1);
});

test('retries a transient server error (5xx) up to the configured attempts', function () {
    Config::set('fe.api_url', 'https://api.example.test/api');
    Config::set('fe.ubl_prefix', '/ubl2.1');
    Http::fake(['api.example.test/*' => Http::response(['message' => 'Bad gateway'], 502)]);

    $client = app(NextpymeClient::class)->forTenant('tenant-token');

    try {
        $client->createInvoice(['number' => 1]);
    } catch (RuntimeException) {
        // Expected: still failing after retries exhausted.
    }

    Http::assertSentCount(2);
});

test('retries once more after a transient failure and succeeds', function () {
    Config::set('fe.api_url', 'https://api.example.test/api');
    Config::set('fe.ubl_prefix', '/ubl2.1');
    Http::fake([
        'api.example.test/*' => Http::sequence()
            ->push(['message' => 'Bad gateway'], 502)
            ->push(['cufe' => 'fake-cufe']),
    ]);

    $client = app(NextpymeClient::class)->forTenant('tenant-token');

    $result = $client->createInvoice(['number' => 1]);

    expect($result)->toBe(['cufe' => 'fake-cufe']);
    Http::assertSentCount(2);
});
