<?php

namespace App\Console\Commands;

use App\Models\SyncCheckpoint;
use App\Models\Tenant;
use App\Services\Sync\ConnectivityService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

#[Signature('sync:pull {--tenant= : ID del tenant (omitir para todos)} {--since= : ISO8601 timestamp de inicio (por defecto: último sync)}')]
#[Description('Descarga delta de datos maestros desde el cloud hacia el servidor local.')]
class SyncPullCommand extends Command
{
    public function handle(ConnectivityService $connectivity): int
    {
        if (config('sync.app_mode') !== 'local') {
            $this->warn('  Este comando solo aplica en servidores en modo local (APP_MODE=local).');

            return self::FAILURE;
        }

        if ($connectivity->isOffline()) {
            $this->error('  Sin conectividad — no se puede hacer pull.');

            return self::FAILURE;
        }

        $cloudUrl = rtrim(config('sync.cloud_sync_url'), '/');
        $secret = config('sync.cloud_sync_secret');

        if (empty($cloudUrl) || empty($secret)) {
            $this->error('  CLOUD_SYNC_URL y CLOUD_SYNC_SECRET deben estar configurados.');

            return self::FAILURE;
        }

        $tenantId = $this->option('tenant');
        $tenants = $tenantId ? Tenant::where('id', $tenantId)->get() : Tenant::all();

        foreach ($tenants as $tenant) {
            $this->line("  Pulling delta para tenant: <fg=yellow>{$tenant->name}</>");

            $checkpoint = SyncCheckpoint::firstOrCreate(
                ['tenant_id' => $tenant->id, 'server_id' => config('sync.server_id')],
                ['status' => 'pending'],
            );

            $since = $this->option('since')
                ?? $checkpoint->last_successful_sync_at?->toISOString()
                ?? '1970-01-01T00:00:00Z';

            try {
                $response = Http::withToken($secret)
                    ->timeout(30)
                    ->get("{$cloudUrl}/api/sync/delta", ['tenant_id' => $tenant->id, 'since' => $since]);

                $response->throw();
                $delta = $response->json();

                $applied = 0;
                foreach ($delta['master_data'] ?? [] as $table => $rows) {
                    foreach ($rows as $row) {
                        DB::table($table)->upsert([$row], ['uuid'], array_keys($row));
                        $applied++;
                    }
                }

                $this->line("  <fg=green>✓</> {$applied} registros aplicados.");
                $checkpoint->update(['status' => 'synced', 'last_successful_sync_at' => now(), 'error_detail' => null]);
            } catch (\Throwable $e) {
                $this->error("  Error: {$e->getMessage()}");
                $checkpoint->update(['status' => 'error', 'error_detail' => $e->getMessage()]);
            }
        }

        return self::SUCCESS;
    }
}
