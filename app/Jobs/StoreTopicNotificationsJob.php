<?php

namespace App\Jobs;

use App\Models\Notification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;

class StoreTopicNotificationsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $users;
    protected $title;
    protected $message;
    protected $notification_type;
    protected $related_order_id;
    protected $data;

    public $tries = 3;      // عدد المحاولات عند الفشل
    public $timeout = 120;  // وقت المهلة بالثواني

    public function __construct($users, $title, $message, $notification_type = 'general', $related_order_id = null, $data = [])
    {
        $this->users = $users;
        $this->title = $title;
        $this->message = $message;
        $this->notification_type = $notification_type;
        $this->related_order_id = $related_order_id;
        $this->data = $data;
    }

    public function handle()
    {
        $now = Carbon::now();
        $notifications = [];

        foreach ($this->users as $user) {
            $notifications[] = [
                'user_id' => $user->user_id,
                'title' => $this->title,
                'message' => $this->message,
                'notification_type' => $this->notification_type,
                'is_read' => false,
                'related_order_id' => $this->related_order_id,
                'sent_at' => $now,
                'action_url' => "",
                'data' => json_encode($this->data, JSON_UNESCAPED_UNICODE),
            ];
        }

        if (!empty($notifications)) {
            Notification::insert($notifications);
        }
    }
}
