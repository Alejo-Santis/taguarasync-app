<?php

namespace App\Http\Controllers\Team;

use App\Http\Controllers\Controller;
use App\Http\Requests\Team\InviteTeamMemberRequest;
use App\Http\Requests\Team\UpdateTeamMemberRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;

class TeamController extends Controller
{
    public function index(Request $request): Response
    {
        $members = User::where('tenant_id', $request->user()->tenant_id)
            ->with('roles')
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->roles->first()?->name ?? 'sin_rol',
                'role_label' => $this->roleLabel($user->roles->first()?->name),
                'email_verified_at' => $user->email_verified_at?->format('d/m/Y'),
                'is_current' => $user->id === $request->user()->id,
            ]);

        $availableRoles = Role::whereIn('name', ['owner', 'admin', 'cashier', 'warehouse', 'accountant'])
            ->orderBy('name')
            ->get()
            ->map(fn (Role $r) => [
                'name' => $r->name,
                'label' => $this->roleLabel($r->name),
            ]);

        return Inertia::render('Team/Index', [
            'members' => $members,
            'availableRoles' => $availableRoles,
        ]);
    }

    public function store(InviteTeamMemberRequest $request): RedirectResponse
    {
        $user = User::create([
            'tenant_id' => $request->user()->tenant_id,
            'name' => $request->validated('name'),
            'email' => $request->validated('email'),
            'password' => Hash::make(Str::random(16)),
            'email_verified_at' => now(),
        ]);

        $user->assignRole($request->validated('role'));

        return back()->with('success', "Usuario {$user->name} creado. Comparte sus credenciales de acceso.");
    }

    public function update(User $member, UpdateTeamMemberRequest $request): RedirectResponse
    {
        $member->update(['name' => $request->validated('name')]);
        $member->syncRoles([$request->validated('role')]);

        return back()->with('success', "Usuario {$member->name} actualizado correctamente.");
    }

    public function resetPassword(User $member, Request $request): RedirectResponse
    {
        if ($member->tenant_id !== $request->user()->tenant_id) {
            abort(403);
        }

        $newPassword = Str::random(12);
        $member->update(['password' => Hash::make($newPassword)]);

        return back()->with('success', "Nueva contrasena para {$member->name}: {$newPassword}");
    }

    private function roleLabel(?string $role): string
    {
        return match ($role) {
            'owner' => 'Propietario',
            'admin' => 'Administrador',
            'cashier' => 'Cajero',
            'warehouse' => 'Bodeguero',
            'accountant' => 'Contador',
            default => 'Sin rol',
        };
    }
}
