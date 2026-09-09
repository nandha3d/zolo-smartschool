<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Guards endpoints that exist to be driven by an external scheduler rather than
 * by a signed-in user. The caller proves itself with the CRON_SECRET value,
 * sent either as an X-Cron-Secret header or a ?cron_secret= query parameter.
 *
 * Fails closed: with no CRON_SECRET configured the route is unreachable, so a
 * missing environment variable can't silently leave the endpoint open.
 */
class VerifyCronSecret
{
    public function handle(Request $request, Closure $next): Response
    {
        $configured = (string) config('app.cron_secret');
        $provided = (string) ($request->header('X-Cron-Secret') ?? $request->query('cron_secret', ''));

        if ($configured === '' || $provided === '' || !hash_equals($configured, $provided)) {
            abort(404);
        }

        return $next($request);
    }
}
