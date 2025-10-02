<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Http\Request;
use Tymon\JWTAuth\Facades\JWTAuth;
use App\Http\Responses\ApiResponse;

class DriverController extends Controller
{
    /**
     * Accept order (sets status accepted)
     */
    public function acceptOrder($order_id)
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();
            $driver = $user->driver;

            if (!$driver) {
                return ApiResponse::error('Driver profile not found', null, 404);
            }

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

    /**
     * Reject order
     */
    public function rejectOrder($order_id)
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();
            $driver = $user->driver;

            if (!$driver) {
                return ApiResponse::error('Driver profile not found', null, 404);
            }

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

    /**
     * Start delivery
     */
    public function startDelivery($order_id)
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();
            $driver = $user->driver;

            if (!$driver) {
                return ApiResponse::error('Driver profile not found', null, 404);
            }

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

    /**
     * Complete order
     */
    public function completeOrder($order_id)
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();
            $driver = $user->driver;

            if (!$driver) {
                return ApiResponse::error('Driver profile not found', null, 404);
            }

            $order = Order::where('order_id', $order_id)
                          ->where('driver_id', $driver->driver_id)
                          ->where('order_status', 'on_the_way')
                          ->first();

            if (!$order) {
                return ApiResponse::error('Order not ready to complete', null, 404);
            }

            $order->update(['order_status' => 'completed',"payment_status" => "paid"]);
            return ApiResponse::success('Order marked as completed', ['order' => $order]);
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to complete order', $e->getMessage(), 500);
        }
    }

    /**
     * Get orders for this driver
     */
    public function myOrders()
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();
            $driver = $user->driver;

            if (!$driver) {
                return ApiResponse::error('Driver profile not found', null, 404);
            }

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