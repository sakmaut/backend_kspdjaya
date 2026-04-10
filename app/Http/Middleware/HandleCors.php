<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class HandleCors
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        // $response->header('Access-Control-Allow-Origin', '*');
        // $response->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS');
        // $response->header('Access-Control-Allow-Headers', 'Content-Type, Accept, Origin, X-Requested-With, Authorization,multipart/form-data');
        // $response->header('Access-Control-Allow-Credentials', 'true');
        // $response->header('Access-Control-Max-Age', '86400');

        $response->headers->set('Access-Control-Allow-Origin', '*');
        $response->headers->set('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS');
        $response->headers->set('Access-Control-Allow-Headers', 'Content-Type, Accept, Origin, X-Requested-With, Authorization, multipart/form-data');
        $response->headers->set('Access-Control-Allow-Credentials', 'true');
        $response->headers->set('Access-Control-Max-Age', '86400');

        return $response;
    }
}
