<?php

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

test('guests cannot access the profile page', function () {
    $this->get('/profile')->assertRedirect('/login');
});

test('authenticated users see their own profile data and 2fa status', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->for($tenant)->create([
        'name' => 'Alejo Santis',
        'email' => 'alejo@example.com',
        'two_factor_secret' => null,
        'two_factor_confirmed_at' => null,
    ]);

    $this->actingAs($user)
        ->get('/profile')
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Profile/Index')
            ->where('user.name', 'Alejo Santis')
            ->where('user.email', 'alejo@example.com')
            ->where('twoFactorEnabled', false)
            ->where('twoFactorConfirmed', false)
        );
});

test('profile reflects an enabled and confirmed two factor secret', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->for($tenant)->create([
        'two_factor_secret' => encrypt('secret'),
        'two_factor_confirmed_at' => now(),
    ]);

    $this->actingAs($user)
        ->get('/profile')
        ->assertInertia(fn (Assert $page) => $page
            ->where('twoFactorEnabled', true)
            ->where('twoFactorConfirmed', true)
        );
});
