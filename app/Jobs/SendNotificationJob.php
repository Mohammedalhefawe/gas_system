<?php

namespace App\Jobs;

use App\Services\FCMService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class SendNotificationJob implements ShouldQueue
{
    use Dispatchable, Queueable;

    protected $tokens;
    protected $title;
    protected $message;
    protected $data;
    protected $topic;

    public function __construct(array $tokens, string $title, string $message, array $data = [], ?string $topic = null)
    {
        $this->tokens = $tokens;
        $this->title = $title;
        $this->message = $message;
        $this->data = $data;
        $this->topic = $topic;
    }

    public function handle(FCMService $fcmService)
    {
        $fcmService->sendNotification(
            $this->tokens,
            $this->title,
            $this->message,
            $this->data,
            $this->topic
        );
    }
}
