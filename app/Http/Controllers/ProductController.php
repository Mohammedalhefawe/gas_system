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
            return ApiResponse::success(__('messages.products_retrieved'), ['products' => $products]);
        } catch (Exception $e) {
            return ApiResponse::error(__('messages.failed_to_retrieve_products'), $e->getMessage(), 500);
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
                return ApiResponse::error(__('messages.validation_failed'), $validator->errors(), 422);
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

            return ApiResponse::success(__('messages.product_created'), ['product' => $product], 201);
        } catch (Exception $e) {
            return ApiResponse::error(__('messages.failed_to_create_product'), $e->getMessage(), 500);
        }
    }

    public function show($id)
    {
        try {
            $product = Product::find($id);
            if (!$product) {
                return ApiResponse::error(__('messages.product_not_found'), null, 404);
            }
            return ApiResponse::success(__('messages.product_retrieved'), ['product' => $product]);
        } catch (Exception $e) {
            return ApiResponse::error(__('messages.failed_to_retrieve_product'), $e->getMessage(), 500);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $product = Product::find($id);
            if (!$product) {
                return ApiResponse::error(__('messages.product_not_found'), null, 404);
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
                return ApiResponse::error(__('messages.validation_failed'), $validator->errors(), 422);
            }

            $product->update($request->only([
                'category_id',
                'product_name',
                'description',
                'price',
                'is_available',
                'special_notes',
            ]));

            if ($request->hasFile('image')) {
                if ($product->image_url) {
                    Storage::disk('public')->delete($product->image_url);
                }
                $imagePath = $request->file('image')->store('products', 'public');
                $product->image_url = $imagePath;
                $product->save();
            }

            return ApiResponse::success(__('messages.product_updated'), ['product' => $product]);
        } catch (Exception $e) {
            return ApiResponse::error(__('messages.failed_to_update_product'), $e->getMessage(), 500);
        }
    }

    public function destroy($id)
    {
        try {
            $product = Product::find($id);
            if (!$product) {
                return ApiResponse::error(__('messages.product_not_found'), null, 404);
            }

            $product->delete();

            if ($product->image_url) {
                Storage::disk('public')->delete($product->image_url);
            }

            return ApiResponse::success(__('messages.product_deleted'));
        } catch (\Illuminate\Database\QueryException $e) {
            if ($e->getCode() == "23000") {
                return ApiResponse::error(__('messages.cannot_delete_product_fk'), null, 403);
            }
            return ApiResponse::error(__('messages.failed_to_delete_product'), null, 500);
        } catch (\Exception $e) {
            return ApiResponse::error(__('messages.failed_to_delete_product'), null, 500);
        }
    }
}
