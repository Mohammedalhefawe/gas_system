<?php

namespace App\Http\Controllers;

use App\Models\UserDevice;
use Illuminate\Http\Request;
use Tymon\JWTAuth\Facades\JWTAuth;
use App\Http\Responses\ApiResponse;

class UserDeviceController extends Controller
{
    /**
     * Store or update a device token for a user.
     */
    public function store(Request $request)
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();

            $request->validate([
                'token' => 'required|string', // FCM token
                'device_id' => 'required|string',
                'platform' => 'required|string|in:android,ios',
                'app_version' => 'nullable|string',
            ]);

            // تحقق إذا الـ token موجود مسبقًا
            $device = UserDevice::where('user_id', $user->user_id)
                ->where('device_token', $request->token)
                ->first();

            if ($device) {
                // تحديث last_active فقط
                $device->update(['last_active' => now()]);
            } else {
                // إنشاء سجل جديد إذا لم يكن موجود
                $device = UserDevice::create([
                    'user_id' => $user->user_id,
                    'device_id' => $request->device_id,
                    'device_token' => $request->token,
                    'device_type' => $request->platform,
                    'app_version' => $request->app_version,
                    'last_active' => now(),
                ]);
            }

            return ApiResponse::success(__('messages.device_token_stored'), ['device' => $device], 201);
        } catch (\Exception $e) {
            return ApiResponse::error(__('messages.failed_to_store_device_token'), $e->getMessage(), 500);
        }
    }


    /**
     * Remove a device token.
     */
    public function destroy(Request $request)
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();

            $request->validate([
                'token' => 'required|string',
            ]);

            $device = UserDevice::where('user_id', $user->user_id)
                ->where('device_token', $request->token)
                ->first();

            if (!$device) {
                return ApiResponse::error(__('messages.device_not_found'), null, 404);
            }

            $device->delete();
            return ApiResponse::success(__('messages.device_token_removed'));
        } catch (\Exception $e) {
            return ApiResponse::error(__('messages.failed_to_remove_device_token'), $e->getMessage(), 500);
        }
    }
}
