<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Fortify\CreateNewUser;
use App\Enums\TenantStatus;
use App\Http\Controllers\Controller;
use App\Models\Tenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

class TenantAdminController extends Controller
{
    public function index(): Response
    {
        $tenants = Tenant::query()
            ->withCount('users')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (Tenant $t) => [
                'uuid' => $t->uuid,
                'name' => $t->name,
                'email' => $t->email,
                'nit' => $t->nit,
                'status' => ['value' => $t->status->value, 'label' => $t->status->label()],
                'users_count' => $t->users_count,
                'created_at' => $t->created_at->format('d/m/Y'),
                'trial_ends_at' => $t->trial_ends_at?->format('d/m/Y'),
            ]);

        return Inertia::render('Admin/Tenants/Index', [
            'tenants' => $tenants,
        ]);
    }

    public function store(Request $request, CreateNewUser $createNewUser): RedirectResponse
    {
        $validated = $request->validate([
            'tenant_name' => ['required', 'string', 'max:180'],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'password' => ['required', 'string', Password::defaults(), 'confirmed'],
        ]);

        $createNewUser->create(array_merge($validated, [
            'password_confirmation' => $request->input('password_confirmation'),
        ]));

        return back()->with('success', "Farmacia \"{$validated['tenant_name']}\" creada correctamente.");
    }

    public function toggleStatus(Tenant $tenant): RedirectResponse
    {
        $newStatus = $tenant->status === TenantStatus::Active
            ? TenantStatus::Suspended
            : TenantStatus::Active;

        $tenant->update(['status' => $newStatus]);

        $label = $newStatus === TenantStatus::Active ? 'activado' : 'suspendido';

        return back()->with('success', "Tenant \"{$tenant->name}\" {$label}.");
    }
}
