<?php

use App\Actions\Inventory\GenerateInventoryAlerts;
use App\Models\InventoryLot;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\InventoryAlertNotification;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use NotificationChannels\WebPush\WebPushChannel;

uses(RefreshDatabase::class);

function notificationUser(Tenant $tenant, string $role = 'owner'): User
{
    app(RoleAndPermissionSeeder::class)->run();

    $user = User::factory()->for($tenant)->create();
    $user->assignRole($role);

    return $user;
}

test('inventory alert generation notifies users about low stock', function () {
    $tenant = Tenant::factory()->create();
    $user = notificationUser($tenant);

    Product::factory()->for($tenant)->create([
        'commercial_name' => 'Acetaminofen 500mg',
        'minimum_stock' => 10,
    ]);

    $summary = app(GenerateInventoryAlerts::class)->execute();

    expect($summary['alerts'])->toBe(1)
        ->and($user->fresh()->unreadNotifications)->toHaveCount(1)
        ->and($user->fresh()->unreadNotifications->first()->data['title'])->toBe('Producto agotado');

    $this->actingAs($user)
        ->get('/profile')
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->where('auth.notifications.unread_count', 1)
            ->where('auth.notifications.items.0.title', 'Producto agotado')
        );
});

test('inventory alert generation notifies users about expiring lots', function () {
    $tenant = Tenant::factory()->create();
    $user = notificationUser($tenant, 'warehouse');
    $product = Product::factory()->for($tenant)->create([
        'commercial_name' => 'Loratadina jarabe',
        'minimum_stock' => 0,
    ]);

    InventoryLot::factory()->for($tenant)->for($product)->create([
        'lot_number' => 'L-EXP-01',
        'expires_on' => today()->addDays(15),
        'current_quantity' => 8,
    ]);

    app(GenerateInventoryAlerts::class)->execute();

    $notification = $user->fresh()->unreadNotifications->first();

    expect($notification)->not->toBeNull()
        ->and($notification->data['title'])->toBe('Lote por vencer')
        ->and($notification->data['body'])->toContain('L-EXP-01');
});

test('users can mark only their own notifications as read', function () {
    $firstTenant = Tenant::factory()->create();
    $secondTenant = Tenant::factory()->create();
    $firstUser = notificationUser($firstTenant);
    $secondUser = notificationUser($secondTenant);

    $firstUser->notify(new InventoryAlertNotification([
        'alert_key' => 'inventory:test',
        'title' => 'Stock bajo',
        'body' => 'Producto de prueba',
        'href' => '/inventory',
    ]));

    $notification = $firstUser->fresh()->unreadNotifications->first();

    $this->actingAs($secondUser)
        ->patch("/notifications/{$notification->id}/read")
        ->assertNotFound();

    expect($notification->fresh()->read_at)->toBeNull();

    $this->actingAs($firstUser)
        ->patch("/notifications/{$notification->id}/read")
        ->assertRedirect();

    expect($notification->fresh()->read_at)->not->toBeNull();
});

test('inventory alerts are sent through the web push channel', function () {
    $notification = new InventoryAlertNotification([
        'alert_key' => 'inventory:test',
        'title' => 'Stock bajo',
        'body' => 'Producto de prueba',
        'href' => '/inventory',
    ]);

    expect($notification->via((object) []))->toContain(WebPushChannel::class);

    $message = $notification->toWebPush((object) [], $notification)->toArray();

    expect($message['title'])->toBe('Stock bajo')
        ->and($message['body'])->toBe('Producto de prueba')
        ->and($message['data'])->toBe(['url' => '/inventory']);
});

test('a user with no push subscriptions does not fail when notified', function () {
    $tenant = Tenant::factory()->create();
    $user = notificationUser($tenant);

    $user->notify(new InventoryAlertNotification([
        'alert_key' => 'inventory:test',
        'title' => 'Stock bajo',
        'body' => 'Producto de prueba',
        'href' => '/inventory',
    ]));

    expect($user->fresh()->unreadNotifications)->toHaveCount(1);
});
