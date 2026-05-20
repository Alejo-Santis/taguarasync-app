<?php

namespace App\Http\Controllers\Pos;

use App\Actions\Pos\GetPosProducts;
use App\Actions\Pos\ProcessSale;
use App\Enums\CashSessionStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Pos\ProcessSaleRequest;
use App\Models\CashSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PosController extends Controller
{
    public function index(Request $request): Response|RedirectResponse
    {
        $session = CashSession::where('user_id', $request->user()->id)
            ->where('status', CashSessionStatus::Open)
            ->with('register')
            ->first();

        if (! $session) {
            return to_route('pos.session.open');
        }

        return Inertia::render('Pos/Index', [
            'activeSession' => [
                'uuid' => $session->uuid,
                'register_name' => $session->register->name,
                'register_code' => $session->register->code,
                'opened_at' => $session->opened_at->format('H:i'),
                'sales_count' => $session->sales()->count(),
                'sales_total' => $session->salesTotal(),
            ],
        ]);
    }

    public function search(Request $request, GetPosProducts $getPosProducts): JsonResponse
    {
        $query = $request->string('q')->trim()->toString();

        return response()->json($getPosProducts->execute($query));
    }

    public function store(ProcessSaleRequest $request, ProcessSale $processSale): RedirectResponse
    {
        $session = CashSession::where('user_id', $request->user()->id)
            ->where('status', CashSessionStatus::Open)
            ->first();

        if (! $session) {
            return to_route('pos.session.open');
        }

        try {
            $sale = $processSale->execute($request->validated(), $request->user(), $session->id);
        } catch (\InvalidArgumentException $e) {
            return back()->withErrors(['cart' => $e->getMessage()]);
        }

        return to_route('pos.index')->with('completedSale', [
            'uuid' => $sale->uuid,
            'document_number' => $sale->document_number,
            'total' => $sale->total,
            'payment_method' => $sale->payment_method->label(),
            'change_amount' => $sale->change_amount,
            'items_count' => $sale->items()->count(),
        ]);
    }
}
