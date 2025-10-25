<?php

namespace App\Http\Controllers;

use App\Models\Ad;
use Illuminate\Http\Request;
use Tymon\JWTAuth\Facades\JWTAuth;
use App\Http\Responses\ApiResponse;
use Illuminate\Support\Facades\Storage;

class AdController extends Controller
{
    // إضافة إعلان
    public function store(Request $request)
    {
        $user = JWTAuth::parseToken()->authenticate();

        if ($user->role_id != 1) { // 1 = Admin
            return ApiResponse::error(__('messages.unauthorized'), null, 403);
        }

        $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'image' => 'nullable|file|mimes:jpg,jpeg,png,gif,webp|max:5120', // 5MB
            'link' => 'nullable|url',
        ]);

        $imagePath = null;
        if ($request->hasFile('image')) {
            $imagePath = $request->file('image')->store('ads', 'public');
        }

        $ad = Ad::create([
            'title' => $request->title,
            'description' => $request->description,
            'image' => $imagePath, // مسار نسبي فقط
            'link' => $request->link,
            'user_id' => $user->user_id,
        ]);

        return ApiResponse::success(__('messages.ad_created'), $ad);
    }

    // تحديث إعلان
    public function update(Request $request, $id)
    {
        $user = JWTAuth::parseToken()->authenticate();
        $ad = Ad::find($id);

        if (!$ad) {
            return ApiResponse::error(__('messages.ad_not_found'), null, 404);
        }

        if ($user->role_id != 1 && $ad->user_id != $user->user_id) {
            return ApiResponse::error(__('messages.unauthorized'), null, 403);
        }

        $request->validate([
            'title' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'image' => 'nullable|file|mimes:jpg,jpeg,png,gif,webp|max:5120',
            'link' => 'nullable|url',
        ]);

        if ($request->hasFile('image')) {
            // حذف الصورة القديمة إذا موجودة
            if ($ad->image) {
                Storage::disk('public')->delete($ad->image);
            }
            $imagePath = $request->file('image')->store('ads', 'public');
            $request->merge(['image' => $imagePath]);
        }

        $ad->update($request->only(['title', 'description', 'image', 'link']));

        return ApiResponse::success(__('messages.ad_updated'), $ad);
    }

    // حذف إعلان
    public function destroy($id)
    {
        $user = JWTAuth::parseToken()->authenticate();
        $ad = Ad::find($id);

        if (!$ad) {
            return ApiResponse::error(__('messages.ad_not_found'), null, 404);
        }

        if ($user->role_id != 1 && $ad->user_id != $user->user_id) {
            return ApiResponse::error(__('messages.unauthorized'), null, 403);
        }

        // حذف الصورة إذا موجودة
        if ($ad->image) {
            Storage::disk('public')->delete($ad->image);
        }

        $ad->delete();

        return ApiResponse::success(__('messages.ad_deleted'));
    }

    // عرض جميع الإعلانات بدون بيانات المستخدم وبدون prefix URL
    public function index()
    {
        $ads = Ad::all()->map(function ($ad) {
            return [
                'id' => $ad->id,
                'title' => $ad->title,
                'description' => $ad->description,
                'image' => $ad->image, // مسار نسبي فقط
                'link' => $ad->link,
                'user_id' => $ad->user_id,
                'created_at' => $ad->created_at,
                'updated_at' => $ad->updated_at,
            ];
        });

        return ApiResponse::success(__('messages.ads_list'), ['ads' => $ads]);
    }

    // عرض إعلان محدد بدون بيانات المستخدم وبدون prefix URL
    public function show($id)
    {
        $ad = Ad::find($id);

        if (!$ad) {
            return ApiResponse::error(__('messages.ad_not_found'), null, 404);
        }

        return ApiResponse::success(__('messages.ad_details'), [
            'id' => $ad->id,
            'title' => $ad->title,
            'description' => $ad->description,
            'image' => $ad->image, // مسار نسبي فقط
            'link' => $ad->link,
            'user_id' => $ad->user_id,
            'created_at' => $ad->created_at,
            'updated_at' => $ad->updated_at,
        ]);
    }
}
