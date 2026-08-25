<?php

use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use NotificationChannels\WebPush\PushSubscription;

uses(RefreshDatabase::class);

test('guests cannot manage push subscriptions', function () {
    $this->postJson('/push-subscriptions', ['endpoint' => 'https://push.example/1'])
        ->assertUnauthorized();

    $this->deleteJson('/push-subscriptions', ['endpoint' => 'https://push.example/1'])
        ->assertUnauthorized();
});

test('authenticated users can register a push subscription', function () {
    $tenant = Tenant::factory()->create();
    $user = createAdminUser($tenant);

    $this->actingAs($user)
        ->postJson('/push-subscriptions', [
            'endpoint' => 'https://push.example/subscription-1',
            'keys' => ['p256dh' => 'public-key', 'auth' => 'auth-token'],
        ])
        ->assertSuccessful()
        ->assertJson(['status' => 'subscribed']);

    expect(PushSubscription::where('endpoint', 'https://push.example/subscription-1')->exists())->toBeTrue();
});

test('authenticated users can remove a push subscription', function () {
    $tenant = Tenant::factory()->create();
    $user = createAdminUser($tenant);

    $user->updatePushSubscription('https://push.example/subscription-2', 'public-key', 'auth-token');

    $this->actingAs($user)
        ->deleteJson('/push-subscriptions', ['endpoint' => 'https://push.example/subscription-2'])
        ->assertSuccessful()
        ->assertJson(['status' => 'unsubscribed']);

    expect(PushSubscription::where('endpoint', 'https://push.example/subscription-2')->exists())->toBeFalse();
});
