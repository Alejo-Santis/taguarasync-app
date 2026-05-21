<?php

namespace App\Jobs;

use App\Actions\Fe\EmitCreditNote;
use App\Models\CreditNote;
use App\Models\Tenant;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class EmitCreditNoteJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 30;

    public function __construct(
        public readonly int $creditNoteId,
        public readonly int $tenantId,
    ) {}

    public function handle(EmitCreditNote $action): void
    {
        $creditNote = CreditNote::withoutGlobalScopes()->findOrFail($this->creditNoteId);
        $tenant = Tenant::findOrFail($this->tenantId);

        $action->execute($creditNote, $tenant);
    }

    public function failed(Throwable $exception): void
    {
        CreditNote::withoutGlobalScopes()
            ->find($this->creditNoteId)
            ?->update(['fe_error_message' => $exception->getMessage()]);
    }
}
