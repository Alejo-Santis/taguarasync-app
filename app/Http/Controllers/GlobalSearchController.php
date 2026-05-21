<?php

namespace App\Http\Controllers;

use App\Enums\InventoryLotStatus;
use App\Enums\ProductStatus;
use App\Models\InventoryLot;
use App\Models\Product;
use App\Models\PurchaseReceipt;
use App\Models\Sale;
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

        return response()->json(['results' => $results]);
    }
}
