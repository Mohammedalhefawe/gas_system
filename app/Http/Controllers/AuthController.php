<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Customer;
use App\Models\Driver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Http;
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
            'phone_number' => 'required|string',
            'password' => 'required|string|min:8|confirmed',
        ]);

        if ($validator->fails()) {
            return ApiResponse::error('Validation failed', $validator->errors(), 422);
        }

        // تحقق إذا في User بنفس الرقم
        $existingUser = User::where('phone_number', $request->phone_number)->first();
        if ($existingUser && $existingUser->is_verified) {
            return ApiResponse::error('Phone number already registered and verified', null, 409);
        }

        // إذا موجود بس مو مفعّل → منحدّث بياناته
        if ($existingUser && !$existingUser->is_verified) {
            $user = $existingUser;
            $user->update([
                'password' => Hash::make($request->password),
                'role_id' => 2,
                'created_at' => now(),
            ]);

            $customer = $user->customer;
            if ($customer) {
                $customer->update(['full_name' => $request->full_name]);
            } else {
                $customer = Customer::create([
                    'user_id' => $user->user_id,
                    'full_name' => $request->full_name,
                ]);
            }
        } else {
            // إنشاء جديد
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
        }

        // توليد PIN وحفظه
        $pin = 111111;
        // $pin = rand(100000, 999999);
        $user->update([
            'verification_pin' => $pin,
            'pin_expires_at' => now()->addMinutes(10),
        ]);

        $phone = $user->phone_number;

        if (!str_starts_with($phone, '+963')) {
            $phone = '+963' . ltrim($phone, '0');
        }

        $response = Http::withHeaders([
            'Authorization' => env('SMS_API_KEY'),
        ])->post(env('SMS_API_URL'), [
            'to' => $phone,
            'message' => "Your verification PIN is $pin",
        ]);

        if ($response->failed()) {
            return ApiResponse::error('Failed to send SMS', $response->body(), 500);
        }

        return ApiResponse::success('Customer registered successfully, PIN sent for verification', [
            'user_id' => $user->user_id,
            'phone_number' => $user->phone_number,
        ], 201);
    }


    /**
     * Forgot Password - Send reset PIN
     */
    public function forgotPassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'phone_number' => 'required|string',
        ]);

        if ($validator->fails()) {
            return ApiResponse::error('Validation failed', $validator->errors(), 422);
        }

        $user = User::where('phone_number', $request->phone_number)->first();

        if (!$user) {
            return ApiResponse::error('User not found', null, 404);
        }

        // توليد PIN جديد
        // $pin = rand(100000, 999999);
        $pin = 111111;
        $user->update([
            'verification_pin' => $pin,
            'pin_expires_at' => now()->addMinutes(10),
        ]);

        // تجهيز الرقم
        $phone = $user->phone_number;
        if (!str_starts_with($phone, '+963')) {
            $phone = '+963' . ltrim($phone, '0');
        }

        // إرسال SMS
        $response = Http::withHeaders([
            'Authorization' => env('SMS_API_KEY'),
        ])->post(env('SMS_API_URL'), [
            'to' => $phone,
            'message' => "Your password reset PIN is $pin",
        ]);

        if ($response->failed()) {
            return ApiResponse::error('Failed to send SMS', $response->body(), 500);
        }

        return ApiResponse::success('Reset PIN sent successfully', [
            'phone_number' => $user->phone_number,
        ]);
    }

    /**
     * Verify Reset PIN
     */
    public function verifyResetPin(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'phone_number' => 'required|string',
            'pin' => 'required|numeric',
        ]);

        if ($validator->fails()) {
            return ApiResponse::error('Validation failed', $validator->errors(), 422);
        }

        $user = User::where('phone_number', $request->phone_number)->first();

        if (!$user) {
            return ApiResponse::error('User not found', null, 404);
        }

        if ($user->verification_pin != $request->pin || now()->gt($user->pin_expires_at)) {
            return ApiResponse::error('Invalid or expired PIN', null, 400);
        }

        return ApiResponse::success('PIN verified successfully');
    }

    /**
     * Reset Password
     */
    public function resetPassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'phone_number' => 'required|string',
            'pin' => 'required|numeric',
            'new_password' => 'required|string|min:8|confirmed',
        ]);

        if ($validator->fails()) {
            return ApiResponse::error('Validation failed', $validator->errors(), 422);
        }

        $user = User::where('phone_number', $request->phone_number)->first();

        if (!$user) {
            return ApiResponse::error('User not found', null, 404);
        }

        if ($user->verification_pin != $request->pin || now()->gt($user->pin_expires_at)) {
            return ApiResponse::error('Invalid or expired PIN', null, 400);
        }

        // تحديث كلمة المرور
        $user->update([
            'password' => Hash::make($request->new_password),
            'verification_pin' => null,
            'pin_expires_at' => null,
        ]);

        return ApiResponse::success('Password reset successfully');
    }


    /**
     * Verify Customer PIN
     */
    public function verifyCustomer(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'phone_number' => 'required|string',
            'pin' => 'required|numeric',
        ]);

        if ($validator->fails()) {
            return ApiResponse::error('Validation failed', $validator->errors(), 422);
        }

        $user = User::where('phone_number', $request->phone_number)->first();
        if (!$user) {
            return ApiResponse::error('User not found', null, 404);
        }

        if ($user->is_verified) {
            return ApiResponse::success('Account already verified');
        }

        if ($user->verification_pin != $request->pin || now()->gt($user->pin_expires_at)) {
            return ApiResponse::error('Invalid or expired PIN', null, 400);
        }

        $user->update([
            'is_verified' => true,
            'verification_pin' => null,
            'pin_expires_at' => null,
        ]);

        $token = JWTAuth::fromUser($user);
        $user->load('role');

        $customer = $user->customer;

        return ApiResponse::success('Account verified successfully', [
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
        ]);
    }


    /**
     * Resend Customer Verification PIN
     */
    public function resendCustomerPin(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'phone_number' => 'required|string',
        ]);

        if ($validator->fails()) {
            return ApiResponse::error('Validation failed', $validator->errors(), 422);
        }

        $user = User::where('phone_number', $request->phone_number)->first();

        if (!$user) {
            return ApiResponse::error('User not found', null, 404);
        }

        if ($user->is_verified) {
            return ApiResponse::success('Account already verified');
        }

        // توليد PIN جديد
        $pin = rand(100000, 999999);
        $user->update([
            'verification_pin' => $pin,
            'pin_expires_at' => now()->addMinutes(10),
        ]);

        // معالجة الرقم مع البادئة
        $phone = $user->phone_number;
        if (!str_starts_with($phone, '+963')) {
            $phone = '+963' . ltrim($phone, '0');
        }

        // إرسال عبر Traccar SMS API
        $response = Http::withHeaders([
            'Authorization' => env('SMS_API_KEY'),
        ])->post(env('SMS_API_URL'), [
            'to' => $phone,
            'message' => "Your new verification PIN is $pin",
        ]);

        if ($response->failed()) {
            return ApiResponse::error('Failed to send SMS', $response->body(), 500);
        }

        return ApiResponse::success('New PIN sent successfully', [
            'user_id' => $user->user_id,
            'phone_number' => $user->phone_number,
        ]);
    }


    /**
     * Register Driver (no verify needed)
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
            'full_name' => $request->full_name,
            'phone_number' => $request->phone_number,
            'password' => Hash::make($request->password),
            'is_verified' => true, // Drivers not need verification
            'role_id' => 3,
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
     * Login (all roles)
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

        if ($user->role_id == 2 && !$user->is_verified) {
            return ApiResponse::error('Account not verified, please enter PIN', null, 403);
        }

        $role_info = null;
        if ($user->role_id == 2) {
            $role_info = [
                'customer_id' => $user->customer->customer_id,
                'full_name' => $user->customer->full_name,
            ];
        } elseif ($user->role_id == 3) {
            $role_info = [
                'driver_id' => $user->driver->driver_id,
                'full_name' => $user->driver->full_name,
                'vehicle_type' => $user->driver->vehicle_type,
                'license_number' => $user->driver->license_number,
                'is_available' => $user->driver->is_available,
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

            if ($user->role_id == 2 && !$user->is_verified) {
                return ApiResponse::error('Account not verified, please enter PIN', null, 403);
            }

            $role_info = null;
            if ($user->role_id == 2) {
                $role_info = [
                    'customer_id' => $user->customer->customer_id,
                    'full_name' => $user->customer->full_name,
                ];
            } elseif ($user->role_id == 3) {
                $role_info = [
                    'driver_id' => $user->driver->driver_id,
                    'full_name' => $user->driver->full_name,
                    'vehicle_type' => $user->driver->vehicle_type,
                    'license_number' => $user->driver->license_number,
                    'is_available' => $user->driver->is_available,
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
