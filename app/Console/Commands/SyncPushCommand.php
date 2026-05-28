<?php

namespace App\Console\Commands;

use App\Jobs\SyncAgentJob;
use App\Models\Tenant;
use App\Services\Sync\ConnectivityService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('sync:push {--tenant= : ID del tenant (omitir para todos)}')]
#[Description('Fuerza el envío de ventas e inventory_movements pendientes hacia el cloud.')]
class SyncPushCommand extends Command
{
    public function handle(ConnectivityService $connectivity): int
    {
        if (config('sync.app_mode') !== 'local') {
            $this->warn('  Este comando solo aplica en servidores en modo local (APP_MODE=local).');

            return self::FAILURE;
        }

        if ($connectivity->isOffline()) {
            $this->error('  Sin conectividad — no se puede hacer push.');

            return self::FAILURE;
        }

        $tenantId = $this->option('tenant');

        $tenants = $tenantId
            ? Tenant::where('id', $tenantId)->get()
            : Tenant::all();

        foreach ($tenants as $tenant) {
            $this->line("  Dispatching sync push para tenant: <fg=yellow>{$tenant->name}</>");
            SyncAgentJob::dispatch($tenant->id);
        }

        $this->info('  Jobs despachados. Revisar las colas para ver el resultado.');

        return self::SUCCESS;
    }
}
