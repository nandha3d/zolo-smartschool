<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;

/**
 * Confirms that the child_id on the request actually belongs to the authenticated
 * guardian, so a parent cannot read another family's records by changing an id.
 *
 * This used to repeat the tenant switch and authenticate by hand with
 * Auth::loginUsingId(), skipping Sanctum's guard. On API routes that work is now done
 * by APISwitchDatabase followed by auth:sanctum, both of which must run before this.
 */
class CheckChild {
    /**
     * Handle an incoming request.
     *
     * @param Request $request
     * @param Closure(Request): (Response|RedirectResponse) $next
     * @return JsonResponse
     */
    public function handle(Request $request, Closure $next) {
        // Web routes reach this without the API middleware, so they still need the
        // tenant connection selected from the session.
        if (!str_contains($request->getRequestUri(), 'api')) {
            $school_database_name = Session::get('school_database_name');
            if ($school_database_name) {
                Config::set('database.connections.school.database', $school_database_name);
                DB::purge('school');
                DB::connection('school')->reconnect();
                DB::setDefaultConnection('school');
            } else {
                DB::purge('school');
                DB::connection('mysql')->reconnect();
                DB::setDefaultConnection('mysql');
            }
        }

        if (!$request->user()) {
            return response()->json([
                'error'   => true,
                'message' => 'Unauthenticated.',
                'code'    => 401,
            ], 401);
        }

        $children = $request->user()->guardianRelationChild()->where('id', $request->child_id)->first();
        if (empty($children)) {
            return response()->json(array(
                'error'   => true,
                'message' => "Invalid Child ID Passed.",
                'code'    => 105,
            ));
        }
        return $next($request);
    }
}
