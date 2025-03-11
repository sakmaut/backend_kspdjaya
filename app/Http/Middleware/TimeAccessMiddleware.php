<?php

namespace App\Http\Middleware;

use Carbon\Carbon;
use Closure;
use Exception;
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
        try {
            $currentTime = Carbon::now()->format('H');

            $setUrl = "https://api.kspdjaya.id";
            $time1 = 23;
            $time2 = 3;

            if ($request->getSchemeAndHttpHost() == $setUrl && ($currentTime >= $time1 || $currentTime < $time2)) {
                throw new Exception("Akses API dibatasi pada jam " . $time1 . " malam hingga " . $time2 . " pagi.", 503);
            }

            return $next($request);
        } catch (\Throwable $e) {
            return response()->json($e->getMessage(), 503);
        }
    }
}
