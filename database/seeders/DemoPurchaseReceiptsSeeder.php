<?php

namespace Database\Seeders;

use App\Actions\Purchases\ReceivePurchaseReceipt;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;

class DemoPurchaseReceiptsSeeder extends Seeder
{
    public function run(): void
    {
        $tenant = Tenant::first();

        if (! $tenant) {
            return;
        }

        $user = User::where('tenant_id', $tenant->id)->first();
        $supplier = Supplier::where('tenant_id', $tenant->id)->first();

        if (! $supplier) {
            return;
        }

        // Load products for this tenant
        $products = Product::where('tenant_id', $tenant->id)
            ->with('presentations')
            ->get()
            ->keyBy('internal_code');

        if ($products->isEmpty()) {
            return;
        }

        $action = app(ReceivePurchaseReceipt::class);

        // Purchase receipt 1 - analgesicos and antibiotics
        $this->createReceipt($action, $tenant->id, $user, $supplier, [
            'document_number' => 'REM-001-2026',
            'document_date' => '2026-05-01',
            'received_at' => '2026-05-01 09:00:00',
            'notes' => 'Pedido mensual analgesicos y antibioticos',
            'items' => [
                $this->item($products->get('DOL-500'), 'LT-DOL-0126', '2028-06-30', 500, 180),
                $this->item($products->get('ACET-MK-500'), 'LT-ACET-0226', '2028-04-30', 600, 120),
                $this->item($products->get('IBU-400'), 'LT-IBU-0126', '2028-03-31', 400, 150),
                $this->item($products->get('AMOX-500'), 'LT-AMOX-0126', '2027-12-31', 200, 400),
                $this->item($products->get('DICLO-50'), 'LT-DICLO-0126', '2028-01-31', 300, 180),
            ],
        ]);

        // Purchase receipt 2 - antihistaminicos, digestivos, vitaminas
        $this->createReceipt($action, $tenant->id, $user, $supplier, [
            'document_number' => 'FAC-5521-2026',
            'document_date' => '2026-05-05',
            'received_at' => '2026-05-05 14:30:00',
            'notes' => 'Factura Copidrogas mayo',
            'items' => [
                $this->item($products->get('LORA-10'), 'LT-LORA-0526', '2028-08-31', 350, 200),
                $this->item($products->get('OME-20'), 'LT-OME-0526', '2028-07-31', 250, 300),
                $this->item($products->get('VITC-1000'), 'LT-VITC-0526', '2027-09-30', 150, 350),
                $this->item($products->get('BUS-10'), 'LT-BUS-0526', '2027-11-30', 100, 500),
                $this->item($products->get('ASP-500'), 'LT-ASP-0526', '2028-02-28', 200, 250),
            ],
        ]);
    }

    /**
     * @param  array{document_number: string, document_date: string, received_at: string, notes: string, items: list<array<string,mixed>>}  $data
     */
    private function createReceipt(
        ReceivePurchaseReceipt $action,
        int $tenantId,
        ?User $user,
        Supplier $supplier,
        array $data,
    ): void {
        $items = array_filter($data['items']); // remove nulls (product not found)

        if (empty($items)) {
            return;
        }

        $action->execute([
            'tenant_id' => $tenantId,
            'supplier_id' => $supplier->id,
            'document_number' => $data['document_number'],
            'document_date' => $data['document_date'],
            'received_at' => $data['received_at'],
            'notes' => $data['notes'],
            'items' => array_values($items),
        ], $user);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function item(?Product $product, string $lotNumber, string $expiresOn, int $quantity, int $unitCost): ?array
    {
        if (! $product) {
            return null;
        }

        $defaultPresentation = $product->presentations->firstWhere('is_default', true)
            ?? $product->presentations->first();

        return [
            'product_id' => $product->id,
            'product_presentation_id' => $defaultPresentation?->id,
            'description' => $product->commercial_name,
            'lot_number' => $lotNumber,
            'expires_on' => $expiresOn,
            'quantity' => $quantity,
            'unit_cost' => $unitCost,
            'tax_rate' => $product->tax_rate,
        ];
    }
}
