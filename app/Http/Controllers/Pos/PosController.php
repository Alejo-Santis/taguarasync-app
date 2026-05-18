<?php

namespace App\Http\Controllers\Pos;

use App\Actions\Pos\GetPosProducts;
use App\Actions\Pos\ProcessSale;
use App\Http\Controllers\Controller;
use App\Http\Requests\Pos\ProcessSaleRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PosController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Pos/Index');
    }

    public function search(Request $request, GetPosProducts $getPosProducts): JsonResponse
    {
        $query = $request->string('q')->trim()->toString();

        return response()->json($getPosProducts->execute($query));
    }

    public function store(ProcessSaleRequest $request, ProcessSale $processSale): RedirectResponse
    {
        try {
            $sale = $processSale->execute($request->validated(), $request->user());
        } catch (\InvalidArgumentException $e) {
            return back()->withErrors(['cart' => $e->getMessage()]);
        }

        return to_route('pos.index')->with('completedSale', [
            'document_number' => $sale->document_number,
            'total' => $sale->total,
            'payment_method' => $sale->payment_method->label(),
            'change_amount' => $sale->change_amount,
            'items_count' => $sale->items()->count(),
        ]);
    }
}
