<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifySyncSecret
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $secret = config('sync.cloud_sync_secret');

        if (empty($secret)) {
            abort(503, 'Sync endpoint not configured.');
        }

        $token = $request->bearerToken();

        if (! hash_equals($secret, (string) $token)) {
            abort(401, 'Invalid sync secret.');
        }

        return $next($request);
    }
}
