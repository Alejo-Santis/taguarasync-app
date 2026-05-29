<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class BranchController extends Controller
{
    public function index(Request $request): Response
    {
        $branches = Branch::query()
            ->withCount('registers')
            ->orderByDesc('is_main')
            ->orderBy('name')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (Branch $branch) => [
                'id' => $branch->id,
                'name' => $branch->name,
                'address' => $branch->address,
                'phone' => $branch->phone,
                'is_main' => $branch->is_main,
                'is_active' => $branch->is_active,
                'registers_count' => $branch->registers_count,
            ]);

        return Inertia::render('Settings/Branches/Index', [
            'branches' => $branches,
            'filters' => $request->only(['q']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $tenantId = $request->user()->tenant_id;

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120', Rule::unique('branches')->where('tenant_id', $tenantId)],
            'address' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:40'],
            'is_main' => ['boolean'],
        ]);

        if ($validated['is_main'] ?? false) {
            Branch::where('tenant_id', $tenantId)->update(['is_main' => false]);
        }

        Branch::create([...$validated, 'tenant_id' => $tenantId, 'is_active' => true]);

        return back()->with('success', "Sucursal '{$validated['name']}' creada correctamente.");
    }

    public function update(Branch $branch, Request $request): RedirectResponse
    {
        $tenantId = $request->user()->tenant_id;
        abort_unless($branch->tenant_id === $tenantId, 403);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120', Rule::unique('branches')->where('tenant_id', $tenantId)->ignore($branch->id)],
            'address' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:40'],
            'is_main' => ['boolean'],
        ]);

        if ($validated['is_main'] ?? false) {
            Branch::where('tenant_id', $tenantId)->where('id', '!=', $branch->id)->update(['is_main' => false]);
        }

        $branch->update($validated);

        return back()->with('success', "Sucursal '{$branch->name}' actualizada.");
    }

    public function toggle(Branch $branch, Request $request): RedirectResponse
    {
        $tenantId = $request->user()->tenant_id;
        abort_unless($branch->tenant_id === $tenantId, 403);

        if ($branch->is_active) {
            $activeCount = Branch::where('tenant_id', $tenantId)->where('is_active', true)->count();
            abort_if($activeCount <= 1, 422, 'No se puede desactivar la única sucursal activa.');
        }

        $branch->update(['is_active' => ! $branch->is_active]);

        return back();
    }
}
