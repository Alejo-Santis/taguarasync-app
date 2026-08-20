<?php

use App\Models\FeSubmission;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

test('guests cannot access the audit page', function () {
    $this->get('/audit')->assertRedirect('/login');
});

test('audit page renders and defers fe submissions scoped to the tenant', function () {
    $tenant = Tenant::factory()->create();
    $otherTenant = Tenant::factory()->create();
    $user = createOwnerUser($tenant);

    FeSubmission::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'document_type' => 'sale',
        'document_id' => 1,
        'response_status' => 'accepted',
        'attempts' => 1,
        'is_non_recoverable' => false,
    ]);

    FeSubmission::withoutGlobalScopes()->create([
        'tenant_id' => $otherTenant->id,
        'document_type' => 'sale',
        'document_id' => 2,
        'response_status' => 'accepted',
        'attempts' => 1,
        'is_non_recoverable' => false,
    ]);

    $this->actingAs($user)
        ->get('/audit')
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Audit/Index')
            ->where('is_super_admin', false)
            ->missing('fe')
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->has('fe.rows', 1)
                ->where('fe.stats.total', 1)
            )
        );
});
