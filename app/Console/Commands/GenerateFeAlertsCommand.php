<?php

namespace App\Console\Commands;

use App\Actions\Fe\GenerateFeTransmissionAlerts;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('fe:alerts {--email : Enviar email ademas de la notificacion interna}')]
#[Description('Genera alertas de facturas FE pendientes, rechazadas o en contingencia')]
class GenerateFeAlertsCommand extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(GenerateFeTransmissionAlerts $generateFeTransmissionAlerts): int
    {
        $summary = $generateFeTransmissionAlerts->execute((bool) $this->option('email'));

        $this->components->info("Alertas FE generadas: {$summary['alerts']} para {$summary['recipients']} destinatarios.");

        return self::SUCCESS;
    }
}
