<?php

namespace App\Http\Controllers;

use App\Models\ProductCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Http\Responses\ApiResponse;

class ProductCategoryController extends Controller
{
    public function index()
    {
        $categories = ProductCategory::all();
        return ApiResponse::success(__('messages.categories_fetched'), ['categories' => $categories]);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'category_name' => 'required|string|max:255|unique:product_categories,category_name',
            'description' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        if ($validator->fails()) {
            return ApiResponse::error(__('messages.validation_failed'), $validator->errors(), 422);
        }

        $category = ProductCategory::create([
            'category_name' => $request->category_name,
            'description' => $request->description,
            'is_active' => $request->is_active ?? true,
            'created_at' => now(),
        ]);

        return ApiResponse::success(__('messages.category_created'), ['category' => $category], 201);
    }

    public function show($id)
    {
        $category = ProductCategory::find($id);
        if (!$category) {
            return ApiResponse::error(__('messages.category_not_found'), null, 404);
        }
        return ApiResponse::success(__('messages.category_fetched'), ['category' => $category]);
    }

    public function update(Request $request, $id)
    {
        $category = ProductCategory::find($id);
        if (!$category) {
            return ApiResponse::error(__('messages.category_not_found'), null, 404);
        }

        $validator = Validator::make($request->all(), [
            'category_name' => "sometimes|required|string|max:255|unique:product_categories,category_name,$id,category_id",
            'description' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        if ($validator->fails()) {
            return ApiResponse::error(__('messages.validation_failed'), $validator->errors(), 422);
        }

        $category->update($request->only(['category_name', 'description', 'is_active']));
        return ApiResponse::success(__('messages.category_updated'), ['category' => $category]);
    }

    public function destroy($id)
    {
        $category = ProductCategory::find($id);
        if (!$category) {
            return ApiResponse::error(__('messages.category_not_found'), null, 404);
        }

        try {
            $category->delete();
            return ApiResponse::success(__('messages.category_deleted'));
        } catch (\Illuminate\Database\QueryException $e) {
            if ($e->getCode() == "23000") {
                return ApiResponse::error(__('messages.cannot_delete_category_fk'), null, 403);
            }

            return ApiResponse::error(__('messages.failed_to_delete_category'), $e->getMessage(), 500);
        }
    }
}
