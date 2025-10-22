<?php

namespace App\Http\Controllers;

use App\Models\ProductCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Http\Responses\ApiResponse;

class ProductCategoryController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $categories = ProductCategory::all();
        return ApiResponse::success('Categories fetched successfully', ['categories' => $categories]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'category_name' => 'required|string|max:255|unique:product_categories,category_name',
            'description' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        if ($validator->fails()) {
            return ApiResponse::error('Validation failed', $validator->errors(), 422);
        }

        $category = ProductCategory::create([
            'category_name' => $request->category_name,
            'description' => $request->description,
            'is_active' => $request->is_active ?? true,
            'created_at' => now(),
        ]);

        return ApiResponse::success('Category created successfully', ['category' => $category], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        $category = ProductCategory::find($id);
        if (!$category) {
            return ApiResponse::error('Category not found', null, 404);
        }
        return ApiResponse::success('Category fetched successfully', ['category' => $category]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        $category = ProductCategory::find($id);
        if (!$category) {
            return ApiResponse::error('Category not found', null, 404);
        }

        $validator = Validator::make($request->all(), [
            'category_name' => "sometimes|required|string|max:255|unique:product_categories,category_name,$id,category_id",
            'description' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        if ($validator->fails()) {
            return ApiResponse::error('Validation failed', $validator->errors(), 422);
        }

        $category->update($request->only(['category_name', 'description', 'is_active']));
        return ApiResponse::success('Category updated successfully', ['category' => $category]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        $category = ProductCategory::find($id);
        if (!$category) {
            return ApiResponse::error('Category not found', null, 404);
        }

        try {
            $category->delete();
            return ApiResponse::success('Category deleted successfully');
        } catch (\Illuminate\Database\QueryException $e) {
            // رقم الخطأ 23000 يعني Foreign key constraint
            if ($e->getCode() == "23000") {
                return ApiResponse::error(
                    'Cannot delete this category because it has products assigned to it. Remove or reassign the products first.',
                    null,
                    403 
                );
            }

            return ApiResponse::error('Failed to delete category', $e->getMessage(), 500);
        }
    }
}
