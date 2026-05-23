<?php

namespace App\Http\Middleware;

use App\Enums\TenantStatus;
use App\Support\Tenancy\CurrentTenant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckTenantActive
{
    public function __construct(private readonly CurrentTenant $currentTenant) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $tenant = $this->currentTenant->get();

        if (! $tenant) {
            return $next($request);
        }

        if ($tenant->status === TenantStatus::Suspended) {
            auth()->logout();

            return redirect()->route('login')
                ->with('error', 'Tu cuenta ha sido suspendida. Contacta al soporte.');
        }

        if ($tenant->trial_ends_at && $tenant->trial_ends_at->isPast() && $tenant->status !== TenantStatus::Active) {
            return redirect()->route('dashboard')
                ->with('warning', 'Tu periodo de prueba ha vencido. Actualiza tu plan para continuar.');
        }

        return $next($request);
    }
}
