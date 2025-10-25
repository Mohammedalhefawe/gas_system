<?php

namespace App\Http\Middleware;

use Closure;
use Tymon\JWTAuth\Facades\JWTAuth;
use App\Http\Responses\ApiResponse;

class EnsureUserIsCustomer
{
    public function handle($request, Closure $next)
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();

            // role_id == 2 means Customer
            if ($user->role_id !== 2) {
                return ApiResponse::error('Unauthorized - Only customers can access this route', null, 403);
            }

            return $next($request);

        } catch (\Exception $e) {
            return ApiResponse::error('Invalid or expired token', null, 401);
        }
    }
}
