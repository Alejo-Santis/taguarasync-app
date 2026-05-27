<?php

namespace App\Http\Requests\Pos;

use App\Enums\PaymentMethod;
use App\Models\PaymentMethod as PaymentMethodConfig;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;

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
            'customer_id' => [
                'nullable', 'integer',
                Rule::exists('customers', 'id')->where('tenant_id', $tenantId),
                Rule::requiredIf($this->input('payment_form') === '2'),
            ],
            'payment_method' => [
                Rule::requiredIf($this->input('payment_form') !== '2'),
                'nullable',
                Rule::enum(PaymentMethod::class),
            ],
            'payment_form' => ['nullable', 'string', Rule::in(['1', '2'])],
            'payment_due_date' => [
                'nullable', 'date',
                Rule::requiredIf($this->input('payment_form') === '2'),
            ],
            'amount_tendered' => [
                'nullable', 'integer', 'min:0',
                Rule::requiredIf($this->input('payment_method') === PaymentMethod::Cash->value && $this->input('payment_form') !== '2'),
            ],
            'notes' => ['nullable', 'string', 'max:500'],
            'payments' => ['nullable', 'array', 'min:1', 'max:5'],
            'payments.*.payment_method_id' => ['required_with:payments', 'integer', Rule::exists('payment_methods', 'id')->where('tenant_id', $tenantId)->where('is_active', true)],
            'payments.*.bank_account_id' => ['nullable', 'integer', Rule::exists('bank_accounts', 'id')->where('tenant_id', $tenantId)->where('is_active', true)],
            'payments.*.amount' => ['required_with:payments', 'integer', 'min:1'],
            'payments.*.amount_tendered' => ['nullable', 'integer', 'min:0'],
            'payments.*.reference' => ['nullable', 'string', 'max:120'],
            'payments.*.attachment' => ['nullable', File::types(['jpg', 'jpeg', 'png', 'webp', 'pdf'])->max(4 * 1024)],
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
            'items.*.discount_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'items.*.tax_rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'items.*.prescription_number' => ['nullable', 'string', 'max:60'],
            'items.*.patient_id_number' => ['nullable', 'string', 'max:30'],
            'items.*.patient_name' => ['nullable', 'string', 'max:120'],
        ];
    }

    public function after(): array
    {
        return [
            function ($validator): void {
                // Credit sales (payment_form=2) don't require payment validation at POS time
                if ($this->input('payment_form') === '2') {
                    return;
                }

                $total = collect($this->input('items', []))->reduce(function (int $carry, array $item): int {
                    $gross = ($item['quantity'] ?? 0) * ($item['unit_price'] ?? 0);
                    $discountRate = (float) ($item['discount_rate'] ?? 0);
                    $discount = (int) round($gross * ($discountRate / 100));
                    $subtotal = $gross - $discount;
                    $tax = (int) round($subtotal * (((float) ($item['tax_rate'] ?? 0)) / 100));

                    return $carry + $subtotal + $tax;
                }, 0);

                if ($this->filled('payments')) {
                    $payments = collect($this->input('payments', []));
                    $paid = $payments->sum(fn (array $payment): int => (int) ($payment['amount'] ?? 0));

                    if ($paid < $total) {
                        $validator->errors()->add('payments', 'Los pagos registrados no cubren el total de la venta.');
                    }

                    if ($paid > $total) {
                        $validator->errors()->add('payments', 'Los pagos registrados superan el total de la venta.');
                    }

                    $methods = PaymentMethodConfig::query()
                        ->where('tenant_id', $this->user()?->tenant_id)
                        ->whereIn('id', $payments->pluck('payment_method_id')->filter()->all())
                        ->get()
                        ->keyBy('id');

                    $payments->each(function (array $payment, int $index) use ($methods, $validator): void {
                        $method = $methods->get((int) ($payment['payment_method_id'] ?? 0));

                        if (! $method) {
                            return;
                        }

                        if ($method->requires_reference && blank($payment['reference'] ?? null)) {
                            $validator->errors()->add("payments.{$index}.reference", "El medio de pago {$method->name} requiere referencia.");
                        }

                        if ($method->requires_bank_account && blank($payment['bank_account_id'] ?? null)) {
                            $validator->errors()->add("payments.{$index}.bank_account_id", "El medio de pago {$method->name} requiere cuenta destino.");
                        }

                        if ($method->affects_cash) {
                            $amount = (int) ($payment['amount'] ?? 0);
                            $tendered = (int) ($payment['amount_tendered'] ?? 0);

                            if ($tendered < $amount) {
                                $validator->errors()->add("payments.{$index}.amount_tendered", 'El monto recibido en efectivo no cubre el pago.');
                            }
                        }
                    });

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
