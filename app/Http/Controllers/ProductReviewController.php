<?php

namespace App\Http\Controllers;

use App\Models\ProductReview;
use Illuminate\Http\Request;
use App\Http\Responses\ApiResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Exception;
use Tymon\JWTAuth\Facades\JWTAuth;

class ProductReviewController extends Controller
{
    public function index()
    {
        try {
            $reviews = ProductReview::all();
            return ApiResponse::success('Reviews retrieved successfully', ['reviews' => $reviews]);
        } catch (Exception $e) {
            return ApiResponse::error('Failed to retrieve reviews', $e->getMessage(), 500);
        }
    }

    public function store(Request $request)
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();
            if (!$user) {
                return ApiResponse::error('Unauthenticated', null, 401);
            }

            $validator = Validator::make($request->all(), [
                'product_id' => 'required|exists:products,product_id',
                'rating' => 'required|numeric|min:1|max:5',
                'review' => 'nullable|string',
            ]);

            if ($validator->fails()) {
                return ApiResponse::error('Validation failed', $validator->errors(), 422);
            }

            $review = ProductReview::create([
                'product_id' => $request->product_id,
             'user_id' => $user->user_id,
                'rating' => $request->rating,
                'review' => $request->review,
            ]);

            return ApiResponse::success('Review created successfully', ['review' => $review], 201);
        } catch (Exception $e) {
            return ApiResponse::error('Failed to create review', $e->getMessage(), 500);
        }
    }

    public function show($id)
    {
        try {
            $review = ProductReview::find($id);
            if (!$review) {
                return ApiResponse::error('Review not found', null, 404);
            }
            return ApiResponse::success('Review retrieved successfully', ['review' => $review]);
        } catch (Exception $e) {
            return ApiResponse::error('Failed to retrieve review', $e->getMessage(), 500);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();
            if (!$user) {
                return ApiResponse::error('Unauthenticated', null, 401);
            }

            $review = ProductReview::find($id);
            if (!$review) {
                return ApiResponse::error('Review not found', null, 404);
            }

            // Ensure the review belongs to the authenticated user
            if ($review->user_id !== $user->user_id) {
                return ApiResponse::error('Unauthorized', null, 403);
            }

            $validator = Validator::make($request->all(), [
                'rating' => 'sometimes|numeric|min:1|max:5',
                'review' => 'nullable|string',
            ]);

            if ($validator->fails()) {
                return ApiResponse::error('Validation failed', $validator->errors(), 422);
            }

            $review->update($request->only(['rating', 'review']));

            return ApiResponse::success('Review updated successfully', ['review' => $review]);
        } catch (Exception $e) {
            return ApiResponse::error('Failed to update review', $e->getMessage(), 500);
        }
    }

    public function destroy($id)
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();
            if (!$user) {
                return ApiResponse::error('Unauthenticated', null, 401);
            }

            $review = ProductReview::find($id);
            if (!$review) {
                return ApiResponse::error('Review not found', null, 404);
            }

            // Ensure the review belongs to the authenticated user
            if ($review->user_id !== $user->id) {
                return ApiResponse::error('Unauthorized', null, 403);
            }

            $review->delete();
            return ApiResponse::success('Review deleted successfully');
        } catch (Exception $e) {
            return ApiResponse::error('Failed to delete review', $e->getMessage(), 500);
        }
    }
}
