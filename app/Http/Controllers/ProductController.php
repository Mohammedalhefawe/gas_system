<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\Request;
use App\Http\Responses\ApiResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Exception;

class ProductController extends Controller
{
    public function index()
    {
        try {
            $products = Product::all();
            return ApiResponse::success('Products retrieved successfully', ['products' => $products]);
        } catch (Exception $e) {
            return ApiResponse::error('Failed to retrieve products', $e->getMessage(), 500);
        }
    }

    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'category_id' => 'required|exists:product_categories,category_id',
                'product_name' => 'required|string|max:255',
                'description' => 'nullable|string',
                'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
                'price' => 'required|numeric',
                'is_available' => 'required|boolean',
                'special_notes' => 'nullable|string',
            ]);

            if ($validator->fails()) {
                return ApiResponse::error('Validation failed', $validator->errors(), 422);
            }

            $imagePath = null;
            if ($request->hasFile('image')) {
                $imagePath = $request->file('image')->store('products', 'public');
            }

            $product = Product::create([
                'category_id' => $request->category_id,
                'product_name' => $request->product_name,
                'description' => $request->description,
                'image_url' => $imagePath,
                'price' => $request->price,
                'is_available' => $request->is_available,
                'special_notes' => $request->special_notes,
            ]);

            return ApiResponse::success('Product created successfully', ['product' => $product], 201);
        } catch (Exception $e) {
            return ApiResponse::error('Failed to create product', $e->getMessage(), 500);
        }
    }

    public function show($id)
    {
        try {
            $product = Product::find($id);
            if (!$product) {
                return ApiResponse::error('Product not found', null, 404);
            }
            return ApiResponse::success('Product retrieved successfully', ['product' => $product]);
        } catch (Exception $e) {
            return ApiResponse::error('Failed to retrieve product', $e->getMessage(), 500);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $product = Product::find($id);
            if (!$product) {
                return ApiResponse::error('Product not found', null, 404);
            }

            $validator = Validator::make($request->all(), [
                'category_id' => 'sometimes|exists:product_categories,category_id',
                'product_name' => 'sometimes|string|max:255',
                'description' => 'nullable|string',
                'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
                'price' => 'sometimes|numeric',
                'is_available' => 'sometimes|boolean',
                'special_notes' => 'nullable|string',
            ]);

            if ($validator->fails()) {
                return ApiResponse::error('Validation failed', $validator->errors(), 422);
            }

            // أول شيئ: تحديث باقي البيانات
            $product->update($request->only([
                'category_id',
                'product_name',
                'description',
                'price',
                'is_available',
                'special_notes',
            ]));

            // إذا تم رفع صورة جديدة
            if ($request->hasFile('image')) {
                if ($product->image_url) {
                    Storage::disk('public')->delete($product->image_url);
                }

                $imagePath = $request->file('image')->store('products', 'public');
                $product->image_url = $imagePath;
                $product->save(); // تأكيد حفظ الصورة
            }

            return ApiResponse::success('Product updated successfully', ['product' => $product]);
        } catch (Exception $e) {
            return ApiResponse::error('Failed to update product', $e->getMessage(), 500);
        }
    }


public function destroy($id)
{
    try {
        $product = Product::find($id);
        if (!$product) {
            return ApiResponse::error('Product not found', null, 404);
        }

        // محاولة حذف المنتج، إذا مستخدم بطلب يتم إطلاق FK error
        $product->delete();

        // حذف الصورة بعد نجاح الحذف
        if ($product->image_url) {
            Storage::disk('public')->delete($product->image_url);
        }

        return ApiResponse::success('Product deleted successfully');

    } catch (\Illuminate\Database\QueryException $e) {
        // إذا كان الخطأ Foreign Key Constraint
        if ($e->getCode() == "23000") {
            return ApiResponse::error(
                'Cannot delete this product because it is already used in one or more orders',
                null,
                403
            );
        }

        return ApiResponse::error('Failed to delete product', null, 500);

    } catch (\Exception $e) {
        return ApiResponse::error('Failed to delete product', null, 500);
    }
}

}
