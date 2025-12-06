<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Http\Request;
use Tymon\JWTAuth\Facades\JWTAuth;
use App\Http\Responses\ApiResponse;
use App\Models\Driver;
use App\Http\Controllers\NotificationController;

class DriverController extends Controller
{


    protected $notificationController;

    public function __construct(NotificationController $notificationController)
    {
        $this->notificationController = $notificationController;
    }

    // قبول الطلب من قبل السائق
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

            // التأكد من أن السائق لا يحمل طلب قيد التنفيذ
            $existingOrder = Order::where('driver_id', $driver->driver_id)
                ->whereIn('order_status', ['accepted', 'on_the_way_provider', 'on_the_way_customer'])
                ->first();

            if ($existingOrder) {
                return ApiResponse::error(__('messages.driver_has_ongoing_order'), null, 400);
            }

            $order = Order::where('order_id', $order_id)
                ->where('order_status', 'pending_driver')
                ->first();

            if (!$order) {
                return ApiResponse::error(__('messages.order_not_available'), null, 404);
            }

            // التحقق من أن السائق في نفس القطاع
            if ($driver->sector_id !== $order->sector_id) {
                return ApiResponse::error(__('messages.driver_wrong_sector'), null, 400);
            }

            $order->update([
                'driver_id' => $driver->driver_id,
                'order_status' => 'accepted',
            ]);

            // إشعار العميل أن السائق قبل الطلب
            $this->notificationController->sendNotification(
                [$order->customer->user->user_id],
                "Order Accepted",
                "Your order #$order->order_id has been accepted by a driver.",
                [
                    'order_id' => $order->order_id,
                    'payload_route' => "/order/{$order->order_id}",
                    'title_ar' => 'تم قبول الطلب',
                    'body_ar' => "تم قبول طلبك رقم #{$order->order_id} من قبل السائق",
                ],
                'order_status',
                $order->order_id
            );

