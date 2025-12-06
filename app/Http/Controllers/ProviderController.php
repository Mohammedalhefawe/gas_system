<?php

namespace App\Http\Controllers;

use App\Models\Provider;
use App\Models\Order;
use App\Models\Driver;
use Illuminate\Http\Request;
use Tymon\JWTAuth\Facades\JWTAuth;
use App\Http\Responses\ApiResponse;

class ProviderController extends Controller
{



    /**
     * جلب بيانات المزوّد الحالي
     */
    private function getProvider()
    {
        $user = JWTAuth::parseToken()->authenticate();
        $provider = $user->provider;
        return $provider;
    }

    /**
     * قبول طلب من قبل المزود
     */
    public function acceptOrder(Request $request, $order_id)
    {
        try {
            $provider = $this->getProvider();
            if (!$provider) {
                return ApiResponse::error(__('messages.provider_not_found'), null, 404);
            }

            if (!$provider->is_available) {
                return ApiResponse::error(__('messages.provider_not_available'), null, 400);
            }

            $order = Order::with('items.product')->find($order_id);
            if (!$order) {
                return ApiResponse::error(__('messages.order_not_found'), null, 404);
            }

            if ($provider->sector_id !== $order->sector_id) {
                return ApiResponse::error(__('messages.provider_wrong_sector'), null, 400);
            }

            if ($order->order_status !== 'pending_provider') {
                return ApiResponse::error(__('messages.order_already_taken'), null, 400);
            }



            // تحقق من توفر كل المنتجات المطلوبة لدى المزود
            foreach ($order->items as $item) {
                $providerProduct = $provider->products()
                    ->where('provider_products.product_id', $item->product_id) // تحديد الجدول هنا
                    ->first();

                if (!$providerProduct || !$providerProduct->pivot->is_available) {
                    return ApiResponse::error(
                        __('messages.product_not_available', ['product' => $item->product->name]),
                        null,
                        400
                    );
                }
            }


            // تغيير حالة الطلب
            $order->order_status = 'pending_driver';
            $order->provider_id = $provider->provider_id;
            $order->save();

            // إرسال إشعار لجميع السائقين المتاحين في نفس القطاع
            $drivers = Driver::where('sector_id', $order->sector_id)
                ->where('is_available', true)
                ->pluck('user_id')->toArray();

            if (!empty($drivers)) {
                app(NotificationController::class)->sendNotification(
                    $drivers,
                    "New Order for Driver",
                    "Order #{$order->order_id} needs a driver in your sector.",
                    ['order_id' => $order->order_id],
                    'order_status',
                    $order->order_id,
                    'drivers'
                );
            }

            return ApiResponse::success(__('messages.order_accepted_by_provider'), ['order' => $order]);
        } catch (\Exception $e) {
            return ApiResponse::error(__('messages.failed_accept_order'), $e->getMessage(), 500);
        }
    }


    /**
     * رفض طلب من قبل المزود
     */
    public function rejectOrder(Request $request, $order_id)
    {
        try {
            $provider = $this->getProvider();
            if (!$provider) {
                return ApiResponse::error(__('messages.provider_not_found'), null, 404);
            }

            $order = Order::find($order_id);
            if (!$order) {
                return ApiResponse::error(__('messages.order_not_found'), null, 404);
            }

            if ($order->order_status !== 'pending_provider') {
                return ApiResponse::error(__('messages.order_already_taken'), null, 400);
            }

            // إعادة الطلب ليكون متاح لمزود آخر
            $order->provider_id = null;
            $order->order_status = 'pending_provider';
            $order->save();

            return ApiResponse::success(__('messages.order_rejected_by_provider'), ['order' => $order]);
        } catch (\Exception $e) {
            return ApiResponse::error(__('messages.failed_reject_order'), $e->getMessage(), 500);
        }
    }
    /**
     * عرض كل المنتجات الخاصة بالمزوّد
     */
    public function myProducts()
    {
        try {
            $provider = $this->getProvider();


            if (!$provider) {
                return ApiResponse::error(__('messages.provider_not_found'), null, 404);
            }

            $products = $provider->products()->get();

            return ApiResponse::success(__('messages.provider_products_retrieved'), [
                'products' => $products
            ]);
        } catch (\Exception $e) {
            return ApiResponse::error(__('messages.failed_retrieve_products'), $e->getMessage(), 500);
        }
    }

