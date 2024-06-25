<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class CheckUserAccess
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        $path = $request->path();

        $hasAccess = DB::table('master_users_access_menu as t1')
                    ->join('master_menu as t2', 't2.id', '=', 't1.master_menu_id')
                    ->select('t1.users_id', 't2.route')
                    ->where('t1.users_id', $user->id)
                    ->where('t2.endpoint', $path)
                    ->exists();

        if (!$hasAccess) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        return $next($request);
    }
}
