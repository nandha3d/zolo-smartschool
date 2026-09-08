<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Holds an account at the change-password screen until it has replaced the password it
 * was created with. Accounts are created with a generated secret that is relayed by
 * whoever created them, so without this the relayed secret would stay valid forever.
 */
class ForcePasswordChange
{
    /**
     * Routes that must stay reachable, otherwise the user cannot get out of the
     * redirect loop or sign out.
     */
    private const ALLOWED_ROUTES = [
        'auth.change-password.index',
        'auth.change-password.update',
        'auth.logout',
        'login',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        if (!$user || !($user->must_change_password ?? false)) {
            return $next($request);
        }

        if (in_array($request->route()?->getName(), self::ALLOWED_ROUTES, true)) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'error'   => true,
                'message' => trans('You must set a new password before continuing.'),
                'code'    => 113,
            ], 403);
        }

        return redirect()->route('auth.change-password.index')
            ->withErrors(['message' => trans('You must set a new password before continuing.')]);
    }
}
