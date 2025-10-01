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
    // Add a new order
    public function store(Request $request)
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();
            if (!$user) {
                return ApiResponse::error('Unauthenticated', null, 401);
            }

            $request->validate([
                'items' => 'required|array|min:1',
                'items.*.product_id' => 'required|exists:products,product_id',
                'items.*.quantity' => 'required|integer|min:1',
                'address_id' => 'required|exists:user_addresses,address_id',
                'payment_method' => 'required|string',
                'delivery_date' => 'required|date|after_or_equal:today',
                'delivery_time' => 'required|date_format:H:i',
            ]);

            // احسب مجموع السعر لكل العناصر
            $total_amount = 0;
            foreach ($request->items as $item) {
                $product = Product::find($item['product_id']);
                if (!$product) continue;
                $total_amount += $product->price * $item['quantity'];
            }

            // جلب آخر delivery_fee محدث
            $lastFee = DeliveryFee::latest('updated_at')->first();
            $delivery_fee = $lastFee ? $lastFee->fee : 0;

            // إنشاء الطلب
            $order = Order::create([
                'user_id' => $user->user_id,
                'address_id' => $request->address_id,
                'total_amount' => $total_amount,
                'delivery_fee' => $delivery_fee,
                'order_status' => 'pending',
                'payment_method' => $request->payment_method,
                'payment_status' => 'pending',
                'delivery_date' => $request->delivery_date,
                'delivery_time' => $request->delivery_time,
            ]);

            // إضافة عناصر الطلب
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

    // Cancel order
    public function cancel($order_id)
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();
            $order = Order::where('order_id', $order_id)->where('user_id', $user->user_id)->first();

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

    // Get my orders
    public function myOrders()
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();
            $orders = Order::with('items.product', 'address')->where('user_id', $user->user_id)->get();
            return ApiResponse::success('Orders retrieved successfully', ['orders' => $orders]);
        } catch (Exception $e) {
            return ApiResponse::error('Failed to retrieve orders', $e->getMessage(), 500);
        }
    }

    // Add review and rating if order is completed
    public function addReview(Request $request, $order_id)
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();
            $order = Order::where('order_id', $order_id)->where('user_id', $user->user_id)->first();

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

    // Get all orders (for admin or user)
    public function index()
    {
        try {
            $orders = Order::with('items.product', 'address', 'user')->get();
            return ApiResponse::success('Orders retrieved successfully', ['orders' => $orders]);
        } catch (Exception $e) {
            return ApiResponse::error('Failed to retrieve orders', $e->getMessage(), 500);
        }
    }

    // Get single order by ID
    public function show($order_id)
    {
        try {
            $order = Order::with('items.product', 'address', 'user')->where('order_id', $order_id)->first();

            if (!$order) {
                return ApiResponse::error('Order not found', null, 404);
            }

            return ApiResponse::success('Order retrieved successfully', ['order' => $order]);
        } catch (Exception $e) {
            return ApiResponse::error('Failed to retrieve order', $e->getMessage(), 500);
        }
    }
}
