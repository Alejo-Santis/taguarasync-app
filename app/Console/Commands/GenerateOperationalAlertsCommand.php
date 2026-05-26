<?php

namespace App\Console\Commands;

use App\Actions\Operations\GenerateOperationalAlerts;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('operations:alerts {--email : Enviar email ademas de la notificacion interna}')]
#[Description('Genera alertas por compras RADIAN pendientes y diferencias bancarias')]
class GenerateOperationalAlertsCommand extends Command
{
    public function handle(GenerateOperationalAlerts $generateOperationalAlerts): int
    {
        $summary = $generateOperationalAlerts->execute((bool) $this->option('email'));

        $this->components->info("Alertas operativas generadas: {$summary['alerts']} para {$summary['recipients']} destinatarios.");

        return self::SUCCESS;
    }
}
