<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Tymon\JWTAuth\Facades\JWTAuth;

class AdminMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $user = JWTAuth::parseToken()->authenticate();

        if (!$user->is_admin) {
            return response()->json([
                'status' => 'error',
                'message' => 'Access denied, only admins allowed'
            ], 403);
        }

        return $next($request);
    }
}
