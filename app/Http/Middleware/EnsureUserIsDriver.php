<?php

namespace App\Http\Middleware;

use Closure;
use Tymon\JWTAuth\Facades\JWTAuth;
use App\Http\Responses\ApiResponse;

class EnsureUserIsDriver
{
    public function handle($request, Closure $next)
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();

            if ($user->role_id !== 3) { // 3 = Driver
                return ApiResponse::error('Unauthorized - Only drivers can access this route', null, 403);
            }

            return $next($request);

        } catch (\Exception $e) {
            return ApiResponse::error('Invalid or expired token', null, 401);
        }
    }
}
