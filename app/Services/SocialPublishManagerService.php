<?php

namespace App\Services;

use App\Models\BoatVideo;
use App\Models\BoatVideoSetting;
use App\Models\SocialPost;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SocialPublishManagerService
{
    /**
     * Send video to Francis API for publishing.
     */
    public function publish(BoatVideo $video, BoatVideoSetting $settings)
    {
        $payload = [
            'boat_id' => $video->yacht_id,
            'video_url' => $video->video_url,
            'caption' => $settings->caption_template,
            'hashtags' => $settings->hashtags_template,
            'platforms' => $settings->platforms ?? ['instagram', 'facebook'],
        ];

        // Ensure platforms are selected
        if (empty($payload['platforms'])) {
            throw new \Exception('No platforms selected for publishing.');
        }

        $apiUrl = env('FRANCIS_SOCIAL_API_URL', 'https://api.francis-social.example.com/webhooks/publish');

        try {
            // Since Francis API might not exist during dev, we simulate or send a real request.
            if (!str_contains($apiUrl, 'example.com')) {
                $response = Http::timeout(10)->post($apiUrl, $payload);
            }
            
            // In a real environment, Francis API would return a 200/202 status.
            // For now, regardless of failure, we record the pending status so the webhook can update it later.
            // We simulate success in dev if the URL is dummy.
            
            foreach ($payload['platforms'] as $platform) {
                SocialPost::updateOrCreate(
                    [
                        'yacht_id' => $video->yacht_id,
                        'platform' => $platform,
                        'status' => 'pending' // Webhook will update this
                    ],
                    [
                        'error_message' => null
                    ]
                );
            }
            
            return true;
        } catch (\Exception $e) {
            Log::error("Failed to call Francis API: " . $e->getMessage());
            throw new \Exception('Failed to communicate with the Social Publishing API.');
        }
    }
    
    /**
     * Handle Webhook from Francis API.
     */
    public function handleWebhook(array $payload)
    {
        /* Expected Payload:
        {
          "status": "published",
          "platform": "instagram",
          "post_id": "123456",
          "boat_id": 123
        }
        */
        
        $post = SocialPost::where('yacht_id', $payload['boat_id'])
            ->where('platform', $payload['platform'])
            ->first();
            
        if ($post) {
            $post->update([
                'status' => $payload['status'],
                'post_id' => $payload['post_id'] ?? $post->post_id,
                'published_at' => $payload['status'] === 'published' ? now() : $post->published_at,
                'error_message' => $payload['error_message'] ?? null,
            ]);
        }
    }
}
