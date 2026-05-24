<?php

namespace App\Actions\Pos;

use App\Actions\Inventory\RegisterInventoryMovement;
use App\Enums\FeStatus;
use App\Enums\InventoryLotStatus;
use App\Enums\SaleStatus;
use App\Models\BankAccountMovement;
use App\Models\InventoryLot;
use App\Models\PaymentMethod as PaymentMethodConfig;
use App\Models\ProductPresentation;
use App\Models\Sale;
use App\Models\SalePayment;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class ProcessSale
{
    /**
     * @param  array{
     *     customer_id?: int|null,
     *     payment_method: string,
     *     payment_form?: string|null,
     *     amount_tendered?: int|null,
     *     notes?: string|null,
     *     payments?: array<int, array{
     *         payment_method_id: int,
     *         bank_account_id?: int|null,
     *         amount: int,
     *         amount_tendered?: int|null,
     *         reference?: string|null,
     *         attachment?: mixed,
     *         notes?: string|null
     *     }>,
     *     items: array<int, array{
     *         product_id: int,
     *         product_presentation_id: int,
     *         description: string,
     *         quantity: int,
     *         unit_price: int,
     *         tax_rate: int|float|string
     *     }>
     * }  $data
     */
    public function execute(array $data, User $user, ?int $cashSessionId = null): Sale
    {
        $sale = DB::transaction(function () use ($data, $user, $cashSessionId): Sale {
            $totals = $this->calculateTotals($data['items']);

            $amountTendered = isset($data['amount_tendered']) ? (int) $data['amount_tendered'] : null;
            $change = ($amountTendered !== null) ? max(0, $amountTendered - $totals['total']) : null;
            $payments = $this->normalizePayments($data, $user, $totals['total'], $amountTendered, $change);

            $feEnabled = config('fe.enabled') && $user->tenant?->feConfig?->electronic_invoicing_enabled;

            $sale = Sale::create([
                'uuid' => (string) Str::uuid(),
                'tenant_id' => $user->tenant_id,
                'user_id' => $user->id,
                'customer_id' => $data['customer_id'] ?? null,
                'cash_session_id' => $cashSessionId,
                'document_number' => 'VTA-TEMP',
                'subtotal' => $totals['subtotal'],
                'tax_total' => $totals['tax_total'],
                'total' => $totals['total'],
                'payment_method' => $data['payment_method'],
                'payment_form' => $data['payment_form'] ?? '1',
                'amount_tendered' => $amountTendered,
                'change_amount' => $change,
                'status' => SaleStatus::Completed,
                'fe_status' => $feEnabled ? FeStatus::Pending : null,
                'notes' => $data['notes'] ?? null,
            ]);

            $sale->update([
                'document_number' => 'VTA-'.str_pad((string) $sale->id, 8, '0', STR_PAD_LEFT),
            ]);

            foreach ($data['items'] as $itemData) {
                $presentation = ProductPresentation::findOrFail($itemData['product_presentation_id']);
                $minUnitsNeeded = $itemData['quantity'] * $presentation->minimum_unit_quantity;

                $lot = $this->findFEFOLot($itemData['product_id'], $minUnitsNeeded);

                $line = $this->lineCalculation($itemData);

                $saleItem = $sale->items()->create([
                    'tenant_id' => $user->tenant_id,
                    'product_id' => $itemData['product_id'],
                    'product_presentation_id' => $itemData['product_presentation_id'],
                    'inventory_lot_id' => $lot->id,
                    'description' => $itemData['description'],
                    'quantity' => $itemData['quantity'],
                    'unit_price' => $itemData['unit_price'],
                    'tax_rate' => (float) $itemData['tax_rate'],
                    'line_subtotal' => $line['subtotal'],
                    'line_tax' => $line['tax'],
                    'line_total' => $line['total'],
                ]);

                $movement = app(RegisterInventoryMovement::class)->sale($lot, $minUnitsNeeded, $user, [
                    'reference_type' => Sale::class,
                    'reference_id' => $sale->id,
                    'reference_code' => $sale->document_number,
                    'notes' => "Venta {$sale->document_number}: {$itemData['description']}",
                    'occurred_at' => now(),
                ]);

                $saleItem->forceFill(['inventory_movement_id' => $movement->id])->save();
            }

            foreach ($payments as $paymentData) {
                $payment = $sale->payments()->create([
                    'tenant_id' => $user->tenant_id,
                    'cash_session_id' => $cashSessionId,
                    'payment_method_id' => $paymentData['payment_method_id'],
                    'bank_account_id' => $paymentData['bank_account_id'] ?? null,
                    'user_id' => $user->id,
                    'amount' => $paymentData['amount'],
                    'amount_tendered' => $paymentData['amount_tendered'] ?? null,
                    'change_amount' => $paymentData['change_amount'] ?? null,
                    'reference' => $paymentData['reference'] ?? null,
                    'attachment_path' => $paymentData['attachment_path'] ?? null,
                    'status' => 'confirmed',
                    'paid_at' => now(),
                    'notes' => $paymentData['notes'] ?? null,
                ]);

                $this->registerBankMovement($payment, $user);
            }

            return $sale->refresh();
        });

        if (config('fe.enabled') && $sale->fe_status === FeStatus::Pending) {
            EmitElectronicInvoiceJob::dispatch($sale->id, $user->tenant_id)
                ->afterCommit();
        }

        return $sale;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<int, array<string, mixed>>
     */
    private function normalizePayments(array $data, User $user, int $total, ?int $amountTendered, ?int $change): array
    {
        if (! empty($data['payments'])) {
            return collect($data['payments'])->map(fn (array $payment): array => [
                'payment_method_id' => (int) $payment['payment_method_id'],
                'bank_account_id' => isset($payment['bank_account_id']) ? (int) $payment['bank_account_id'] : null,
                'amount' => (int) $payment['amount'],
                'amount_tendered' => isset($payment['amount_tendered']) ? (int) $payment['amount_tendered'] : null,
                'change_amount' => null,
                'reference' => $payment['reference'] ?? null,
                'attachment_path' => $this->storeAttachment($payment['attachment'] ?? null, $user),
                'notes' => $payment['notes'] ?? null,
            ])->all();
        }

        $method = $this->paymentMethodForLegacyValue((string) $data['payment_method'], $user);

        return [[
            'payment_method_id' => $method->id,
            'bank_account_id' => null,
            'amount' => $total,
            'amount_tendered' => $amountTendered,
            'change_amount' => $change,
            'reference' => null,
            'attachment_path' => null,
            'notes' => null,
        ]];
    }

    private function storeAttachment(mixed $attachment, User $user): ?string
    {
        if (! $attachment instanceof UploadedFile) {
            return null;
        }

        return $attachment->store("tenants/{$user->tenant_id}/payment-receipts");
    }

    private function paymentMethodForLegacyValue(string $legacyValue, User $user): PaymentMethodConfig
    {
        $code = match ($legacyValue) {
            'card' => 'card_credit',
            default => $legacyValue,
        };

        $defaults = [
            'cash' => [
                'name' => 'Efectivo',
                'type' => 'cash',
                'dian_payment_method_code' => '10',
                'affects_cash' => true,
                'sort_order' => 10,
            ],
            'card_credit' => [
                'name' => 'Tarjeta credito',
                'type' => 'card',
                'dian_payment_method_code' => '48',
                'requires_reference' => true,
                'sort_order' => 20,
            ],
            'transfer' => [
                'name' => 'Transferencia bancaria',
                'type' => 'transfer',
                'dian_payment_method_code' => '47',
                'requires_reference' => true,
                'requires_bank_account' => true,
                'allows_attachment' => true,
                'sort_order' => 40,
            ],
        ];

        $definition = $defaults[$code] ?? $defaults['cash'];

        return PaymentMethodConfig::query()->firstOrCreate(
            ['tenant_id' => $user->tenant_id, 'code' => $code],
            [
                'tenant_id' => $user->tenant_id,
                'payment_form' => '1',
                'requires_reference' => false,
                'requires_bank_account' => false,
                'allows_attachment' => false,
                'affects_cash' => false,
                'is_active' => true,
                ...$definition,
            ],
        );
    }

    private function registerBankMovement(SalePayment $payment, User $user): void
    {
        if (! $payment->bank_account_id) {
            return;
        }

        BankAccountMovement::create([
            'tenant_id' => $user->tenant_id,
            'bank_account_id' => $payment->bank_account_id,
            'sale_payment_id' => $payment->id,
            'user_id' => $user->id,
            'type' => 'inflow',
            'amount' => $payment->amount,
            'reference' => $payment->reference,
            'status' => 'pending',
            'occurred_at' => $payment->paid_at ?? now(),
            'description' => "Pago de venta {$payment->sale->document_number}",
        ]);
    }

    private function findFEFOLot(int $productId, int $minUnitsNeeded): InventoryLot
    {
        $lot = InventoryLot::query()
            ->where('product_id', $productId)
            ->where('status', InventoryLotStatus::Available)
            ->where('current_quantity', '>=', $minUnitsNeeded)
            ->orderByRaw('expires_on IS NULL, expires_on ASC')
            ->first();

        if (! $lot) {
            throw new InvalidArgumentException("Stock insuficiente. Se necesitan {$minUnitsNeeded} unidades minimas.");
        }

        return $lot;
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array{subtotal: int, tax: int, total: int}
     */
    private function lineCalculation(array $item): array
    {
        $subtotal = $item['quantity'] * $item['unit_price'];
        $tax = (int) round($subtotal * ((float) $item['tax_rate'] / 100));

        return ['subtotal' => $subtotal, 'tax' => $tax, 'total' => $subtotal + $tax];
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return array{subtotal: int, tax_total: int, total: int}
     */
    private function calculateTotals(array $items): array
    {
        return collect($items)->reduce(function (array $carry, array $item): array {
            $line = $this->lineCalculation($item);

            return [
                'subtotal' => $carry['subtotal'] + $line['subtotal'],
                'tax_total' => $carry['tax_total'] + $line['tax'],
                'total' => $carry['total'] + $line['total'],
            ];
        }, ['subtotal' => 0, 'tax_total' => 0, 'total' => 0]);
    }
}
