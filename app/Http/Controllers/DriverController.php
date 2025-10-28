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

            // Send notification to the customer
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
                $order->order_id,
            );

            return ApiResponse::success(__('messages.order_accepted'), ['order' => $order]);
        } catch (\Exception $e) {
            return ApiResponse::error(__('messages.failed_accept_order'), $e->getMessage(), 500);
        }
    }

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

            // Send notification to the customer
            $this->notificationController->sendNotification(
                [$order->customer->user->user_id],
                "Order Rejected",
                "Your order #$order->order_id has been rejected by a driver.",
                [
                    'order_id' => $order->order_id,
                    'payload_route' => "/order/{$order->order_id}",
                    'title_ar' => 'تم رفض الطلب',
                    'body_ar' => "تم رفض طلبك رقم #{$order->order_id} من قبل السائق",
                ],
                'order_status',
                $order->order_id,
            );

            return ApiResponse::success(__('messages.order_rejected'), ['order' => $order]);
        } catch (\Exception $e) {
            return ApiResponse::error(__('messages.failed_reject_order'), $e->getMessage(), 500);
        }
    }

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

            // Send notification to the customer
            $this->notificationController->sendNotification(
                [$order->customer->user->user_id],
                "Order On The Way",
                "Your order #$order->order_id is on the way.",
                [
                    'order_id' => $order->order_id,
                    'payload_route' => "/order/{$order->order_id}",
                    'title_ar' => 'الطلب في الطريق',
                    'body_ar' => "طلبك رقم #{$order->order_id} في الطريق إليك",
                ],
                'order_status',
                $order->order_id,
            );

            return ApiResponse::success(__('messages.order_on_the_way'), ['order' => $order]);
        } catch (\Exception $e) {
            return ApiResponse::error(__('messages.failed_start_delivery'), $e->getMessage(), 500);
        }
    }

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

            // Send notification to the customer
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
                $order->order_id,
            );

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



    // // قبول طلب
    // public function acceptOrder($order_id)
    // {
    //     try {
    //         $user = JWTAuth::parseToken()->authenticate();
    //         $driver = $user->driver;

    //         if (!$driver) {
    //             return ApiResponse::error(__('messages.driver_not_found'), null, 404);
    //         }

    //         if ($driver->blocked) {
    //             return ApiResponse::error(__('messages.driver_blocked'), null, 403);
    //         }

    //         $existingOrder = Order::where('driver_id', $driver->driver_id)
    //             ->whereIn('order_status', ['accepted', 'on_the_way'])
    //             ->first();

    //         if ($existingOrder) {
    //             return ApiResponse::error(__('messages.driver_has_ongoing_order'), null, 400);
    //         }

    //         $order = Order::where('order_id', $order_id)
    //             ->where('order_status', 'pending')
    //             ->first();

    //         if (!$order) {
    //             return ApiResponse::error(__('messages.order_not_available'), null, 404);
    //         }

    //         $order->update([
    //             'driver_id' => $driver->driver_id,
    //             'order_status' => 'accepted',
    //         ]);

    //         return ApiResponse::success(__('messages.order_accepted'), ['order' => $order]);
    //     } catch (\Exception $e) {
    //         return ApiResponse::error(__('messages.failed_accept_order'), $e->getMessage(), 500);
    //     }
    // }

    // // رفض طلب
    // public function rejectOrder($order_id)
    // {
    //     try {
    //         $user = JWTAuth::parseToken()->authenticate();
    //         $driver = $user->driver;

    //         if (!$driver) {
    //             return ApiResponse::error(__('messages.driver_not_found'), null, 404);
    //         }

    //         $order = Order::where('order_id', $order_id)
    //             ->where('order_status', 'accepted')
    //             ->first();

    //         if (!$order) {
    //             return ApiResponse::error(__('messages.order_not_available'), null, 404);
    //         }

    //         $order->update([
    //             'driver_id' => $driver->driver_id,
    //             'order_status' => 'rejected',
    //         ]);

    //         return ApiResponse::success(__('messages.order_rejected'), ['order' => $order]);
    //     } catch (\Exception $e) {
    //         return ApiResponse::error(__('messages.failed_reject_order'), $e->getMessage(), 500);
    //     }
    // }

    // // بدء التوصيل
    // public function startDelivery($order_id)
    // {
    //     try {
    //         $user = JWTAuth::parseToken()->authenticate();
    //         $driver = $user->driver;

    //         if (!$driver) {
    //             return ApiResponse::error(__('messages.driver_not_found'), null, 404);
    //         }

    //         $order = Order::where('order_id', $order_id)
    //             ->where('driver_id', $driver->driver_id)
    //             ->where('order_status', 'accepted')
    //             ->first();

    //         if (!$order) {
    //             return ApiResponse::error(__('messages.order_not_ready_delivery'), null, 404);
    //         }

    //         $order->update(['order_status' => 'on_the_way']);
    //         return ApiResponse::success(__('messages.order_on_the_way'), ['order' => $order]);
    //     } catch (\Exception $e) {
    //         return ApiResponse::error(__('messages.failed_start_delivery'), $e->getMessage(), 500);
    //     }
    // }

    // // إكمال الطلب
    // public function completeOrder($order_id)
    // {
    //     try {
    //         $user = JWTAuth::parseToken()->authenticate();
    //         $driver = $user->driver;

    //         if (!$driver) {
    //             return ApiResponse::error(__('messages.driver_not_found'), null, 404);
    //         }

    //         $order = Order::where('order_id', $order_id)
    //             ->where('driver_id', $driver->driver_id)
    //             ->where('order_status', 'on_the_way')
    //             ->first();

    //         if (!$order) {
    //             return ApiResponse::error(__('messages.order_not_ready_complete'), null, 404);
    //         }

    //         $order->update(['order_status' => 'completed', 'payment_status' => 'paid']);
    //         return ApiResponse::success(__('messages.order_completed'), ['order' => $order]);
    //     } catch (\Exception $e) {
    //         return ApiResponse::error(__('messages.failed_complete_order'), $e->getMessage(), 500);
    //     }
    // }
