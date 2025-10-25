<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Http\Request;
use Tymon\JWTAuth\Facades\JWTAuth;
use App\Http\Responses\ApiResponse;
use App\Models\Driver;

class DriverController extends Controller
{
    // قبول طلب
    public function acceptOrder($order_id)
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();
            $driver = $user->driver;

            if (!$driver) {
                return ApiResponse::error(__('messages.driver_not_found'), null, 404);
            }

            if ($driver->blocked) {
                return ApiResponse::error(__('messages.driver_blocked'), null, 403);
            }

            $existingOrder = Order::where('driver_id', $driver->driver_id)
                ->whereIn('order_status', ['accepted', 'on_the_way'])
                ->first();

            if ($existingOrder) {
                return ApiResponse::error(__('messages.driver_has_ongoing_order'), null, 400);
            }

            $order = Order::where('order_id', $order_id)
                ->where('order_status', 'pending')
                ->first();

            if (!$order) {
                return ApiResponse::error(__('messages.order_not_available'), null, 404);
            }

            $order->update([
                'driver_id' => $driver->driver_id,
                'order_status' => 'accepted',
            ]);

            return ApiResponse::success(__('messages.order_accepted'), ['order' => $order]);
        } catch (\Exception $e) {
            return ApiResponse::error(__('messages.failed_accept_order'), $e->getMessage(), 500);
        }
    }

    // رفض طلب
    public function rejectOrder($order_id)
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();
            $driver = $user->driver;

            if (!$driver) {
                return ApiResponse::error(__('messages.driver_not_found'), null, 404);
            }

            $order = Order::where('order_id', $order_id)
                ->where('order_status', 'accepted')
                ->first();

            if (!$order) {
                return ApiResponse::error(__('messages.order_not_available'), null, 404);
            }

            $order->update([
                'driver_id' => $driver->driver_id,
                'order_status' => 'rejected',
            ]);

            return ApiResponse::success(__('messages.order_rejected'), ['order' => $order]);
        } catch (\Exception $e) {
            return ApiResponse::error(__('messages.failed_reject_order'), $e->getMessage(), 500);
        }
    }

    // بدء التوصيل
    public function startDelivery($order_id)
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();
            $driver = $user->driver;

            if (!$driver) {
                return ApiResponse::error(__('messages.driver_not_found'), null, 404);
            }

            $order = Order::where('order_id', $order_id)
                ->where('driver_id', $driver->driver_id)
                ->where('order_status', 'accepted')
                ->first();

            if (!$order) {
                return ApiResponse::error(__('messages.order_not_ready_delivery'), null, 404);
            }

            $order->update(['order_status' => 'on_the_way']);
            return ApiResponse::success(__('messages.order_on_the_way'), ['order' => $order]);
        } catch (\Exception $e) {
            return ApiResponse::error(__('messages.failed_start_delivery'), $e->getMessage(), 500);
        }
    }

    // إكمال الطلب
    public function completeOrder($order_id)
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();
            $driver = $user->driver;

            if (!$driver) {
                return ApiResponse::error(__('messages.driver_not_found'), null, 404);
            }

            $order = Order::where('order_id', $order_id)
                ->where('driver_id', $driver->driver_id)
                ->where('order_status', 'on_the_way')
                ->first();

            if (!$order) {
                return ApiResponse::error(__('messages.order_not_ready_complete'), null, 404);
            }

            $order->update(['order_status' => 'completed', 'payment_status' => 'paid']);
            return ApiResponse::success(__('messages.order_completed'), ['order' => $order]);
        } catch (\Exception $e) {
            return ApiResponse::error(__('messages.failed_complete_order'), $e->getMessage(), 500);
        }
    }

    // طلبات السائق
    public function myOrders()
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();
            $driver = $user->driver;

            if (!$driver) {
                return ApiResponse::error(__('messages.driver_not_found'), null, 404);
            }

            $orders = Order::with('items.product', 'address', 'customer.user')
                ->where('driver_id', $driver->driver_id)
                ->orderBy('order_date', 'desc')
                ->get();

            return ApiResponse::success(__('messages.driver_orders_retrieved'), ['orders' => $orders]);
        } catch (\Exception $e) {
            return ApiResponse::error(__('messages.failed_retrieve_driver_orders'), $e->getMessage(), 500);
        }
    }

    // طلبات السائق للمسؤول
    public function getOrdersByDriverForAdmin($driver_id)
    {
        try {
            $orders = Order::with('items.product', 'address', 'customer.user')
                ->where('driver_id', $driver_id)
                ->orderBy('order_date', 'desc')
                ->get();

            return ApiResponse::success(__('messages.driver_orders_retrieved'), [
                'driver_id' => $driver_id,
                'orders' => $orders
            ]);
        } catch (\Exception $e) {
            return ApiResponse::error(__('messages.failed_retrieve_driver_orders'), $e->getMessage(), 500);
        }
    }

    // حظر/رفع الحظر عن السائق
    public function toggleBlockDriver($driver_id)
    {
        $driver = Driver::find($driver_id);

        if (!$driver) {
            return ApiResponse::error(__('messages.driver_not_found'), null, 404);
        }

        $driver->blocked = !$driver->blocked;
        $driver->save();

        return ApiResponse::success(
            $driver->blocked ? __('messages.driver_blocked') : __('messages.driver_unblocked'),
            ['driver' => $driver]
        );
    }

    // عرض كل السائقين
    public function getAllDrivers()
    {
        try {
            $drivers = Driver::with('user')
                ->orderBy('driver_id', 'desc')
                ->get();

            return ApiResponse::success(__('messages.drivers_retrieved'), [
                'drivers' => $drivers
            ]);
        } catch (\Exception $e) {
            return ApiResponse::error(__('messages.failed_retrieve_drivers'), $e->getMessage(), 500);
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