<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;

class CheckTwoFactorAuthenticated
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {

        // Was && - which required one user to hold both roles at once. Nobody does, so
        // this branch never ran and 2FA expiry was never enforced despite the UI toggle.
        if (Auth::user() && (Auth::user()->hasRole('School Admin') || Auth::user()->hasRole('Super Admin'))) {
            
            $user = DB::table('users')->where('id',Auth::user()->id)->first();
            $currentTime = now()->format('Y-m-d H:i:s');

            if ($currentTime >= $user->two_factor_expires_at && $user->two_factor_enabled == 1) {
                DB::table('users')->where('email',$user->email)->update(['two_factor_secret' => null,'two_factor_expires_at' => null]);
                Auth::logout();
                $request->session()->flush();
                $request->session()->regenerate();
                session()->forget('school_database_name');
                Session::forget('school_database_name');
                return redirect('/login');
               
            }
        } 

        return $next($request);
    }
}

