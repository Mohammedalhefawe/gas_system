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
    public function registerCustomer(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'full_name' => 'required|string|max:255',
            'phone_number' => 'required|string',
            'password' => 'required|string|min:8|confirmed',
        ]);

        if ($validator->fails()) {
            return ApiResponse::error(__('messages.validation_failed'), $validator->errors(), 422);
        }

        $existingUser = User::where('phone_number', $request->phone_number)->first();
        if ($existingUser && $existingUser->is_verified) {
            return ApiResponse::error(__('messages.phone_already_registered'), null, 409);
        }

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
            $user = User::create([
                'phone_number' => $request->phone_number,
                'password' => Hash::make($request->password),
                'is_verified' => false,
                'role_id' => 2,
                'created_at' => now(),
            ]);

            $customer = Customer::create([
                'user_id' => $user->user_id,
                'full_name' => $request->full_name,
            ]);
        }

        $pin = 111111;
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
            'message' => __("messages.pin_message", ['pin' => $pin]),
        ]);

        if ($response->failed()) {
            return ApiResponse::error(__('messages.failed_to_send_sms'), $response->body(), 500);
        }

        return ApiResponse::success(__('messages.customer_registered'), [
            'user_id' => $user->user_id,
            'phone_number' => $user->phone_number,
        ], 201);
    }

    public function forgotPassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'phone_number' => 'required|string',
        ]);

        if ($validator->fails()) {
            return ApiResponse::error(__('messages.validation_failed'), $validator->errors(), 422);
        }

        $user = User::where('phone_number', $request->phone_number)->first();

        if (!$user) {
            return ApiResponse::error(__('messages.user_not_found'), null, 404);
        }

        $pin = 111111;
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
            'message' => __("messages.reset_pin_message", ['pin' => $pin]),
        ]);

        if ($response->failed()) {
            return ApiResponse::error(__('messages.failed_to_send_sms'), $response->body(), 500);
        }

        return ApiResponse::success(__('messages.reset_pin_sent'), [
            'phone_number' => $user->phone_number,
        ]);
    }

    public function verifyResetPin(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'phone_number' => 'required|string',
            'pin' => 'required|numeric',
        ]);

        if ($validator->fails()) {
            return ApiResponse::error(__('messages.validation_failed'), $validator->errors(), 422);
        }

        $user = User::where('phone_number', $request->phone_number)->first();

        if (!$user) {
            return ApiResponse::error(__('messages.user_not_found'), null, 404);
        }

        if ($user->verification_pin != $request->pin || now()->gt($user->pin_expires_at)) {
            return ApiResponse::error(__('messages.invalid_or_expired_pin'), null, 400);
        }

        return ApiResponse::success(__('messages.pin_verified'));
    }

    public function resetPassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'phone_number' => 'required|string',
            'pin' => 'required|numeric',
            'new_password' => 'required|string|min:8|confirmed',
        ]);

        if ($validator->fails()) {
            return ApiResponse::error(__('messages.validation_failed'), $validator->errors(), 422);
        }

        $user = User::where('phone_number', $request->phone_number)->first();

        if (!$user) {
            return ApiResponse::error(__('messages.user_not_found'), null, 404);
        }

        if ($user->verification_pin != $request->pin || now()->gt($user->pin_expires_at)) {
            return ApiResponse::error(__('messages.invalid_or_expired_pin'), null, 400);
        }

        $user->update([
            'password' => Hash::make($request->new_password),
            'verification_pin' => null,
            'pin_expires_at' => null,
        ]);

        return ApiResponse::success(__('messages.password_reset_success'));
    }

    public function verifyCustomer(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'phone_number' => 'required|string',
            'pin' => 'required|numeric',
        ]);

        if ($validator->fails()) {
            return ApiResponse::error(__('messages.validation_failed'), $validator->errors(), 422);
        }

        $user = User::where('phone_number', $request->phone_number)->first();
        if (!$user) {
            return ApiResponse::error(__('messages.user_not_found'), null, 404);
        }

        if ($user->is_verified) {
            return ApiResponse::success(__('messages.account_already_verified'));
        }

        if ($user->verification_pin != $request->pin || now()->gt($user->pin_expires_at)) {
            return ApiResponse::error(__('messages.invalid_or_expired_pin'), null, 400);
        }

        $user->update([
            'is_verified' => true,
            'verification_pin' => null,
            'pin_expires_at' => null,
        ]);

        $token = JWTAuth::fromUser($user);
        $user->load('role');
        $customer = $user->customer;

        return ApiResponse::success(__('messages.account_verified'), [
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

    public function resendCustomerPin(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'phone_number' => 'required|string',
        ]);

        if ($validator->fails()) {
            return ApiResponse::error(__('messages.validation_failed'), $validator->errors(), 422);
        }

        $user = User::where('phone_number', $request->phone_number)->first();

        if (!$user) {
            return ApiResponse::error(__('messages.user_not_found'), null, 404);
        }

        if ($user->is_verified) {
            return ApiResponse::success(__('messages.account_already_verified'));
        }

        $pin = rand(100000, 999999);
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
            'message' => __("messages.pin_message", ['pin' => $pin]),
        ]);

        if ($response->failed()) {
            return ApiResponse::error(__('messages.failed_to_send_sms'), $response->body(), 500);
        }

        return ApiResponse::success(__('messages.new_pin_sent'), [
            'user_id' => $user->user_id,
            'phone_number' => $user->phone_number,
        ]);
    }

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
            return ApiResponse::error(__('messages.validation_failed'), $validator->errors(), 422);
        }

        $user = User::create([
            'full_name' => $request->full_name,
            'phone_number' => $request->phone_number,
            'password' => Hash::make($request->password),
            'is_verified' => true,
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

        return ApiResponse::success(__('messages.driver_registered'), [
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

    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'phone_number' => 'required|string',
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            return ApiResponse::error(__('messages.validation_failed'), $validator->errors(), 422);
        }

        $credentials = $request->only('phone_number', 'password');

        if (!$token = JWTAuth::attempt($credentials)) {
            return ApiResponse::error(__('messages.invalid_credentials'), null, 401);
        }

        $user = JWTAuth::user()->load('role');

        if ($user->role_id == 2 && !$user->is_verified) {
            return ApiResponse::error(__('messages.account_not_verified'), null, 403);
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

        return ApiResponse::success(__('messages.login_successful'), [
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

    public function logout()
    {
        try {
            JWTAuth::invalidate(JWTAuth::getToken());
            return ApiResponse::success(__('messages.logout_success'));
        } catch (\Exception $e) {
            return ApiResponse::error(__('messages.logout_failed'), null, 500);
        }
    }

    public function me()
    {
        try {
            $user = JWTAuth::parseToken()->authenticate()->load('role');

            if ($user->role_id == 2 && !$user->is_verified) {
                return ApiResponse::error(__('messages.account_not_verified'), null, 403);
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

            return ApiResponse::success(__('messages.auth_user'), [
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
            return ApiResponse::error(__('messages.token_invalid'), null, 401);
        }
    }

    public function getAllCustomers(Request $request)
    {
        try {
            $query = Customer::with('user');

            $perPage = $request->get('per_page', 10);
            $customers = $query->orderBy('customer_id', 'desc')->paginate($perPage);

            return ApiResponse::success(__('messages.customers_retrieved'), [
                'items'        => $customers->items(),
                'current_page' => $customers->currentPage(),
                'last_page'    => $customers->lastPage(),
                'per_page'     => $customers->perPage(),
                'total'        => $customers->total(),
            ]);
        } catch (\Exception $e) {
            return ApiResponse::error(__('messages.failed_to_retrieve_customers'), $e->getMessage(), 500);
        }
    }


    public function toggleBlockCustomer($customer_id)
    {
        $customer = Customer::find($customer_id);

        if (!$customer) {
            return ApiResponse::error(__('messages.customer_not_found'), null, 404);
        }

        $customer->blocked = !$customer->blocked;
        $customer->save();

        return ApiResponse::success(
            $customer->blocked ? __('messages.customer_blocked') : __('messages.customer_unblocked'),
            ['customer' => $customer]
        );
    }
}
