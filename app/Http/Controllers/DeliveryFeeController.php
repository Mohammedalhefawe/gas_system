<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\DeliveryFee;
use App\Http\Responses\ApiResponse;
use Exception;

class DeliveryFeeController extends Controller
{
    public function store(Request $request)
    {
        try {
            $request->validate([
                'fee' => 'required|numeric|min:0',
            ]);

            $deliveryFee = DeliveryFee::create([
                'fee' => $request->fee,
                'date' => now()->toDateString(),
            ]);

            return ApiResponse::success('Delivery fee set successfully', ['delivery_fee' => $deliveryFee]);
        } catch (Exception $e) {
            return ApiResponse::error('Failed to set delivery fee', $e->getMessage(), 500);
        }
    }

    public function latest()
    {
        try {
            $deliveryFee = DeliveryFee::latest('date')->first();
            if (!$deliveryFee) {
                return ApiResponse::error('No delivery fee found', null, 404);
            }
            return ApiResponse::success('Latest delivery fee retrieved', ['delivery_fee' => $deliveryFee]);
        } catch (Exception $e) {
            return ApiResponse::error('Failed to get delivery fee', $e->getMessage(), 500);
        }
    }
}
