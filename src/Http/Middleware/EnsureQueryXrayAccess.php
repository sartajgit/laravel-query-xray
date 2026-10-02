<?php

namespace Sartajgit\QueryXray\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

class EnsureQueryXrayAccess
{
    public function handle(Request $request, Closure $next)
    {
        if (Auth::check()) {
            return $next($request);
        }

        if (Route::has('login')) {
            return redirect()->route('login')
                ->with('query_xray_message', 'Please log in to view the Query X-Ray dashboard.');
        }

        return response()->view('query-xray::login-required', [], 403);
    }
}