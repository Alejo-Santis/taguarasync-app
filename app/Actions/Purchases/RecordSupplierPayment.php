<?php

namespace App\Actions\Purchases;

use App\Models\BankAccount;
use App\Models\BankAccountMovement;
use App\Models\Supplier;
use App\Models\SupplierPayment;
use App\Models\User;

class RecordSupplierPayment
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(Supplier $supplier, array $data, ?User $user = null): SupplierPayment
    {
        $payment = SupplierPayment::create([
            'tenant_id' => $supplier->tenant_id,
            'supplier_id' => $supplier->id,
            'bank_account_id' => $data['bank_account_id'] ?? null,
            'user_id' => $user?->id,
            'payment_date' => $data['payment_date'],
            'amount' => $data['amount'],
            'reference' => $data['reference'] ?? null,
            'notes' => $data['notes'] ?? null,
        ]);

        if ($payment->bank_account_id) {
            $bankAccount = BankAccount::find($payment->bank_account_id);

            BankAccountMovement::create([
                'tenant_id' => $supplier->tenant_id,
                'bank_account_id' => $payment->bank_account_id,
                'supplier_payment_id' => $payment->id,
                'user_id' => $user?->id,
                'type' => 'outflow',
                'amount' => $payment->amount,
                'reference' => $payment->reference,
                'status' => 'confirmed',
                'occurred_at' => $payment->payment_date->startOfDay(),
                'description' => "Pago a proveedor {$supplier->name}",
            ]);
        }

        return $payment;
    }
}
