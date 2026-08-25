<?php

use App\Http\Middleware\CheckTenantActive;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\SetCurrentTenant;
use App\Http\Middleware\VerifySyncSecret;
use App\Mail\ServerErrorOccurred;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;
use Spatie\Permission\Exceptions\UnauthorizedException;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
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
            'sync.secret' => VerifySyncSecret::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {

        // Errores 5xx / no-HTTP genuinos → alerta por correo (maximo una vez cada
        // 10 minutos por tipo+mensaje de excepcion, para no saturar el buzon con
        // el mismo error repitiendose en cada request).
        $exceptions->reportable(function (Throwable $e): void {
            if ($e instanceof HttpExceptionInterface && $e->getStatusCode() < 500) {
                return;
            }

            $throttleKey = 'error-alert:'.md5($e::class.$e->getMessage());

            if (Cache::has($throttleKey)) {
                return;
            }

            Cache::put($throttleKey, true, now()->addMinutes(10));

            Mail::to(config('mail.error_alert_address'))->queue(new ServerErrorOccurred(
                exceptionClass: $e::class,
                exceptionMessage: $e->getMessage(),
                file: $e->getFile(),
                line: $e->getLine(),
                url: request()->fullUrl(),
                occurredAt: now()->toDateTimeString(),
            ));
        });

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
