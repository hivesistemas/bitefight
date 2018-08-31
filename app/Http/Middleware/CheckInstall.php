<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Request;

class CheckInstall
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        if(env('CHECK_INSTALL', true) && !Request::is('install')) {
            if(file_exists(base_path('.env'))) unlink(base_path('.env'));

            return redirect(url('/install'));
        }

        return $next($request);
    }
}
