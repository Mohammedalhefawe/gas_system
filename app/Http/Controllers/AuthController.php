<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Tymon\JWTAuth\Facades\JWTAuth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Exceptions\JWTException;
use App\Http\Responses\ApiResponse;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'full_name' => 'required|string|max:255',
            'phone_number' => 'required|string|unique:users,phone_number',
            'email' => 'required|string|email|unique:users,email',
            'password' => 'required|string|min:8|confirmed',
        ]);

        if ($validator->fails()) {
            return ApiResponse::error('Validation failed', $validator->errors(), 422);
        }

        $user = User::create([
            'full_name' => $request->full_name,
            'phone_number' => $request->phone_number,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'is_verified' => false,
            'created_at' => now(),
        ]);

        $token = JWTAuth::fromUser($user);

        return ApiResponse::success('User registered successfully', [
            'user' => $user,
            'token' => $token,
        ], 201);
    }

    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|string|email',
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            return ApiResponse::error('Validation failed', $validator->errors(), 422);
        }

        $credentials = $request->only('email', 'password');

        try {
            if (!$token = JWTAuth::attempt($credentials)) {
                return ApiResponse::error('Invalid credentials', null, 401);
            }
        } catch (JWTException $e) {
            return ApiResponse::error('Could not create token', null, 500);
        }

        $user = JWTAuth::user();

        return ApiResponse::success('Login successful', [
            'user' => $user,
            'token' => $token,
        ]);
    }

    public function logout(Request $request)
    {
        try {
            JWTAuth::invalidate(JWTAuth::getToken());
            return ApiResponse::success('Successfully logged out');
        } catch (JWTException $e) {
            return ApiResponse::error('Failed to logout, please try again', null, 500);
        }
    }

    public function me(Request $request)
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();
            return ApiResponse::success('Authenticated user', [
                'user' => $user,
            ]);
        } catch (JWTException $e) {
            return ApiResponse::error('Token is invalid or expired', null, 401);
        }
    }
}
