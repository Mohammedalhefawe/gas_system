<?php

namespace App\Http\Controllers;

use App\Models\Provider;
use App\Models\Product;
use Illuminate\Http\Request;
use Tymon\JWTAuth\Facades\JWTAuth;
use App\Http\Responses\ApiResponse;

class ProviderController extends Controller
{
    /**
     * عرض كل المنتجات الخاصة بالمزوّد
     */
    public function myProducts()
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();
            $provider = $user->provider;

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
            $user = JWTAuth::parseToken()->authenticate();
            $provider = $user->provider;

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
            $user = JWTAuth::parseToken()->authenticate();
            $provider = $user->provider;

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
            $user = JWTAuth::parseToken()->authenticate();
            $provider = $user->provider;

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
            $user = JWTAuth::parseToken()->authenticate();
            $provider = $user->provider;

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
}
