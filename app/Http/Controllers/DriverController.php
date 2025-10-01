<?php

namespace App\Http\Controllers;

use App\Models\Driver;
use App\Models\Order;
use Illuminate\Http\Request;
use Tymon\JWTAuth\Facades\JWTAuth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Exceptions\JWTException;
use App\Http\Responses\ApiResponse;

class DriverController extends Controller
{
    // Register driver
    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'full_name' => 'required|string|max:255',
            'phone_number' => 'required|string|unique:drivers,phone_number',
            'password' => 'required|string|min:8|confirmed',
            'vehicle_type' => 'required|string',
            'license_number' => 'required|string|unique:drivers,license_number',
        ]);

        if ($validator->fails()) {
            return ApiResponse::error('Validation failed', $validator->errors(), 422);
        }

        $driver = Driver::create([
            'full_name' => $request->full_name,
            'phone_number' => $request->phone_number,
            'password' => Hash::make($request->password),
            'vehicle_type' => $request->vehicle_type,
            'license_number' => $request->license_number,
            'is_available' => true,
        ]);

        $token = JWTAuth::fromUser($driver);

        return ApiResponse::success('Driver registered successfully', [
            'driver' => $driver,
            'token' => $token,
        ], 201);
    }

    // Driver login
  public function login(Request $request)
{
    $validator = Validator::make($request->all(), [
        'phone_number' => 'required|string',
        'password' => 'required|string',
    ]);

    if ($validator->fails()) {
        return ApiResponse::error('Validation failed', $validator->errors(), 422);
    }

    $driver = Driver::where('phone_number', $request->phone_number)->first();

    if (!$driver) {
        return ApiResponse::error('Driver not found', null, 404);
    }

    if (!Hash::check($request->password, $driver->password)) {
        return ApiResponse::error('Incorrect password', null, 401);
    }

    $token = JWTAuth::fromUser($driver);

    return ApiResponse::success('Login successful', [
        'driver' => $driver,
        'token' => $token,
    ]);
}


    // Get authenticated driver info
    public function me()
    {
        try {
            $driver = JWTAuth::parseToken()->authenticate();
            return ApiResponse::success('Authenticated driver', ['driver' => $driver]);
        } catch (JWTException $e) {
            return ApiResponse::error('Token is invalid or expired', null, 401);
        }
    }

    // Accept order (sets status accepted)
    public function acceptOrder($order_id)
    {
        try {
            $driver = JWTAuth::parseToken()->authenticate();
            $order = Order::where('order_id', $order_id)
                          ->where('order_status', 'pending')
                          ->first();

            if (!$order) {
                return ApiResponse::error('Order not available for acceptance', null, 404);
            }

            $order->update([
                'driver_id' => $driver->driver_id,
                'order_status' => 'accepted',
            ]);

            return ApiResponse::success('Order accepted, waiting for user confirmation', ['order' => $order]);
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to accept order', $e->getMessage(), 500);
        }
    }

    // Reject order (driver refuses)
    public function rejectOrder($order_id)
    {
        try {
            $driver = JWTAuth::parseToken()->authenticate();
            $order = Order::where('order_id', $order_id)
                          ->where('order_status', 'pending')
                          ->first();

            if (!$order) {
                return ApiResponse::error('Order not available for rejection', null, 404);
            }

            $order->update([
                'driver_id' => $driver->driver_id,
                'order_status' => 'rejected',
            ]);

            return ApiResponse::success('Order rejected by driver', ['order' => $order]);
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to reject order', $e->getMessage(), 500);
        }
    }

    // Start delivery (on_the_way)
    public function startDelivery($order_id)
    {
        try {
            $driver = JWTAuth::parseToken()->authenticate();
            $order = Order::where('order_id', $order_id)
                          ->where('driver_id', $driver->driver_id)
                          ->where('order_status', 'accepted')
                          ->first();

            if (!$order) {
                return ApiResponse::error('Order not ready for delivery', null, 404);
            }

            $order->update(['order_status' => 'on_the_way']);
            return ApiResponse::success('Order is now on the way', ['order' => $order]);
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to start delivery', $e->getMessage(), 500);
        }
    }

    // Complete order
    public function completeOrder($order_id)
    {
        try {
            $driver = JWTAuth::parseToken()->authenticate();
            $order = Order::where('order_id', $order_id)
                          ->where('driver_id', $driver->driver_id)
                          ->where('order_status', 'on_the_way')
                          ->first();

            if (!$order) {
                return ApiResponse::error('Order not ready to complete', null, 404);
            }

            $order->update(['order_status' => 'completed']);
            return ApiResponse::success('Order marked as completed', ['order' => $order]);
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to complete order', $e->getMessage(), 500);
        }
    }

    // My orders (driver)
    public function myOrders()
    {
        try {
            $driver = JWTAuth::parseToken()->authenticate();
            $orders = Order::with('items.product', 'address')
                           ->where('driver_id', $driver->driver_id)
                           ->get();

            return ApiResponse::success('Driver orders retrieved successfully', ['orders' => $orders]);
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to retrieve orders', $e->getMessage(), 500);
        }
    }
}


/*

pending → الطلب جديد، لم يقبله أي سائق بعد

accepted → السائق قبل الطلب ولكنه يحتاج الاتصال بالمستخدم للتأكد منه

rejected → السائق رفض الطلب قبل التوصيل لأن المستخدم لم يرد أو لم يكن حقيقي

on_the_way → الطلب قيد التوصيل

completed → تم تسليم الطلب بنجاح

cancelled → المستخدم ألغى الطلب قبل التسليم                     

*/