<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\DeliveryFee;
use Illuminate\Http\Request;
use App\Http\Responses\ApiResponse;
use Tymon\JWTAuth\Facades\JWTAuth;
use Exception;

class OrderController extends Controller
{
    // إضافة طلب جديد
    public function store(Request $request)
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();
            $customer = $user->customer;

            if (!$customer) {
                return ApiResponse::error('Customer record not found', null, 404);
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

            $total_amount = 0;
            foreach ($request->items as $item) {
                $product = Product::find($item['product_id']);
                if (!$product) continue;
                $total_amount += $product->price * $item['quantity'];
            }

            $lastFee = DeliveryFee::latest('updated_at')->first();
            $delivery_fee = $lastFee ? $lastFee->fee : 0;

            $order = Order::create([
                'customer_id' => $customer->customer_id,
                'address_id' => $request->address_id,
                'total_amount' => $total_amount,
                'delivery_fee' => $delivery_fee,
                'order_status' => 'pending',
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

            return ApiResponse::success('Order created successfully', ['order' => $order], 201);
        } catch (Exception $e) {
            return ApiResponse::error('Failed to create order', $e->getMessage(), 500);
        }
    }

    // إلغاء طلب
    public function cancel($order_id)
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();
            $customer = $user->customer;

            $order = Order::where('order_id', $order_id)
                          ->where('customer_id', $customer->customer_id)
                          ->first();

            if (!$order) {
                return ApiResponse::error('Order not found', null, 404);
            }

            if ($order->order_status !== 'pending') {
                return ApiResponse::error('Cannot cancel this order', null, 400);
            }

            $order->update(['order_status' => 'cancelled']);
            return ApiResponse::success('Order cancelled successfully');
        } catch (Exception $e) {
            return ApiResponse::error('Failed to cancel order', $e->getMessage(), 500);
        }
    }

    // عرض طلبات العميل
   public function myOrders()
{
    try {
        $user = JWTAuth::parseToken()->authenticate();
        $customer = $user->customer;

        $orders = Order::with('items.product', 'address')
                       ->where('customer_id', $customer->customer_id)
                       ->orderBy('order_date', 'desc') 
                       ->get();

        return ApiResponse::success('Orders retrieved successfully', ['orders' => $orders]);
    } catch (Exception $e) {
        return ApiResponse::error('Failed to retrieve orders', $e->getMessage(), 500);
    }
}


    // إضافة تقييم و مراجعة للطلب بعد اكتماله
    public function addReview(Request $request, $order_id)
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();
            $customer = $user->customer;

            $order = Order::where('order_id', $order_id)
                          ->where('customer_id', $customer->customer_id)
                          ->first();

            if (!$order) {
                return ApiResponse::error('Order not found', null, 404);
            }

            if ($order->order_status !== 'completed') {
                return ApiResponse::error('You can only review completed orders', null, 400);
            }

            $request->validate([
                'rating' => 'required|integer|min:1|max:5',
                'review' => 'nullable|string|max:1000',
            ]);

            $order->update([
                'rating' => $request->rating,
                'review' => $request->review,
            ]);

            return ApiResponse::success('Review added successfully', ['order' => $order]);
        } catch (Exception $e) {
            return ApiResponse::error('Failed to add review', $e->getMessage(), 500);
        }
    }

    // عرض كل الطلبات (للمسؤول أو المستخدم)
 public function index(Request $request)
{
    try {
        $query = Order::with('items.product', 'address', 'customer');

        // ✅ فلترة اختيارية حسب الحالة
        if ($request->has('status') && !empty($request->status)) {
            $query->where('order_status', $request->status);
        }

        $orders = $query->orderBy('order_date', 'desc')->get();

        return ApiResponse::success('Orders retrieved successfully', ['orders' => $orders]);
    } catch (Exception $e) {
        return ApiResponse::error('Failed to retrieve orders', $e->getMessage(), 500);
    }
}

    // عرض طلب محدد
    public function show($order_id)
    {
        try {
            $order = Order::with('items.product', 'address', 'customer')
                          ->where('order_id', $order_id)
                          ->first();

            if (!$order) {
                return ApiResponse::error('Order not found', null, 404);
            }

            return ApiResponse::success('Order retrieved successfully', ['order' => $order]);
        } catch (Exception $e) {
            return ApiResponse::error('Failed to retrieve order', $e->getMessage(), 500);
        }
    }
}
