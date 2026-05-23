<?php

namespace App\Http\Requests\Pos;

use App\Enums\PaymentMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProcessSaleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $tenantId = $this->user()?->tenant_id;

        return [
            'customer_id' => ['nullable', 'integer', Rule::exists('customers', 'id')->where('tenant_id', $tenantId)],
            'payment_method' => ['required', Rule::enum(PaymentMethod::class)],
            'payment_form' => ['nullable', 'string', Rule::in(['1', '2'])],
            'amount_tendered' => [
                'nullable', 'integer', 'min:0',
                Rule::requiredIf($this->input('payment_method') === PaymentMethod::Cash->value),
            ],
            'notes' => ['nullable', 'string', 'max:500'],
            'payments' => ['nullable', 'array', 'min:1', 'max:5'],
            'payments.*.payment_method_id' => ['required_with:payments', 'integer', Rule::exists('payment_methods', 'id')->where('tenant_id', $tenantId)],
            'payments.*.bank_account_id' => ['nullable', 'integer', Rule::exists('bank_accounts', 'id')->where('tenant_id', $tenantId)],
            'payments.*.amount' => ['required_with:payments', 'integer', 'min:1'],
            'payments.*.amount_tendered' => ['nullable', 'integer', 'min:0'],
            'payments.*.reference' => ['nullable', 'string', 'max:120'],
            'payments.*.notes' => ['nullable', 'string', 'max:500'],
            'items' => ['required', 'array', 'min:1', 'max:50'],
            'items.*.product_id' => [
                'required', 'integer',
                Rule::exists('products', 'id')->where('tenant_id', $tenantId),
            ],
            'items.*.product_presentation_id' => ['required', 'integer', Rule::exists('product_presentations', 'id')],
            'items.*.description' => ['required', 'string', 'max:260'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:9999'],
            'items.*.unit_price' => ['required', 'integer', 'min:0'],
            'items.*.tax_rate' => ['required', 'numeric', 'min:0', 'max:100'],
        ];
    }

    public function after(): array
    {
        return [
            function ($validator): void {
                $total = collect($this->input('items', []))->reduce(function (int $carry, array $item): int {
                    $subtotal = ($item['quantity'] ?? 0) * ($item['unit_price'] ?? 0);
                    $tax = (int) round($subtotal * (((float) ($item['tax_rate'] ?? 0)) / 100));

                    return $carry + $subtotal + $tax;
                }, 0);

                if ($this->filled('payments')) {
                    $paid = collect($this->input('payments', []))->sum(fn (array $payment): int => (int) ($payment['amount'] ?? 0));

                    if ($paid < $total) {
                        $validator->errors()->add('payments', 'Los pagos registrados no cubren el total de la venta.');
                    }

                    return;
                }

                if ($this->input('payment_method') === PaymentMethod::Cash->value) {
                    $tendered = (int) $this->input('amount_tendered', 0);

                    if ($tendered < $total) {
                        $validator->errors()->add('amount_tendered', 'El monto recibido es menor al total de la venta.');
                    }
                }
            },
        ];
    }
}
