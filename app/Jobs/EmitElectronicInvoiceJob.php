<?php

namespace App\Jobs;

use App\Actions\Fe\EmitElectronicInvoice;
use App\Enums\FeStatus;
use App\Models\Sale;
use App\Models\Tenant;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class EmitElectronicInvoiceJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 30;

    public function __construct(
        public readonly int $saleId,
        public readonly int $tenantId,
    ) {}

    public function handle(EmitElectronicInvoice $action): void
    {
        $sale = Sale::withoutGlobalScopes()->findOrFail($this->saleId);
        $tenant = Tenant::findOrFail($this->tenantId);

        $action->execute($sale, $tenant);
    }

    public function failed(Throwable $exception): void
    {
        Sale::withoutGlobalScopes()
            ->find($this->saleId)
            ?->update([
                'fe_status' => FeStatus::Contingency,
                'fe_error_message' => $exception->getMessage(),
            ]);
    }
}
