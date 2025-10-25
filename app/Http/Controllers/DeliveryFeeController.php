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

            return ApiResponse::success(__('messages.delivery_fee_set'), ['delivery_fee' => $deliveryFee]);
        } catch (\Illuminate\Validation\ValidationException $ve) {
            return ApiResponse::error(__('messages.validation_failed'), $ve->errors(), 422);
        } catch (Exception $e) {
            return ApiResponse::error(__('messages.delivery_fee_failed'), $e->getMessage(), 500);
        }
    }

    public function latest()
    {
        try {
            $deliveryFee = DeliveryFee::latest('date')->first();
            if (!$deliveryFee) {
                return ApiResponse::error(__('messages.no_delivery_fee'), null, 404);
            }
            return ApiResponse::success(__('messages.latest_delivery_fee'), ['delivery_fee' => $deliveryFee]);
        } catch (Exception $e) {
            return ApiResponse::error(__('messages.delivery_fee_failed'), $e->getMessage(), 500);
        }
    }
}
