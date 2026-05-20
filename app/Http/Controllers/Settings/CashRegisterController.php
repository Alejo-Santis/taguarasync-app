<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\StoreCashRegisterRequest;
use App\Http\Requests\Settings\UpdateCashRegisterRequest;
use App\Models\CashRegister;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CashRegisterController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = [
            'q' => $request->string('q')->trim()->toString(),
            'status' => $request->string('status')->toString(),
        ];

        $items = CashRegister::withCount('sessions')
            ->when($filters['q'] !== '', fn ($q) => $q->where('name', 'like', "%{$filters['q']}%")->orWhere('code', 'like', "%{$filters['q']}%"))
            ->when($filters['status'] === 'active', fn ($q) => $q->where('is_active', true))
            ->when($filters['status'] === 'inactive', fn ($q) => $q->where('is_active', false))
            ->orderBy('name')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (CashRegister $register) => [
                'id' => $register->id,
                'name' => $register->name,
                'code' => $register->code,
                'is_active' => $register->is_active,
                'sessions_count' => $register->sessions_count,
                'has_open_session' => $register->hasOpenSession(),
            ]);

        return Inertia::render('Settings/Registers/Index', [
            'items' => $items,
            'filters' => $filters,
            'stats' => [
                'total' => CashRegister::count(),
                'active' => CashRegister::where('is_active', true)->count(),
            ],
        ]);
    }

    public function store(StoreCashRegisterRequest $request): RedirectResponse
    {
        CashRegister::create($request->validated());

        return back()->with('success', 'Caja creada correctamente.');
    }

    public function update(CashRegister $register, UpdateCashRegisterRequest $request): RedirectResponse
    {
        $register->update($request->validated());

        return back()->with('success', 'Caja actualizada correctamente.');
    }

    public function toggle(CashRegister $register): RedirectResponse
    {
        $register->update(['is_active' => ! $register->is_active]);

        return back();
    }
}
