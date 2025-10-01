<?php

namespace App\Http\Controllers;

use App\Models\UserAddress;
use Illuminate\Http\Request;
use App\Http\Responses\ApiResponse;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Facades\JWTAuth;
use Exception;

class UserAddressController extends Controller
{
    // استعراض كل العناوين للمستخدم
    public function index()
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();
            if (!$user) {
                return ApiResponse::error('Unauthenticated', null, 401);
            }

            $addresses = UserAddress::where('user_id', $user->user_id)->get();
            return ApiResponse::success('Addresses retrieved successfully', ['addresses' => $addresses]);
        } catch (Exception $e) {
            return ApiResponse::error('Failed to retrieve addresses', $e->getMessage(), 500);
        }
    }

    // عرض عنوان محدد
    public function show($id)
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();
            $address = UserAddress::where('user_id', $user->user_id)->find($id);

            if (!$address) {
                return ApiResponse::error('Address not found', null, 404);
            }

            return ApiResponse::success('Address retrieved successfully', ['address' => $address]);
        } catch (Exception $e) {
            return ApiResponse::error('Failed to retrieve address', $e->getMessage(), 500);
        }
    }

    // اضافة عنوان جديد
    public function store(Request $request)
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();

            $validator = Validator::make($request->all(), [
                'address' => 'required|string|max:255',
                'city' => 'required|string|max:100',
                'latitude' => 'nullable|numeric',
                'longitude' => 'nullable|numeric',
                'is_default' => 'nullable|boolean',
            ]);

            if ($validator->fails()) {
                return ApiResponse::error('Validation failed', $validator->errors(), 422);
            }

            $address = UserAddress::create([
                'user_id' => $user->user_id,
                'address' => $request->address,
                'city' => $request->city,
                'latitude' => $request->latitude,
                'longitude' => $request->longitude,
                'is_default' => $request->is_default ?? false,
            ]);

            return ApiResponse::success('Address created successfully', ['address' => $address], 201);
        } catch (Exception $e) {
            return ApiResponse::error('Failed to create address', $e->getMessage(), 500);
        }
    }

    // تعديل عنوان
    public function update(Request $request, $id)
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();
            $address = UserAddress::where('user_id', $user->user_id)->find($id);

            if (!$address) {
                return ApiResponse::error('Address not found', null, 404);
            }

            $validator = Validator::make($request->all(), [
                'address' => 'sometimes|string|max:255',
                'city' => 'sometimes|string|max:100',
                'latitude' => 'nullable|numeric',
                'longitude' => 'nullable|numeric',
                'is_default' => 'nullable|boolean',
            ]);

            if ($validator->fails()) {
                return ApiResponse::error('Validation failed', $validator->errors(), 422);
            }

            $address->update($request->only(['address', 'city', 'latitude', 'longitude', 'is_default']));

            return ApiResponse::success('Address updated successfully', ['address' => $address]);
        } catch (Exception $e) {
            return ApiResponse::error('Failed to update address', $e->getMessage(), 500);
        }
    }

    // حذف عنوان
    public function destroy($id)
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();
            $address = UserAddress::where('user_id', $user->user_id)->find($id);

            if (!$address) {
                return ApiResponse::error('Address not found', null, 404);
            }

            $address->delete();
            return ApiResponse::success('Address deleted successfully');
        } catch (Exception $e) {
            return ApiResponse::error('Failed to delete address', $e->getMessage(), 500);
        }
    }
}
