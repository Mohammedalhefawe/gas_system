<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\UserAddress;
use App\Models\Provider;
use App\Models\Sector;
use App\Services\GeoService;
use App\Http\Controllers\NotificationController;
use Illuminate\Http\Request;
use App\Http\Responses\ApiResponse;
use Tymon\JWTAuth\Facades\JWTAuth;
use Exception;

class OrderController extends Controller
{
    protected $notificationController;
    protected $geoService;


    public function __construct(NotificationController $notificationController, GeoService $geoService)
    {
        $this->notificationController = $notificationController;
        $this->geoService = $geoService;
    }


    public function store(Request $request)
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();
            $customer = $user->customer;

            if (!$customer) {
                return ApiResponse::error(__('messages.customer_not_found'), null, 404);
            }

            if ($customer->blocked) {
                return ApiResponse::error(__('messages.customer_blocked'), null, 403);
            }

            $request->validate([
                'items' => 'required|array|min:1',
                'items.*.product_id' => 'required|exists:products,product_id',
                'items.*.quantity' => 'required|integer|min:1',
                'address_id' => 'required|exists:user_addresses,address_id',
                'payment_method' => 'required|string',
                'immediate' => 'nullable|boolean',
                'delivery_date' => 'required_if:immediate,false|nullable|date|after_or_equal:today',
                'delivery_time' => 'required_if:immediate,false|nullable|date_format:H:i',
                'note' => 'nullable|string|max:500',
            ]);

            $address = UserAddress::find($request->address_id);
            if (!$address) {
                return ApiResponse::error(__('messages.address_not_found'), null, 404);
            }

            $lat = $address->latitude;
            $lng = $address->longitude;

            $sector = Sector::where('is_active', true)->get()
                ->filter(fn($s) => $s->polygon && app(GeoService::class)->pointInPolygon($lat, $lng, $s->polygon))
                ->first();

            if (!$sector) {
                return ApiResponse::error(__('messages.service_not_available_in_region'), null, 404);
            }

            $total_amount = 0;
            foreach ($request->items as $item) {
                $product = Product::find($item['product_id']);
                if (!$product) continue;
                $total_amount += $product->price * $item['quantity'];
            }

            $delivery_fee = $sector->delivery_fee ?? 0;

            // الحالة الابتدائية للطلب: pending_provider
            $order = Order::create([
                'customer_id' => $customer->customer_id,
                'address_id' => $address->address_id,
                'sector_id' => $sector->sector_id,
                'total_amount' => $total_amount,
                'delivery_fee' => $delivery_fee,
                'order_status' => 'pending_provider',
                'payment_method' => $request->payment_method,
                'payment_status' => 'pending',
                'delivery_date' => $request->immediate ? null : $request->delivery_date,
                'delivery_time' => $request->immediate ? null : $request->delivery_time,
                'note' => $request->note ?? null,
                'immediate' => $request->immediate ?? false,
            ]);

            foreach ($request->items as $item) {
                $product = Product::find($item['product_id']);
                if (!$product) continue;

                OrderItem::create([
                    'order_id' => $order->order_id,
                    'product_id' => $product->product_id,
                    'quantity' => $item['quantity'],
                    'unit_price' => $product->price,
                    'subtotal' => $product->price * $item['quantity'],
                ]);
            }

            // إرسال إشعار للمستخدم (العميل)
            $this->notificationController->sendNotification(
                [$user->user_id],
                "Order Placed",
                "You have placed an order #$order->order_id successfully",
                [
                    'order_id' => $order->order_id,
                    'payload_route' => "/order/{$order->order_id}",
                    'title_ar' => 'تم تقديم الطلب',
                    'body_ar' => "تم تقديم طلبك رقم #{$order->order_id} بنجاح",
                ],
                'order_status',
                $order->order_id
            );

            // إرسال إشعار لجميع المزودين المتاحين في نفس القطاع
            $availableProviders = Provider::where('sector_id', $sector->sector_id)
                ->where('is_available', true)
                ->pluck('user_id')
                ->toArray();

            if (!empty($availableProviders)) {
                $this->notificationController->sendNotification(
                    $availableProviders,
                    "New Order",
                    "Order #{$order->order_id} is available for acceptance in your sector",
                    [
                        'order_id' => $order->order_id,
                        'payload_route' => "/orders/{$order->order_id}",
                        'title_ar' => 'طلب جديد',
                        'body_ar' => "هناك طلب جديد رقم #{$order->order_id} متاح في قطاعك",
                    ],
                    'order_status',
                    $order->order_id,
                    'providers'
                );
            }

