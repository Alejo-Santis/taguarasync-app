<?php

namespace App\Http\Controllers\Sales;

use App\Enums\FeStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Sales\StoreCreditNoteRequest;
use App\Jobs\EmitCreditNoteJob;
use App\Models\CreditNote;
use App\Models\DianDiscrepancyReason;
use App\Models\Sale;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class CreditNoteController extends Controller
{
    public function create(Sale $sale): Response
    {
        $sale->load(['items.product', 'customer']);

        return Inertia::render('Sales/CreditNote/Create', [
            'sale' => [
                'uuid' => $sale->uuid,
                'document_number' => $sale->document_number,
                'invoice_prefix' => $sale->invoice_prefix,
                'fe_cufe' => $sale->fe_cufe,
                'total' => $sale->total,
                'created_at' => $sale->created_at->format('d/m/Y H:i'),
                'customer_name' => $sale->customer?->full_name ?? 'Consumidor final',
                'items' => $sale->items->map(fn ($i) => [
                    'id' => $i->id,
                    'description' => $i->description,
                    'quantity' => $i->quantity,
                    'unit_price' => $i->unit_price,
                    'tax_rate' => $i->tax_rate,
                    'line_total' => $i->line_total,
                ])->values(),
            ],
            'discrepancy_reasons' => DianDiscrepancyReason::where('applies_to', 'credit_note')
                ->orderBy('code')
                ->get(['code', 'name']),
        ]);
    }

    public function store(Sale $sale, StoreCreditNoteRequest $request): RedirectResponse
    {
        if (! $sale->fe_cufe) {
            return back()->withErrors(['fe' => 'La venta original no tiene CUFE. Emite primero la factura electrónica.']);
        }

        $data = $request->validated();
        $feEnabled = config('fe.enabled');

        $creditNote = DB::transaction(function () use ($sale, $data, $request, $feEnabled): CreditNote {
            $totals = $this->calculateTotals($data['items']);

            $creditNote = CreditNote::create([
                'uuid' => (string) Str::uuid(),
                'tenant_id' => $request->user()->tenant_id,
                'sale_id' => $sale->id,
                'user_id' => $request->user()->id,
                'number' => (string) time(),
                'discrepancy_reason_code' => $data['discrepancy_reason_code'],
                'subtotal' => $totals['subtotal'],
                'tax_total' => $totals['tax_total'],
                'total' => $totals['total'],
                'fe_status' => $feEnabled ? FeStatus::Pending : null,
                'notes' => $data['notes'] ?? null,
            ]);

            foreach ($data['items'] as $item) {
                $subtotal = $item['quantity'] * $item['unit_price'];
                $tax = (int) round($subtotal * ((float) $item['tax_rate'] / 100));

                $creditNote->items()->create([
                    'tenant_id' => $request->user()->tenant_id,
                    'description' => $item['description'],
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'tax_rate' => (float) $item['tax_rate'],
                    'line_subtotal' => $subtotal,
                    'line_tax' => $tax,
                    'line_total' => $subtotal + $tax,
                ]);
            }

            return $creditNote;
        });

        if ($feEnabled) {
            EmitCreditNoteJob::dispatch($creditNote->id, $request->user()->tenant_id)
                ->afterCommit();
        }

        return to_route('sales.index')->with('success', "Nota crédito {$creditNote->number} creada correctamente.");
    }

    /** @return array{subtotal: int, tax_total: int, total: int} */
    private function calculateTotals(array $items): array
    {
        return collect($items)->reduce(function (array $carry, array $item): array {
            $subtotal = $item['quantity'] * $item['unit_price'];
            $tax = (int) round($subtotal * ((float) $item['tax_rate'] / 100));

            return [
                'subtotal' => $carry['subtotal'] + $subtotal,
                'tax_total' => $carry['tax_total'] + $tax,
                'total' => $carry['total'] + $subtotal + $tax,
            ];
        }, ['subtotal' => 0, 'tax_total' => 0, 'total' => 0]);
    }
}
