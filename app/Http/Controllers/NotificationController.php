<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use App\Models\UserDevice;
use App\Models\User;
use App\Services\FCMService;
use App\Jobs\StoreTopicNotificationsJob;
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

    public function sendNotification($user_ids, $title, $message, $data = [], $notification_type = 'general', $related_order_id = null, $topic = null)
    {
        try {
            $user_ids = (array)$user_ids;

            if (empty($user_ids) && !$topic) {
                return ApiResponse::error(__('messages.no_users_or_topic_specified'), null, 400);
            }

            $fcmTokens = [];
            $devices = collect();

            if (!$topic) {
                // المستخدمين المحددين
                if (empty($user_ids) || count($user_ids) === 0) {
                    return ApiResponse::error(__('messages.no_users_or_topic_specified'), null, 400);
                }

                // $devices = UserDevice::whereIn('user_id', $user_ids)->get();

                // جلب آخر جهاز نشط لكل مستخدم
                $devices = UserDevice::whereIn('user_id', $user_ids)
                    ->orderBy('last_active', 'desc')
                    ->get()
                    ->unique('user_id'); // يأخذ جهاز واحد لكل مستخدم فقط

                if ($devices->isEmpty()) {
                    return ApiResponse::error(__('messages.no_devices_found'), null, 404);
                }

                $fcmTokens = $devices->pluck('device_token')->toArray();
            }


            // print_r($fcmTokenss);
            // إرسال الإشعار عبر FCM
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
                $topic
            );

            // تخزين الإشعارات في قاعدة البيانات
            if (!$topic) {
                // المستخدمين المحددين
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
            } else {
                // Topic محدد
                $users = match ($topic) {
                    'customers' => User::where('role_id', 2)->where('is_verified', true)->get(),
                    'drivers' => User::where('role_id', 3)->where('is_verified', true)->get(),
                    'all' => User::whereIn('role_id', [2, 3])->where('is_verified', true)->get(),
                    default => collect(),
                };

                // إرسال Job لتخزين الإشعارات بشكل async
                if ($users->isNotEmpty()) {
                    dispatch(new StoreTopicNotificationsJob(
                        $users,
                        $title,
                        $message,
                        $notification_type,
                        $related_order_id,
                        $data
                    ));
                }
            }

            return ApiResponse::success(__('messages.notification_sent'));
        } catch (\Exception $e) {
            return ApiResponse::error(__('messages.failed_to_send_notification'), $e->getMessage(), 500);
        }
    }

    // باقي الدوال: index و markAsRead كما هي بدون تغيير
    public function index(Request $request)
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();

            $query = Notification::where('user_id', $user->user_id)
                ->orderBy('sent_at', 'desc');

            $perPage = $request->get('per_page', 10);
            $notifications = $query->paginate($perPage);

            // تحويل العناصر للـ array المطلوب
            $items = $notifications->map(function ($notification) {
                return [
                    'notification_id'   => $notification->notification_id,
                    'user_id'           => $notification->user_id,
                    'title'             => $notification->title,
                    'message'           => $notification->message,
                    'notification_type' => $notification->notification_type,
                    'is_read'           => $notification->is_read,
                    'related_order_id'  => $notification->related_order_id,
                    'action_url'        => $notification->action_url,
                    'data'              => is_string($notification->data) ? json_decode($notification->data, true) : $notification->data,
                    'sent_at'           => $notification->sent_at,
                ];
            });

            return ApiResponse::success(__('messages.notifications_retrieved'), [
                'items' => $items,
                'current_page' => $notifications->currentPage(),
                'last_page' => $notifications->lastPage(),
                'per_page' => $notifications->perPage(),
                'total' => $notifications->total(),
            ]);
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
