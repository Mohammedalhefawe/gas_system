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
    // استعراض كل العناوين للعميل
    public function index()
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();
            $customer = $user->customer;

            if (!$customer) {
                return ApiResponse::error(__('messages.customer_not_found'), null, 404);
            }

            $addresses = UserAddress::where('customer_id', $customer->customer_id)->get();
            return ApiResponse::success(__('messages.addresses_retrieved'), ['addresses' => $addresses]);
        } catch (Exception $e) {
            return ApiResponse::error(__('messages.failed_to_retrieve_addresses'), $e->getMessage(), 500);
        }
    }

    // عرض عنوان محدد
    public function show($id)
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();
            $customer = $user->customer;

            if (!$customer) {
                return ApiResponse::error(__('messages.customer_not_found'), null, 404);
            }

            $address = UserAddress::where('customer_id', $customer->customer_id)->find($id);
            if (!$address) {
                return ApiResponse::error(__('messages.address_not_found'), null, 404);
            }

            return ApiResponse::success(__('messages.address_retrieved'), ['address' => $address]);
        } catch (Exception $e) {
            return ApiResponse::error(__('messages.failed_to_retrieve_address'), $e->getMessage(), 500);
        }
    }

    // اضافة عنوان جديد
    public function store(Request $request)
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();
            $customer = $user->customer;

            if (!$customer) {
                return ApiResponse::error(__('messages.customer_not_found'), null, 404);
            }

            $validator = Validator::make($request->all(), [
                'address' => 'required|string|max:255',
                'city' => 'required|string|max:100',
                'latitude' => 'nullable|numeric',
                'longitude' => 'nullable|numeric',
                'floor_number' => 'nullable|string|max:20',
                'address_name' => 'nullable|string|max:100',
                'details' => 'nullable|string',
            ]);

            if ($validator->fails()) {
                return ApiResponse::error(__('messages.validation_failed'), $validator->errors(), 422);
            }

            $address = UserAddress::create([
                'customer_id' => $customer->customer_id,
                'address' => $request->address,
                'city' => $request->city,
                'latitude' => $request->latitude,
                'longitude' => $request->longitude,
                'floor_number' => $request->floor_number,
                'address_name' => $request->address_name,
                'details' => $request->details,
            ]);

            return ApiResponse::success(__('messages.address_created'), ['address' => $address], 201);
        } catch (Exception $e) {
            return ApiResponse::error(__('messages.failed_to_create_address'), $e->getMessage(), 500);
        }
    }

    // تعديل عنوان
    public function update(Request $request, $id)
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();
            $customer = $user->customer;

            if (!$customer) {
                return ApiResponse::error(__('messages.customer_not_found'), null, 404);
            }

            $address = UserAddress::where('customer_id', $customer->customer_id)->find($id);
            if (!$address) {
                return ApiResponse::error(__('messages.address_not_found'), null, 404);
            }

            $validator = Validator::make($request->all(), [
                'address' => 'sometimes|string|max:255',
                'city' => 'sometimes|string|max:100',
                'latitude' => 'nullable|numeric',
                'longitude' => 'nullable|numeric',
                'floor_number' => 'nullable|string|max:20',
                'address_name' => 'string|max:100',
                'details' => 'nullable|string',
            ]);

            if ($validator->fails()) {
                return ApiResponse::error(__('messages.validation_failed'), $validator->errors(), 422);
            }

            $address->update($request->only(['address', 'city', 'latitude', 'longitude', 'floor_number', 'address_name', 'details']));

            return ApiResponse::success(__('messages.address_updated'), ['address' => $address]);
        } catch (Exception $e) {
            return ApiResponse::error(__('messages.failed_to_update_address'), $e->getMessage(), 500);
        }
    }

    // حذف عنوان
    public function destroy($id)
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();
            $customer = $user->customer;

            if (!$customer) {
                return ApiResponse::error(__('messages.customer_not_found'), null, 404);
            }

            $address = UserAddress::where('customer_id', $customer->customer_id)->find($id);
            if (!$address) {
                return ApiResponse::error(__('messages.address_not_found'), null, 404);
            }

            $address->delete();
            return ApiResponse::success(__('messages.address_deleted'));
        } catch (Exception $e) {
            return ApiResponse::error(__('messages.failed_to_delete_address'), $e->getMessage(), 500);
        }
    }
}
