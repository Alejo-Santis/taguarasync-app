<?php

namespace App\Actions\Payments;

use App\Models\PaymentMethod;
use App\Models\Tenant;
use Illuminate\Support\Collection;

class EnsureDefaultPaymentMethods
{
    /**
     * @return Collection<int, PaymentMethod>
     */
    public function execute(Tenant|int $tenant): Collection
    {
        $tenantId = $tenant instanceof Tenant ? $tenant->id : $tenant;

        foreach ($this->defaults() as $method) {
            PaymentMethod::query()->updateOrCreate(
                ['tenant_id' => $tenantId, 'code' => $method['code']],
                ['tenant_id' => $tenantId, ...$method, 'is_active' => true],
            );
        }

        return PaymentMethod::query()
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function defaults(): array
    {
        return [
            [
                'name' => 'Efectivo',
                'code' => 'cash',
                'type' => 'cash',
                'dian_payment_method_code' => '10',
                'payment_form' => '1',
                'requires_reference' => false,
                'requires_bank_account' => false,
                'allows_attachment' => false,
                'affects_cash' => true,
                'sort_order' => 10,
            ],
            [
                'name' => 'Tarjeta credito',
                'code' => 'card_credit',
                'type' => 'card',
                'dian_payment_method_code' => '48',
                'payment_form' => '1',
                'requires_reference' => true,
                'requires_bank_account' => false,
                'allows_attachment' => false,
                'affects_cash' => false,
                'sort_order' => 20,
            ],
            [
                'name' => 'Tarjeta debito',
                'code' => 'card_debit',
                'type' => 'card',
                'dian_payment_method_code' => '49',
                'payment_form' => '1',
                'requires_reference' => true,
                'requires_bank_account' => false,
                'allows_attachment' => false,
                'affects_cash' => false,
                'sort_order' => 30,
            ],
            [
                'name' => 'Transferencia bancaria',
                'code' => 'transfer',
                'type' => 'transfer',
                'dian_payment_method_code' => '47',
                'payment_form' => '1',
                'requires_reference' => true,
                'requires_bank_account' => true,
                'allows_attachment' => true,
                'affects_cash' => false,
                'sort_order' => 40,
            ],
            [
                'name' => 'Billetera digital',
                'code' => 'wallet',
                'type' => 'wallet',
                'dian_payment_method_code' => '47',
                'payment_form' => '1',
                'requires_reference' => true,
                'requires_bank_account' => true,
                'allows_attachment' => true,
                'affects_cash' => false,
                'sort_order' => 50,
            ],
        ];
    }
}
