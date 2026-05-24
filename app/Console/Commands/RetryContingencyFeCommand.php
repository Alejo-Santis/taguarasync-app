<?php

namespace App\Console\Commands;

use App\Enums\FeStatus;
use App\Jobs\EmitElectronicInvoiceJob;
use App\Models\Sale;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('fe:retry-contingency {--limit=50 : Maximo de facturas a reintentar}')]
#[Description('Reintenta facturas electronicas pendientes o en contingencia')]
class RetryContingencyFeCommand extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $count = 0;

        Sale::withoutGlobalScopes()
            ->whereIn('fe_status', [FeStatus::Pending, FeStatus::Contingency])
            ->oldest('fe_sent_at')
            ->limit((int) $this->option('limit'))
            ->get(['id', 'tenant_id', 'document_number'])
            ->each(function (Sale $sale) use (&$count): void {
                $sale->update(['fe_status' => FeStatus::Pending, 'fe_error_message' => null]);
                EmitElectronicInvoiceJob::dispatch($sale->id, $sale->tenant_id);
                $count++;
            });

        $this->components->info("Facturas FE reintentadas: {$count}.");

        return self::SUCCESS;
    }
}
