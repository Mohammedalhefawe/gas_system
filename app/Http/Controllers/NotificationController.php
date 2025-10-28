<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use App\Models\UserDevice;
use App\Services\FCMService;
use Illuminate\Http\Request;
use Tymon\JWTAuth\Facades\JWTAuth;
use App\Http\Responses\ApiResponse;

class NotificationController extends Controller
{
    protected $fcmService;

    public function __construct(FCMService $fcmService)
    {
        $this->fcmService = $fcmService;
    }

    public function sendNotification($user_ids, $title, $message, $data = [], $notification_type = 'general', $related_order_id = null,  $topic = null)
    {
        try {
            $user_ids = (array)$user_ids;
            if (empty($user_ids) && !$topic) {
                return ApiResponse::error(__('messages.no_users_or_topic_specified'), null, 400);
            }

            $fcmTokens = [];
            if (!$topic) {
                if (empty($user_ids) || count($user_ids) === 0) {
                    return ApiResponse::error(__('messages.no_users_or_topic_specified'), null, 400);
                }

                $devices = UserDevice::whereIn('user_id', $user_ids)->get();
                if ($devices->isEmpty()) {
                    return ApiResponse::error(__('messages.no_devices_found'), null, 404);
                }
                $fcmTokens = $devices->pluck('device_token')->toArray();
                print_r($fcmTokens);
            }

            $response = $this->fcmService->sendNotification(
                $fcmTokens,
                $title,
                $message,
                array_merge($data, [
                    'notification_type' => $notification_type,
                    'related_order_id' => $related_order_id,
                    'action_url' => "",
                    'title_ar' => $data['title_ar'] ?? $title,
                    'body_ar' => $data['body_ar'] ?? $message,
                ]),
                $topic // Pass topic to FCMService
            );

            if ($response->failed()) {
                return ApiResponse::error(__('messages.failed_to_send_notification'), $response->body(), 500);
            }

            if (!$topic) {
                foreach ($devices as $device) {
                    Notification::create([
                        'user_id' => $device->user_id,
                        'title' => $title,
                        'message' => $message,
                        'notification_type' => $notification_type,
                        'is_read' => false,
                        'related_order_id' => $related_order_id,
                        'sent_at' => now(),
                        'action_url' => "",
                        'data' => json_encode($data, JSON_UNESCAPED_UNICODE),
                    ]);
                }
            }

            return ApiResponse::success(__('messages.notification_sent'));
        } catch (\Exception $e) {
            return ApiResponse::error(__('messages.failed_to_send_notification'), $e->getMessage(), 500);
        }
    }

    public function index(Request $request)
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();

            $notifications = Notification::where('user_id', $user->user_id)
                ->orderBy('sent_at', 'desc')
                ->get()
                ->map(function ($notification) {
                    return [
                        'notification_id'   => $notification->notification_id,
                        'user_id'           => $notification->user_id,
                        'title'             => $notification->title,
                        'message'           => $notification->message,
                        'notification_type' => $notification->notification_type,
                        'is_read'           => $notification->is_read,
                        'related_order_id'  => $notification->related_order_id,
                        'action_url'        => $notification->action_url,
                        'data'              => $notification->data ? json_decode($notification->data, true) : null,
                        'sent_at'           => $notification->sent_at,
                    ];
                });

            return ApiResponse::success(__('messages.notifications_retrieved'), ['notifications' => $notifications]);
        } catch (\Exception $e) {
            return ApiResponse::error(__('messages.failed_to_retrieve_notifications'), $e->getMessage(), 500);
        }
    }


    public function markAsRead($notification_id)
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();
            $notification = Notification::where('notification_id', $notification_id)
                ->where('user_id', $user->user_id)
                ->first();

            if (!$notification) {
                return ApiResponse::error(__('messages.notification_not_found'), null, 404);
            }

            $notification->update(['is_read' => true]);
            return ApiResponse::success(__('messages.notification_marked_read'));
        } catch (\Exception $e) {
            return ApiResponse::error(__('messages.failed_to_mark_notification'), $e->getMessage(), 500);
        }
    }
}
