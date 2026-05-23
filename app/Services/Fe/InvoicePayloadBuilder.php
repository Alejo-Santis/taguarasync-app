<?php

namespace App\Services\Fe;

use App\Models\Customer;
use App\Models\DianMunicipality;
use App\Models\FeResolution;
use App\Models\Sale;
use App\Models\Tenant;
use App\Models\TenantFeConfig;

class InvoicePayloadBuilder
{
    /** @return array<string, mixed> */
    public function build(Sale $sale, Tenant $tenant, FeResolution $resolution, int $invoiceNumber): array
    {
        $customer = $sale->customer;
        $feConfig = $tenant->feConfig;

        return [
            'date' => $sale->created_at->format('Y-m-d'),
            'time' => $sale->created_at->format('H:i:s'),
            'number' => $invoiceNumber,
            'prefix' => $resolution->prefix,
            'type_document_id' => config('fe.map.doc_types.invoice'),
            'resolution_number' => $resolution->resolution_number,
            'notes' => $sale->notes,
            'foot_note' => 'Generado por Taguara Sync | Sistema de gestión farmacéutica',
            'sendmail' => $customer?->email !== null,
            'sendmailtome' => true,
            'customer' => $this->buildCustomer($customer, $tenant, $feConfig),
            'invoice_lines' => $this->buildLines($sale),
            'tax_totals' => $this->buildTaxTotals($sale),
            'payment_form' => $this->buildPaymentForm($sale),
            'legal_monetary_totals' => $this->buildMonetaryTotals($sale),
            'allowance_charges' => [],
            'operation_mode' => [
                'company' => "{$tenant->legal_name} - NIT: {$tenant->nit}-{$tenant->verification_digit}",
                'software' => 'Taguara Sync',
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function buildCustomer(?Customer $customer, Tenant $tenant, ?TenantFeConfig $feConfig): array
    {
        $idTypeMap = config('fe.map.id_types');
        $orgTypeMap = config('fe.map.org_types');
        $regimeMap = config('fe.map.regime_types');
        $liabilityMap = config('fe.map.liabilities');

        // Derive municipality_id from DANE code via dian_municipalities.api_id
        // Falls back to manual override in tenant_fe_configs if set
        $tenantMunicipalityId = $feConfig?->municipality_api_id
            ?? DianMunicipality::where('code', $tenant->municipality_code)->value('api_id')
            ?? 0;

        if (! $customer) {
            return [
                'identification_number' => '222222222222',
                'dv' => null,
                'name' => 'Consumidor Final',
                'email' => null,
                'phone' => null,
                'address' => $tenant->address,
                'municipality_id' => $tenantMunicipalityId,
                'merchant_registration' => $tenant->merchant_registration ?? '0000000-00',
                'type_document_identification_id' => 3,
                'type_organization_id' => 2,
                'type_liability_id' => $liabilityMap['no_responsible'],
                'type_regime_id' => 2,
            ];
        }

        $customerMunicipalityId = DianMunicipality::where('code', $customer->municipality_code)->value('api_id')
            ?? $tenantMunicipalityId;

        $regimeCode = $customer->regime_type_code ?? '49';
        $liabilityId = $regimeCode === '48'
            ? $liabilityMap['responsible']
            : $liabilityMap['no_responsible'];

        return [
            'identification_number' => $customer->identification_number,
            'dv' => $customer->verification_digit,
            'name' => $customer->full_name,
            'email' => $customer->email,
            'phone' => $customer->phone,
            'address' => $customer->address,
            'municipality_id' => $customerMunicipalityId,
            'merchant_registration' => $customer->merchant_registration ?? '0000000-00',
            'type_document_identification_id' => $idTypeMap[$customer->identification_type_code] ?? 3,
            'type_organization_id' => $orgTypeMap[$customer->organization_type_code] ?? 2,
            'type_liability_id' => $liabilityId,
            'type_regime_id' => $regimeMap[$regimeCode] ?? 2,
        ];
    }

    /** @return list<array<string, mixed>> */
    private function buildLines(Sale $sale): array
    {
        return $sale->items->map(function ($item): array {
            $taxRate = (float) $item->tax_rate;
            $subtotal = $item->line_subtotal / 100;
            $taxAmount = $item->line_tax / 100;
            $unitPrice = $item->unit_price / 100;
            $qty = (string) $item->quantity;

            return [
                'code' => (string) $item->product_id,
                'description' => $item->description,
                'notes' => null,
                'unit_measure_id' => $item->dian_unit_measure_code
                    ? (int) $item->dian_unit_measure_code
                    : config('fe.map.unit_measure_default'),
                'invoiced_quantity' => $qty,
                'base_quantity' => $qty,
                'price_amount' => number_format($unitPrice, 2, '.', ''),
                'line_extension_amount' => number_format($subtotal, 2, '.', ''),
                'free_of_charge_indicator' => false,
                'type_item_identification_id' => config('fe.map.item_identification_default'),
                'tax_totals' => $taxRate > 0 ? [
                    [
                        'tax_id' => config('fe.map.iva_tax_id'),
                        'percent' => number_format($taxRate, 2, '.', ''),
                        'taxable_amount' => number_format($subtotal, 2, '.', ''),
                        'tax_amount' => number_format($taxAmount, 3, '.', ''),
                    ],
                ] : [],
            ];
        })->values()->all();
    }

    /** @return list<array<string, mixed>> */
    private function buildTaxTotals(Sale $sale): array
    {
        $byRate = $sale->items->groupBy(fn ($i) => number_format((float) $i->tax_rate, 2, '.', ''));

        $totals = [];
        foreach ($byRate as $rate => $items) {
            if ((float) $rate === 0.0) {
                continue;
            }
            $taxable = $items->sum('line_subtotal') / 100;
            $tax = $items->sum('line_tax') / 100;

            $totals[] = [
                'tax_id' => config('fe.map.iva_tax_id'),
                'percent' => $rate,
                'taxable_amount' => number_format($taxable, 2, '.', ''),
                'tax_amount' => number_format($tax, 2, '.', ''),
            ];
        }

        if ($sale->items->where('tax_rate', 0)->isNotEmpty()) {
            $taxable = $sale->items->where('tax_rate', 0)->sum('line_subtotal') / 100;
            $totals[] = [
                'tax_id' => config('fe.map.iva_tax_id'),
                'percent' => '0.00',
                'taxable_amount' => number_format($taxable, 2, '.', ''),
                'tax_amount' => '0.00',
            ];
        }

        return $totals;
    }

    /** @return array<string, mixed> */
    private function buildPaymentForm(Sale $sale): array
    {
        $paymentMethodMap = config('fe.map.payment_methods');
        $methodKey = $sale->payment_method->value;
        $apiMethodId = $paymentMethodMap[$methodKey] ?? $paymentMethodMap['other'];

        $paymentFormId = $sale->payment_form?->value === '2' ? 2 : 1;
        $dueDate = $sale->payment_due_date?->format('Y-m-d') ?? $sale->created_at->format('Y-m-d');

        return [
            'payment_form_id' => $paymentFormId,
            'payment_method_id' => $apiMethodId,
            'payment_due_date' => $dueDate,
            'duration_measure' => 0,
        ];
    }

    /** @return array<string, mixed> */
    private function buildMonetaryTotals(Sale $sale): array
    {
        $subtotal = $sale->subtotal / 100;
        $total = $sale->total / 100;

        return [
            'line_extension_amount' => number_format($subtotal, 2, '.', ''),
            'tax_exclusive_amount' => number_format($subtotal, 2, '.', ''),
            'tax_inclusive_amount' => number_format($total, 2, '.', ''),
            'allowance_total_amount' => '0.00',
            'charge_total_amount' => '0.000',
            'payable_amount' => number_format($total, 2, '.', ''),
        ];
    }
}
