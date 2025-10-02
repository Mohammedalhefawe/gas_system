<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Customer;
use App\Models\Driver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Facades\JWTAuth;
use App\Http\Responses\ApiResponse;

class AuthController extends Controller
{
    /**
     * Register Customer
     */
    public function registerCustomer(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'full_name' => 'required|string|max:255',
            'phone_number' => 'required|string|unique:users,phone_number',
            'password' => 'required|string|min:8|confirmed',
        ]);

        if ($validator->fails()) {
            return ApiResponse::error('Validation failed', $validator->errors(), 422);
        }

        $user = User::create([
            'phone_number' => $request->phone_number,
            'password' => Hash::make($request->password),
            'is_verified' => false,
            'role_id' => 2, // 2 = Customer
            'created_at' => now(),
        ]);

        $customer = Customer::create([
            'user_id' => $user->user_id,
            'full_name' => $request->full_name,
        ]);

        $token = JWTAuth::fromUser($user);
        $user->load('role');

        return ApiResponse::success('Customer registered successfully', [
            'user' => [
                'user_id' => $user->user_id,
                'phone_number' => $user->phone_number,
                'role' => $user->role->name,
                'is_verified' => $user->is_verified,
                'created_at' => $user->created_at,
                'role_info' => [
                    'customer_id' => $customer->customer_id,
                    'full_name' => $customer->full_name,
                ],
            ],
            'token' => $token,
        ], 201);
    }

    /**
     * Register Driver
     */
    public function registerDriver(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'full_name' => 'required|string|max:255',
            'phone_number' => 'required|string|unique:users,phone_number',
            'vehicle_type' => 'required|string',
            'license_number' => 'required|string|unique:drivers,license_number',
            'password' => 'required|string|min:8|confirmed',
        ]);

        if ($validator->fails()) {
            return ApiResponse::error('Validation failed', $validator->errors(), 422);
        }

        $user = User::create([
            'phone_number' => $request->phone_number,
            'password' => Hash::make($request->password),
            'is_verified' => false,
            'role_id' => 3, // 3 = Driver
            'created_at' => now(),
        ]);

        $driver = Driver::create([
            'user_id' => $user->user_id,
            'full_name' => $request->full_name,
            'vehicle_type' => $request->vehicle_type,
            'license_number' => $request->license_number,
            'is_available' => true,
            'rating' => 0.0,
        ]);

        $token = JWTAuth::fromUser($user);
        $user->load('role');

        return ApiResponse::success('Driver registered successfully', [
            'user' => [
                'user_id' => $user->user_id,
                'phone_number' => $user->phone_number,
                'role' => $user->role->name,
                'is_verified' => $user->is_verified,
                'created_at' => $user->created_at,
                'role_info' => [
                    'driver_id' => $driver->driver_id,
                    'full_name' => $driver->full_name,
                    'vehicle_type' => $driver->vehicle_type,
                    'license_number' => $driver->license_number,
                    'is_available' => $driver->is_available,
                ],
            ],
            'token' => $token,
        ], 201);
    }

    /**
     * Login (general for all roles)
     */
    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'phone_number' => 'required|string',
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            return ApiResponse::error('Validation failed', $validator->errors(), 422);
        }

        $credentials = $request->only('phone_number', 'password');

        if (!$token = JWTAuth::attempt($credentials)) {
            return ApiResponse::error('Invalid credentials', null, 401);
        }

        $user = JWTAuth::user()->load('role');

        // حسب الدور نجيب المعلومات
        $role_info = null;
        if ($user->role_id == 2) { // Customer
            $customer = $user->customer;
            $role_info = [
                'customer_id' => $customer->customer_id,
                'full_name' => $customer->full_name,
            ];
        } elseif ($user->role_id == 3) { // Driver
            $driver = $user->driver;
            $role_info = [
                'driver_id' => $driver->driver_id,
                'full_name' => $driver->full_name,
                'vehicle_type' => $driver->vehicle_type,
                'license_number' => $driver->license_number,
                'is_available' => $driver->is_available,
            ];
        }

        return ApiResponse::success('Login successful', [
            'user' => [
                'user_id' => $user->user_id,
                'phone_number' => $user->phone_number,
                'role' => $user->role->name,
                'is_verified' => $user->is_verified,
                'created_at' => $user->created_at,
                'role_info' => $role_info,
            ],
            'token' => $token,
        ]);
    }

    /**
     * Logout
     */
    public function logout()
    {
        try {
            JWTAuth::invalidate(JWTAuth::getToken());
            return ApiResponse::success('Successfully logged out');
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to logout, please try again', null, 500);
        }
    }

    /**
     * Authenticated user
     */
    public function me()
    {
        try {
            $user = JWTAuth::parseToken()->authenticate()->load('role');

            // حسب الدور نجيب المعلومات
            $role_info = null;
            if ($user->role_id == 2) { // Customer
                $customer = $user->customer;
                $role_info = [
                    'customer_id' => $customer->customer_id,
                    'full_name' => $customer->full_name,
                ];
            } elseif ($user->role_id == 3) { // Driver
                $driver = $user->driver;
                $role_info = [
                    'driver_id' => $driver->driver_id,
                    'full_name' => $driver->full_name,
                    'vehicle_type' => $driver->vehicle_type,
                    'license_number' => $driver->license_number,
                    'is_available' => $driver->is_available,
                ];
            }

            return ApiResponse::success('Authenticated user', [
                'user' => [
                    'user_id' => $user->user_id,
                    'phone_number' => $user->phone_number,
                    'role' => $user->role->name,
                    'is_verified' => $user->is_verified,
                    'created_at' => $user->created_at,
                    'role_info' => $role_info,
                ],
            ]);
        } catch (\Exception $e) {
            return ApiResponse::error('Token is invalid or expired', null, 401);
        }
    }
}
