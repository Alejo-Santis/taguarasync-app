<?php

namespace App\Http\Controllers;

use App\Enums\InventoryLotStatus;
use App\Enums\ProductStatus;
use App\Models\Customer;
use App\Models\InventoryLot;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseReceipt;
use App\Models\Sale;
use App\Models\Supplier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GlobalSearchController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $query = $request->string('q')->trim()->toString();

        if (mb_strlen($query) < 2) {
            return response()->json(['results' => []]);
        }

        $operator = (new Product)->getConnection()->getDriverName() === 'pgsql' ? 'ilike' : 'like';
        $like = "%{$query}%";

        $products = Product::where('status', ProductStatus::Active)
            ->where(fn ($q) => $q
                ->where('commercial_name', $operator, $like)
                ->orWhere('internal_code', $operator, $like)
                ->orWhere('barcode', $operator, $like)
            )
            ->limit(5)
            ->get(['id', 'uuid', 'commercial_name', 'internal_code', 'sale_price']);

        $lots = InventoryLot::where('status', InventoryLotStatus::Available)
            ->where(fn ($q) => $q->where('lot_number', $operator, $like))
            ->with('product:id,commercial_name')
            ->limit(3)
            ->get(['id', 'uuid', 'product_id', 'lot_number', 'current_quantity']);

        $sales = Sale::where('document_number', $operator, $like)
            ->limit(3)
            ->get(['uuid', 'document_number', 'total', 'created_at']);

        $receipts = PurchaseReceipt::where('document_number', $operator, $like)
            ->limit(3)
            ->get(['uuid', 'document_number', 'total']);

        $customers = Customer::where(fn ($q) => $q
            ->where('first_name', $operator, $like)
            ->orWhere('last_name', $operator, $like)
            ->orWhere('business_name', $operator, $like)
            ->orWhere('identification_number', $operator, $like)
        )
            ->limit(4)
            ->get(['uuid', 'first_name', 'last_name', 'business_name', 'identification_number']);

        $suppliers = Supplier::where(fn ($q) => $q
            ->where('name', $operator, $like)
            ->orWhere('nit', $operator, $like)
        )
            ->limit(3)
            ->get(['id', 'name', 'nit']);

        $orders = PurchaseOrder::where('order_number', $operator, $like)
            ->with('supplier:id,name')
            ->limit(3)
            ->get(['id', 'uuid', 'supplier_id', 'order_number', 'status']);

        $results = [];

        foreach ($products as $p) {
            $results[] = [
                'type' => 'product',
                'label' => $p->commercial_name,
                'sub' => $p->internal_code,
                'href' => "/products/{$p->uuid}/edit",
            ];
        }

        foreach ($lots as $l) {
            $results[] = [
                'type' => 'lot',
                'label' => "Lote {$l->lot_number}",
                'sub' => "{$l->product?->commercial_name} · {$l->current_quantity} unidades",
                'href' => '/inventory',
            ];
        }

        foreach ($sales as $s) {
            $results[] = [
                'type' => 'sale',
                'label' => $s->document_number,
                'sub' => 'Venta · $'.number_format($s->total, 0, ',', '.'),
                'href' => '/sales',
            ];
        }

        foreach ($receipts as $r) {
            $results[] = [
                'type' => 'receipt',
                'label' => $r->document_number,
                'sub' => 'Compra · $'.number_format($r->total, 0, ',', '.'),
                'href' => '/purchases',
            ];
        }

        foreach ($customers as $c) {
            $results[] = [
                'type' => 'customer',
                'label' => $c->full_name,
                'sub' => $c->identification_number ? "Cliente · {$c->identification_number}" : 'Cliente',
                'href' => '/customers',
            ];
        }

        foreach ($suppliers as $s) {
            $results[] = [
                'type' => 'supplier',
                'label' => $s->name,
                'sub' => $s->nit ? "Proveedor · NIT {$s->nit}" : 'Proveedor',
                'href' => '/settings/suppliers',
            ];
        }

        foreach ($orders as $o) {
            $results[] = [
                'type' => 'order',
                'label' => $o->order_number,
                'sub' => 'Orden de compra · '.($o->supplier?->name ?? ''),
                'href' => "/purchases/orders/{$o->id}",
            ];
        }

        return response()->json(['results' => $results]);
    }
}