            return ApiResponse::success(__('messages.order_created'), ['order' => $order], 201);
        } catch (\Exception $e) {
            return ApiResponse::error(__('messages.failed_to_create_order'), $e->getMessage(), 500);
        }
    }


    public function cancel($order_id)
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();
            $customer = $user->customer;

            $order = Order::where('order_id', $order_id)
                ->where('customer_id', $customer->customer_id)
                ->first();

            if (!$order) {
                return ApiResponse::error(__('messages.order_not_found'), null, 404);
            }

            if ($order->order_status !== 'pending_provider' || $order->order_status !== 'pending_driver') {
                return ApiResponse::error(__('messages.cannot_cancel_order'), null, 400);
            }

            $order->update(['order_status' => 'cancelled']);

            if ($order->driver_id && $order->driver) {
                $this->notificationController->sendNotification(
                    [], // Empty user_ids for topic-based notification
                    "Order Cancelled",
                    "Order #$order->order_id has been cancelled by the customer.",
                    [
                        'order_id' => $order->order_id,
                        'payload_route' => "/order/{$order->order_id}",
                        'title_ar' => 'تم إلغاء الطلب',
                        'body_ar' => "تم إلغاء الطلب رقم #{$order->order_id} من قبل العميل",
                    ],
                    'order_status',
                    $order->order_id,
                    'drivers'
                );
            }

            return ApiResponse::success(__('messages.order_cancelled'));
        } catch (Exception $e) {
            return ApiResponse::error(__('messages.failed_to_cancel_order'), $e->getMessage(), 500);
        }
    }
    public function myOrders(Request $request)
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();
            $customer = $user->customer;

            if (!$customer) {
                return ApiResponse::error(__('messages.customer_not_found'), null, 404);
            }

            $query = Order::with('items.product', 'address')
                ->where('customer_id', $customer->customer_id);

            $perPage = $request->get('per_page', 10);
            $orders = $query->orderBy('order_date', 'desc')->paginate($perPage);

            return ApiResponse::success(__('messages.orders_retrieved'), [
                'items' => $orders->items(),
                'current_page' => $orders->currentPage(),
                'last_page' => $orders->lastPage(),
                'per_page' => $orders->perPage(),
                'total' => $orders->total(),
            ]);
        } catch (\Exception $e) {
            return ApiResponse::error(__('messages.failed_to_retrieve_orders'), $e->getMessage(), 500);
        }
    }

    public function getOrdersByCustomer(Request $request, $customer_id)
    {
        try {
            $query = Order::with('items.product', 'address', 'customer.user')
                ->where('customer_id', $customer_id);

            $perPage = $request->get('per_page', 10);
            $orders = $query->orderBy('order_date', 'desc')->paginate($perPage);



            return ApiResponse::success(__('messages.orders_retrieved'), [
                'items'        => $orders->items(),
                'current_page' => $orders->currentPage(),
                'last_page'    => $orders->lastPage(),
                'per_page'     => $orders->perPage(),
                'total'        => $orders->total(),
            ]);
        } catch (\Exception $e) {
            return ApiResponse::error(__('messages.failed_to_retrieve_orders'), $e->getMessage(), 500);
        }
    }

    public function addReview(Request $request, $order_id)
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();
            $customer = $user->customer;

            $order = Order::where('order_id', $order_id)
                ->where('customer_id', $customer->customer_id)
                ->first();

            if (!$order) {
                return ApiResponse::error(__('messages.order_not_found'), null, 404);
            }

            if ($order->order_status !== 'completed') {
                return ApiResponse::error(__('messages.cannot_review_order'), null, 400);
            }

            $request->validate([
                'rating' => 'required|integer|min:1|max:5',
                'review' => 'nullable|string|max:1000',
            ]);

            $order->update([
                'rating' => $request->rating,
                'review' => $request->review,
            ]);

            return ApiResponse::success(__('messages.review_added'), ['order' => $order]);
        } catch (Exception $e) {
            return ApiResponse::error(__('messages.failed_to_add_review'), $e->getMessage(), 500);
        }
    }

    public function index(Request $request)
    {
        try {
            $query = Order::with('items.product', 'address', 'customer.user');

            // فلترة حسب الحالة إذا موجودة
            if ($request->has('status') && !empty($request->status)) {
                $query->where('order_status', $request->status);
            }

            $perPage = $request->get('per_page', 10);
            $orders = $query->orderBy('order_date', 'desc')->paginate($perPage);

            return ApiResponse::success(__('messages.orders_retrieved'), [
                'items' => $orders->items(),
                'current_page' => $orders->currentPage(),
                'last_page' => $orders->lastPage(),
                'per_page' => $orders->perPage(),
                'total' => $orders->total(),
            ]);
        } catch (\Exception $e) {
            return ApiResponse::error(__('messages.failed_to_retrieve_orders'), $e->getMessage(), 500);
        }
    }

    public function show($order_id)
    {
        try {
            $order = Order::with('items.product', 'address', 'customer.user')
                ->where('order_id', $order_id)
                ->first();

            if (!$order) {
                return ApiResponse::error(__('messages.order_not_found'), null, 404);
            }

            return ApiResponse::success(__('messages.orders_retrieved'), ['order' => $order]);
        } catch (Exception $e) {
            return ApiResponse::error(__('messages.failed_to_retrieve_orders'), $e->getMessage(), 500);
        }
    }
}
