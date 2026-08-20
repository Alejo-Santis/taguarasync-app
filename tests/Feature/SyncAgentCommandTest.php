<?php

use App\Jobs\SyncAgentJob;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

test('sync agent refuses to run outside local mode', function () {
    config(['sync.app_mode' => 'cloud']);

    $this->artisan('sync:agent', ['--once' => true])
        ->assertFailed();
});

test('sync agent dispatches a sync cycle only for tenants with offline sync enabled', function () {
    config(['sync.app_mode' => 'local']);
    Queue::fake();

    $enabled = Tenant::factory()->create(['offline_sync_enabled' => true]);
    Tenant::factory()->create(['offline_sync_enabled' => false]);

    $this->artisan('sync:agent', ['--once' => true])
        ->assertSuccessful();

    Queue::assertPushed(SyncAgentJob::class, 1);
    Queue::assertPushed(
        SyncAgentJob::class,
        fn (SyncAgentJob $job): bool => $job->tenantId === $enabled->id
    );
});
