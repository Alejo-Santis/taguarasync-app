<?php

namespace App\Console\Commands;

use App\Models\SyncCheckpoint;
use App\Models\Tenant;
use App\Services\Sync\ConnectivityService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('sync:status')]
#[Description('Muestra el estado actual de sincronización del servidor local con el cloud.')]
class SyncStatusCommand extends Command
{
    public function handle(ConnectivityService $connectivity): int
    {
        $appMode = config('sync.app_mode', 'cloud');
        $serverId = config('sync.server_id', 'cloud');

        $this->line('');
        $this->line('  <fg=green>Taguara Sync — Estado</>');
        $this->line("  Modo: <fg=yellow>{$appMode}</>");
        $this->line("  Server ID: <fg=yellow>{$serverId}</>");

        if ($appMode === 'cloud') {
            $this->line('  <fg=green>✓ Servidor cloud — sin sincronización requerida.</>');

            return self::SUCCESS;
        }

        $online = $connectivity->isOnline();
        $statusStr = $online ? '<fg=green>ONLINE</>' : '<fg=red>OFFLINE</>';
        $this->line("  Conectividad: {$statusStr}");

        $checkpoints = SyncCheckpoint::where('server_id', $serverId)->get();

        if ($checkpoints->isEmpty()) {
            $this->line('  Sin checkpoints registrados todavía.');

            return self::SUCCESS;
        }

        $rows = $checkpoints->map(fn ($cp) => [
            Tenant::find($cp->tenant_id)?->name ?? "tenant #{$cp->tenant_id}",
            $cp->status,
            $cp->last_successful_sync_at?->format('d/m/Y H:i:s') ?? '—',
            $cp->error_detail ? substr($cp->error_detail, 0, 60) : '—',
        ])->toArray();

        $this->table(['Tenant', 'Estado', 'Último sync OK', 'Error'], $rows);

        return self::SUCCESS;
    }
}
