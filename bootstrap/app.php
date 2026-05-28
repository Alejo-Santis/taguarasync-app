<?php

use App\Http\Middleware\CheckTenantActive;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\SetCurrentTenant;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Spatie\Permission\Exceptions\UnauthorizedException;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            SetCurrentTenant::class,
            CheckTenantActive::class,
            HandleInertiaRequests::class,
        ]);

        $middleware->alias([
            'role' => RoleMiddleware::class,
            'permission' => PermissionMiddleware::class,
            'role_or_permission' => RoleOrPermissionMiddleware::class,
            'tenant.active' => CheckTenantActive::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {

        // Spatie UnauthorizedException → flash y redirige atrás (mejor UX dentro de la app)
        $exceptions->render(function (UnauthorizedException $e) {
            return back()->with('error', 'No tienes permiso para realizar esta acción.');
        });

        // Todos los demás HttpException → página de error Inertia
        // Inertia::render() maneja tanto visitas completas (SSR-like) como requests XHR
        $exceptions->render(function (HttpException $e, Request $request) {
            $status = $e->getStatusCode();

            // En 5xx nunca exponemos el mensaje original (seguridad)
            // En 4xx usamos el mensaje si existe, sino null (la vista usa su propio texto)
            $message = ($status < 500 && $e->getMessage())
                ? $e->getMessage()
                : null;

            return Inertia::render('Error', [
                'status' => $status,
                'message' => $message,
            ])
                ->toResponse($request)
                ->setStatusCode($status);
        });

        // 419 (CSRF expirado) → redirige atrás con aviso
        $exceptions->respond(function (Response $response) {
            if ($response->getStatusCode() === 419) {
                return back()->with('warning', 'La sesión expiró. Intenta nuevamente.');
            }

            return $response;
        });
    })->create();