    /**
     * إضافة منتج إلى المزوّد مع حالة توفره
     */
    public function addProduct(Request $request)
    {
        try {
            $provider = $this->getProvider();


            if (!$provider) {
                return ApiResponse::error(__('messages.provider_not_found'), null, 404);
            }

            $request->validate([
                'product_id' => 'required|exists:products,product_id',
                'is_available' => 'boolean',
            ]);

            // منع التكرار
            if ($provider->products()->where('products.product_id', $request->product_id)->exists()) {
                return ApiResponse::error(__('messages.product_already_added'), null, 400);
            }

            $provider->products()->attach($request->product_id, [
                'is_available' => $request->is_available ?? true,
            ]);

            return ApiResponse::success(__('messages.product_added_to_provider'));
        } catch (\Exception $e) {
            return ApiResponse::error(__('messages.failed_add_product'), $e->getMessage(), 500);
        }
    }

    /**
     * تحديث حالة توفر منتج عند المزوّد
     */
    public function updateProductAvailability(Request $request)
    {
        try {
            $provider = $this->getProvider();


            if (!$provider) {
                return ApiResponse::error(__('messages.provider_not_found'), null, 404);
            }

            $request->validate([
                'product_id' => 'required|exists:products,product_id',
                'is_available' => 'required|boolean',
            ]);

            if (!$provider->products()->where('products.product_id', $request->product_id)->exists()) {
                return ApiResponse::error(__('messages.product_not_found_for_provider'), null, 404);
            }

            $provider->products()->updateExistingPivot($request->product_id, [
                'is_available' => $request->is_available,
            ]);

            return ApiResponse::success(__('messages.product_availability_updated'));
        } catch (\Exception $e) {
            return ApiResponse::error(__('messages.failed_update_availability'), $e->getMessage(), 500);
        }
    }

    /**
     * حذف منتج من منتجات المزوّد
     */
    public function removeProduct($product_id)
    {
        try {
            $provider = $this->getProvider();


            if (!$provider) {
                return ApiResponse::error(__('messages.provider_not_found'), null, 404);
            }

            if (!$provider->products()->where('products.product_id', $product_id)->exists()) {
                return ApiResponse::error(__('messages.product_not_found_for_provider'), null, 404);
            }

            $provider->products()->detach($product_id);

            return ApiResponse::success(__('messages.product_removed_from_provider'));
        } catch (\Exception $e) {
            return ApiResponse::error(__('messages.failed_remove_product'), $e->getMessage(), 500);
        }
    }

    /**
     * تعديل حالة توفر المزوّد نفسه (Online / Offline)
     */
    public function toggleProviderAvailability()
    {
        try {
            $provider = $this->getProvider();


            if (!$provider) {
                return ApiResponse::error(__('messages.provider_not_found'), null, 404);
            }

            $provider->is_available = !$provider->is_available;
            $provider->save();

            return ApiResponse::success(
                __('messages.provider_availability_updated'),
                ['is_available' => $provider->is_available]
            );
        } catch (\Exception $e) {
            return ApiResponse::error(__('messages.failed_update_provider_status'), $e->getMessage(), 500);
        }
    }

    public function getAllProviders(Request $request)
    {
        try {
            // استلام عدد العناصر في الصفحة (افتراضي 10)
            $perPage = $request->get('per_page', 10);

            // جلب Providers مع المستخدم المرتبط
            $providers = Provider::with('user')
                ->orderBy('provider_id', 'desc')
                ->paginate($perPage);

            // تجهيز شكل البيانات المتوافق مع PaginatedModel في Flutter
            $data = [
                'items' => $providers->items(),
                'current_page' => $providers->currentPage(),
                'last_page' => $providers->lastPage(),
                'per_page' => $providers->perPage(),
                'total' => $providers->total(),
            ];

            return ApiResponse::success(__('messages.providers_retrieved'), $data);
        } catch (\Exception $e) {
            return ApiResponse::error(
                __('messages.failed_retrieve_providers'),
                $e->getMessage(),
                500
            );
        }
    }

    /**
     * جلب طلبات المزود الخاصة به حسب حالة الطلب
     */
    public function myOrders(Request $request)
    {
        try {
            $provider = $this->getProvider();
            if (!$provider) {
                return ApiResponse::error(__('messages.provider_not_found'), null, 404);
            }

            $statusFilter = $request->get('status', ['pending_driver', 'on_the_way_provider']); // افتراضي

            $query = Order::with('items.product', 'address', 'customer.user')
                ->where('provider_id', $provider->provider_id)
                ->whereIn('order_status', (array)$statusFilter);

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



    /**
     * جلب الطلبات المتاحة للمزود (Pending Provider) حسب قطاعه
     */
    public function availableOrders(Request $request)
    {
        try {
            $provider = $this->getProvider();
            if (!$provider) {
                return ApiResponse::error(__('messages.provider_not_found'), null, 404);
            }

            $query = Order::with('items.product', 'address', 'customer.user')
                ->where('sector_id', $provider->sector_id)
                ->where('order_status', 'pending_provider');

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
}