            return ApiResponse::success(__('messages.order_accepted'), ['order' => $order]);
        } catch (\Exception $e) {
            return ApiResponse::error(__('messages.failed_accept_order'), $e->getMessage(), 500);
        }
    }

    // رفض الطلب من قبل السائق
    public function rejectOrder($order_id)
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();
            $driver = $user->driver;

            if (!$driver) {
                return ApiResponse::error(__('messages.driver_not_found'), null, 404);
            }

            $order = Order::where('order_id', $order_id)
                ->where('order_status', 'pending_driver')
                ->first();

            if (!$order) {
                return ApiResponse::error(__('messages.order_not_available'), null, 404);
            }

            $order->update([
                'driver_id' => null, // السماح لسائق آخر بأخذ الطلب
                'order_status' => 'pending_driver',
            ]);

            // إشعار العميل أن السائق رفض الطلب
            $this->notificationController->sendNotification(
                [$order->customer->user->user_id],
                "Order Rejected",
                "A driver rejected your order #$order->order_id.",
                [
                    'order_id' => $order->order_id,
                    'payload_route' => "/order/{$order->order_id}",
                    'title_ar' => 'تم رفض الطلب',
                    'body_ar' => "تم رفض طلبك رقم #{$order->order_id} من قبل السائق",
                ],
                'order_status',
                $order->order_id
            );

            return ApiResponse::success(__('messages.order_rejected'), ['order' => $order]);
        } catch (\Exception $e) {
            return ApiResponse::error(__('messages.failed_reject_order'), $e->getMessage(), 500);
        }
    }

    // بدء التوصيل من السائق إلى المزود
    public function startDeliveryToProvider($order_id)
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

            $order->update(['order_status' => 'on_the_way_provider']);

            $this->notificationController->sendNotification(
                [$order->customer->user->user_id],
                "Order On The Way to Provider",
                "Your order #$order->order_id is on the way to the provider.",
                [
                    'order_id' => $order->order_id,
                    'payload_route' => "/order/{$order->order_id}",
                    'title_ar' => 'الطلب في الطريق إلى المزود',
                    'body_ar' => "طلبك رقم #{$order->order_id} في الطريق إلى المزود",
                ],
                'order_status',
                $order->order_id
            );

            return ApiResponse::success(__('messages.order_on_the_way_provider'), ['order' => $order]);
        } catch (\Exception $e) {
            return ApiResponse::error(__('messages.failed_start_delivery'), $e->getMessage(), 500);
        }
    }

    // بدء التوصيل من المزود إلى العميل
    public function startDeliveryToCustomer($order_id)
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();
            $driver = $user->driver;

            if (!$driver) {
                return ApiResponse::error(__('messages.driver_not_found'), null, 404);
            }

            $order = Order::where('order_id', $order_id)
                ->where('driver_id', $driver->driver_id)
                ->where('order_status', 'on_the_way_provider')
                ->first();

            if (!$order) {
                return ApiResponse::error(__('messages.order_not_ready_delivery'), null, 404);
            }

            $order->update(['order_status' => 'on_the_way_customer']);

            $this->notificationController->sendNotification(
                [$order->customer->user->user_id],
                "Order On The Way",
                "Your order #$order->order_id is on the way to you.",
                [
                    'order_id' => $order->order_id,
                    'payload_route' => "/order/{$order->order_id}",
                    'title_ar' => 'الطلب في الطريق إليك',
                    'body_ar' => "طلبك رقم #{$order->order_id} في الطريق إليك",
                ],
                'order_status',
                $order->order_id
            );

            return ApiResponse::success(__('messages.order_on_the_way_customer'), ['order' => $order]);
        } catch (\Exception $e) {
            return ApiResponse::error(__('messages.failed_start_delivery'), $e->getMessage(), 500);
        }
    }

    // إكمال الطلب وتسليمه للعميل
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
                ->where('order_status', 'on_the_way_customer')
                ->first();

            if (!$order) {
                return ApiResponse::error(__('messages.order_not_ready_complete'), null, 404);
            }

            $order->update([
                'order_status' => 'completed',
                'payment_status' => 'paid'
            ]);

            $this->notificationController->sendNotification(
                [$order->customer->user->user_id],
                "Order Completed",
                "Your order #$order->order_id has been completed.",
                [
                    'order_id' => $order->order_id,
                    'payload_route' => "/order/{$order->order_id}",
                    'title_ar' => 'تم إكمال الطلب',
                    'body_ar' => "تم إكمال طلبك رقم #{$order->order_id} بنجاح",
                ],
                'order_status',
                $order->order_id
            );

            return ApiResponse::success(__('messages.order_completed'), ['order' => $order]);
        } catch (\Exception $e) {
            return ApiResponse::error(__('messages.failed_complete_order'), $e->getMessage(), 500);
        }
    }
    // طلبات السائق
    public function myOrders(Request $request)
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();
            $driver = $user->driver;

            if (!$driver) {
                return ApiResponse::error(__('messages.driver_not_found'), null, 404);
            }

            $query = Order::with('items.product', 'address', 'customer.user')
                ->where('driver_id', $driver->driver_id);

            $perPage = $request->get('per_page', 10);
            $orders = $query->orderBy('order_date', 'desc')->paginate($perPage);

            return ApiResponse::success(__('messages.driver_orders_retrieved'), [
                'items' => $orders->items(),
                'current_page' => $orders->currentPage(),
                'last_page' => $orders->lastPage(),
                'per_page' => $orders->perPage(),
                'total' => $orders->total(),
            ]);
        } catch (\Exception $e) {
            return ApiResponse::error(__('messages.failed_retrieve_driver_orders'), $e->getMessage(), 500);
        }
    }


    // طلبات السائق للمسؤول
    public function getOrdersByDriverForAdmin(Request $request, $driver_id)
    {
        try {
            $perPage = $request->get('per_page', 10);

            $orders = Order::with('items.product', 'address', 'customer.user')
                ->where('driver_id', $driver_id)
                ->orderBy('order_date', 'desc')
                ->paginate($perPage);

            $data = [
                'driver_id' => $driver_id,
                'items' => $orders->items(),
                'current_page' => $orders->currentPage(),
                'last_page' => $orders->lastPage(),
                'per_page' => $orders->perPage(),
                'total' => $orders->total(),
            ];

            return ApiResponse::success(__('messages.driver_orders_retrieved'), $data);
        } catch (\Exception $e) {
            return ApiResponse::error(
                __('messages.failed_retrieve_driver_orders'),
                $e->getMessage(),
                500
            );
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
    public function getAllDrivers(Request $request)
    {
        try {
            // استلام عدد العناصر في الصفحة (افتراضي 10)
            $perPage = $request->get('per_page', 10);

            // جلب السائقين مع المستخدمين المرتبطين
            $drivers = Driver::with('user')
                ->orderBy('driver_id', 'desc')
                ->paginate($perPage);

            // تجهيز شكل البيانات المتوافق مع PaginatedModel في Flutter
            $data = [
                'items' => $drivers->items(),
                'current_page' => $drivers->currentPage(),
                'last_page' => $drivers->lastPage(),
                'per_page' => $drivers->perPage(),
                'total' => $drivers->total(),
            ];

            return ApiResponse::success(__('messages.drivers_retrieved'), $data);
        } catch (\Exception $e) {
            return ApiResponse::error(
                __('messages.failed_retrieve_drivers'),
                $e->getMessage(),
                500
            );
        }
    }
}



/*

pending_provider → الطلب جديد، لم يقبله أي مزود بعد

pending_driver → الطلب جديد، لم يقبله أي سائق بعد

accepted → السائق قبل الطلب ولكنه يحتاج الاتصال بالمستخدم للتأكد منه

rejected → السائق رفض الطلب قبل التوصيل لأن المستخدم لم يرد أو لم يكن حقيقي

on_the_way_provider →  جاري جلب الطلب من المزود

on_the_way_customer →  الطلب في الطريق للمتسخدم

completed → تم تسليم الطلب بنجاح

cancelled → (accepted) المستخدم ألغى الطلب قبل ان تصبح الحالة                    

*/


