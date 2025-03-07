<?php

namespace App\Http\Middleware;

use Carbon\Carbon;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TimeAccessMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $currentTime = Carbon::now()->format('H');

        if ($currentTime >= 23 || $currentTime < 3) {
            abort(403, 'Akses API dibatasi pada jam 11 malam hingga 3 pagi.');
        }
        
        return $next($request);
    }
}
