<?php

namespace App\Console\Commands;

use App\Actions\Inventory\GenerateInventoryAlerts;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('inventory:alerts {--email : Enviar email ademas de la notificacion interna}')]
#[Description('Genera alertas de stock bajo y vencimientos de inventario')]
class GenerateInventoryAlertsCommand extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(GenerateInventoryAlerts $generateInventoryAlerts): int
    {
        $summary = $generateInventoryAlerts->execute((bool) $this->option('email'));

        $this->components->info("Alertas generadas: {$summary['alerts']} para {$summary['recipients']} destinatarios.");

        return self::SUCCESS;
    }
}
