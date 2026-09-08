<?php

namespace App\Http\Middleware;

use App\Models\School;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

/**
 * Selects the tenant database for an API request from the school-code header.
 *
 * This middleware used to authenticate as well: it looked the bearer token up itself
 * and called Auth::loginUsingId(). That bypassed Sanctum's guard entirely, so token
 * abilities were never checked and an expired token kept working indefinitely.
 * Authentication is now left to the auth:sanctum middleware, which runs immediately
 * after this one - the order matters, because personal access tokens live in the
 * tenant database that this middleware selects.
 */
class APISwitchDatabase
{
    private const DEMO_EXCLUDED_URIS = [
        '/api/student/login',
        '/api/parent/login',
        '/api/teacher/login',
        '/contact',
        '/api/student/submit-online-exam-answers',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $schoolCode = $request->header('school-code');

        if (!$schoolCode) {
            // Previously returned error:false with code 200 while refusing to continue,
            // which told the caller the request had succeeded when it had not.
            return response()->json([
                'error'   => true,
                'message' => 'School Code is Required',
                'code'    => 400,
            ], 400);
        }

        $school = School::on('mysql')->where('code', $schoolCode)->first();

        if (!$school) {
            return response()->json([
                'error'   => true,
                'message' => 'Invalid school code',
                'code'    => 400,
            ], 400);
        }

        Config::set('database.connections.school.database', $school->database_name);
        DB::purge('school');
        DB::connection('school')->reconnect();
        DB::setDefaultConnection('school');

        if (config('app.demo_mode')
            && !$request->isMethod('get')
            && !in_array($request->getRequestUri(), self::DEMO_EXCLUDED_URIS, true)
        ) {
            return response()->json([
                'error'   => true,
                'message' => 'This is not allowed in the Demo Version.',
                'code'    => 112,
            ]);
        }

        return $next($request);
    }
}
