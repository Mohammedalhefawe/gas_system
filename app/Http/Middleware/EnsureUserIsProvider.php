<?php

namespace App\Http\Middleware;

use Closure;
use Tymon\JWTAuth\Facades\JWTAuth;
use App\Http\Responses\ApiResponse;

class EnsureUserIsProvider
{
    public function handle($request, Closure $next)
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();

            if ($user->role_id !== 4) { // 4 = Provider
                return ApiResponse::error('Unauthorized - Only providers can access this route', null, 403);
            }

            return $next($request);

        } catch (\Exception $e) {
            return ApiResponse::error('Invalid or expired token', null, 401);
        }
    }
}