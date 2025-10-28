<?php

namespace App\Services;

use Google_Client;
use Illuminate\Support\Facades\Http;

class FCMService
{
    protected $client;

    public function __construct()
    {
        $this->client = new Google_Client();
        $this->client->setAuthConfig(env('FCM_CREDENTIALS_PATH'));
        $this->client->addScope('https://www.googleapis.com/auth/firebase.messaging');
    }

    public function sendNotification(array $tokens = [], string $title, string $message, array $data = [], ?string $topic = null)
    {
        $accessToken = $this->client->fetchAccessTokenWithAssertion()['access_token'];

        $messagePayload = [
            'notification' => [
                'title' => $title,
                'body'  => $message,
            ],
            'data' => array_map('strval', array_merge($data, [
                'title_en' => $title,
                'body_en' => $message,
                'title_ar' => $data['title_ar'] ?? $title,
                'body_ar' => $data['body_ar'] ?? $message,
                // 'notification_type' => $data['notification_type'] ?? 'general',
                'related_order_id' => $data['related_order_id'] ?? null,
                // 'action_url' => $data['action_url'] ?? null,
                // 'payload_route' => $data['payload_route'] ?? '',
            ])),
        ];

        // تحديد نوع الارسال
        if ($topic) {

            $messagePayload['topic'] = $topic;
        } elseif (count($tokens) === 1) {
            $messagePayload['token'] = $tokens[0];
        } else {

            // v1 API doesn't support registration_ids
            // لازم ارسال متعدد عبر loop أو Firebase messaging batch
            foreach ($tokens as $token) {
                $this->sendNotification([$token], $title, $message, $data, null);
            }
            return; // stop to avoid final request
        }

        $payload = ['message' => $messagePayload];

        return Http::withHeaders([
            'Authorization' => "Bearer $accessToken",
            'Content-Type'  => 'application/json',
        ])->post(
            "https://fcm.googleapis.com/v1/projects/" . env('FCM_PROJECT_ID') . "/messages:send",
            $payload
        );
    }
}
