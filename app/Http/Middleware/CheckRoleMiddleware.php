<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Tymon\JWTAuth\Facades\JWTAuth;
use App\Http\Responses\ApiResponse;

class CheckRole
{
    /**
     * Handle an incoming request.
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @param  mixed ...$roles
     */
    public function handle(Request $request, Closure $next, ...$roles)
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();

            if (!in_array($user->role->name, $roles)) {
                return ApiResponse::error('Unauthorized', null, 403);
            }

        } catch (\Exception $e) {
            return ApiResponse::error('Token is invalid or expired', null, 401);
        }

        return $next($request);
    }
}
