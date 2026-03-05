<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PushNotificationService
{
    protected ?string $instanceId;
    protected ?string $secretKey;

    public function __construct()
    {
        $this->instanceId = env('PUSHER_BEAMS_INSTANCE_ID');
        $this->secretKey = env('PUSHER_BEAMS_SECRET_KEY');
    }

    /**
     * Send a browser push notification to a specific user.
     *
     * @param int $userId
     * @param string $title
     * @param string $body
     * @param string $icon
     * @return void
     */
    public function sendToUser(int $userId, string $title, string $body, string $icon = '/icon.png'): void
    {
        if (!$this->instanceId || !$this->secretKey) {
            return;
        }

        $url = "https://{$this->instanceId}.pushnotifications.pusher.com/publish_api/v1/instances/{$this->instanceId}/publishes";

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->secretKey,
            'Content-Type' => 'application/json',
        ])->post($url, [
            'interests' => ["user-{$userId}"],
            'web' => [
                'notification' => [
                    'title' => $title,
                    'body' => $body,
                    'icon' => $icon,
                    'deep_link' => url('/dashboard'), // optional
                ],
            ],
        ]);

        if ($response->failed()) {
            Log::error('Pusher Beams error', ['response' => $response->body()]);
        }
    }
}