<?php

namespace App\Actions\Sales;

use App\Actions\Inventory\RegisterInventoryMovement;
use App\Enums\FeStatus;
use App\Jobs\EmitCreditNoteJob;
use App\Models\BankAccountMovement;
use App\Models\CreditNote;
use App\Models\InventoryLot;
use App\Models\ProductPresentation;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ProcessCreditNote
{
    /**
     * @param  array{
     *     discrepancy_reason_code: string,
     *     notes?: string|null,
     *     items: array<int, array{
     *         sale_item_id: int,
     *         description: string,
     *         quantity: int,
     *         unit_price: int,
     *         tax_rate: int|float|string
     *     }>
     * }  $data
     */
    public function execute(Sale $sale, array $data, User $user): CreditNote
    {
        $sale->loadMissing(['items', 'payments.paymentMethod']);

        $presentationIds = $sale->items->pluck('product_presentation_id')->filter()->unique();
        $minUnitsByPresentation = ProductPresentation::whereIn('id', $presentationIds)
            ->pluck('minimum_unit_quantity', 'id');

        $creditNote = DB::transaction(function () use ($sale, $data, $user, $minUnitsByPresentation): CreditNote {
            $totals = $this->calculateTotals($data['items']);
            $feEnabled = config('fe.enabled') && $sale->fe_cufe !== null;

            $creditNote = CreditNote::create([
                'uuid' => (string) Str::uuid(),
                'tenant_id' => $user->tenant_id,
                'sale_id' => $sale->id,
                'user_id' => $user->id,
                'number' => (string) time(),
                'discrepancy_reason_code' => $data['discrepancy_reason_code'],
                'subtotal' => $totals['subtotal'],
                'tax_total' => $totals['tax_total'],
                'total' => $totals['total'],
                'fe_status' => $feEnabled ? FeStatus::Pending : FeStatus::NotApplicable,
                'notes' => $data['notes'] ?? null,
            ]);

            foreach ($data['items'] as $itemData) {
                $saleItem = $sale->items->firstWhere('id', $itemData['sale_item_id']);
                $subtotal = $itemData['quantity'] * $itemData['unit_price'];
                $tax = (int) round($subtotal * ((float) $itemData['tax_rate'] / 100));

                $creditNote->items()->create([
                    'tenant_id' => $user->tenant_id,
                    'sale_item_id' => $saleItem?->id,
                    'product_id' => $saleItem?->product_id,
                    'product_presentation_id' => $saleItem?->product_presentation_id,
                    'description' => $itemData['description'],
                    'quantity' => $itemData['quantity'],
                    'unit_price' => $itemData['unit_price'],
                    'tax_rate' => (float) $itemData['tax_rate'],
                    'line_subtotal' => $subtotal,
                    'line_tax' => $tax,
                    'line_total' => $subtotal + $tax,
                ]);

                if ($saleItem?->inventory_lot_id) {
                    $minUnits = $itemData['quantity']
                        * ($minUnitsByPresentation[$saleItem->product_presentation_id] ?? 1);

                    $lot = InventoryLot::lockForUpdate()->findOrFail($saleItem->inventory_lot_id);

                    app(RegisterInventoryMovement::class)->saleReturn($lot, $minUnits, $user, [
                        'reference_type' => CreditNote::class,
                        'reference_id' => $creditNote->id,
                        'reference_code' => $creditNote->number,
                        'notes' => "Nota crédito {$creditNote->number}: {$itemData['description']}",
                        'occurred_at' => now(),
                    ]);
                }
            }

            $creditNote->update(['inventory_returned_at' => now()]);

            $this->reversePayments($sale, $creditNote, $totals['total'], $user);

            return $creditNote->refresh();
        });

        if (config('fe.enabled') && $sale->fe_cufe !== null) {
            EmitCreditNoteJob::dispatch($creditNote->id, $user->tenant_id)->afterCommit();
        }

        return $creditNote;
    }

    private function reversePayments(Sale $sale, CreditNote $creditNote, int $refundTotal, User $user): void
    {
        if ($sale->total <= 0 || $refundTotal <= 0) {
            $creditNote->update(['payments_reversed_at' => now()]);

            return;
        }

        $bankPayments = $sale->payments->filter(fn ($p) => $p->bank_account_id !== null)->values();

        if ($bankPayments->isEmpty()) {
            $creditNote->update(['payments_reversed_at' => now()]);

            return;
        }

        $totalBankPaid = $bankPayments->sum('amount');
        $bankRefundTotal = (int) round($refundTotal * ($totalBankPaid / $sale->total));

        if ($bankRefundTotal <= 0) {
            $creditNote->update(['payments_reversed_at' => now()]);

            return;
        }

        $remaining = $bankRefundTotal;
        $count = $bankPayments->count();

        foreach ($bankPayments as $index => $payment) {
            $isLast = $index === $count - 1;
            $proportion = $totalBankPaid > 0 ? $payment->amount / $totalBankPaid : 1;
            $reverseAmount = $isLast ? $remaining : (int) round($bankRefundTotal * $proportion);
            $remaining -= $reverseAmount;

            if ($reverseAmount <= 0) {
                continue;
            }

            BankAccountMovement::create([
                'tenant_id' => $user->tenant_id,
                'bank_account_id' => $payment->bank_account_id,
                'credit_note_id' => $creditNote->id,
                'user_id' => $user->id,
                'type' => 'outflow',
                'amount' => $reverseAmount,
                'status' => 'pending',
                'occurred_at' => now(),
                'description' => "Devolución nota crédito {$creditNote->number} — {$sale->document_number}",
            ]);
        }

        $creditNote->update(['payments_reversed_at' => now()]);
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return array{subtotal: int, tax_total: int, total: int}
     */
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
