<?php

namespace App\Services;

use App\Models\SocialLog;
use App\Models\VideoPost;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class YextSocialService
{
    public function createPost(VideoPost $post): array
    {
        $config = config('services.yext');
        $apiKey = $config['api_key'] ?? null;
        $accountId = $post->yext_account_id ?: ($config['account_id'] ?? null);
        $entityId = $post->yext_entity_id ?: ($config['entity_id'] ?? null);

        if (!$apiKey || !$accountId || !$entityId) {
            return $this->fail("Missing Yext configuration (api_key/account_id/entity_id).");
        }

        $video = $post->video;
        if (!$video) {
            return $this->fail('Video not found for post.');
        }

        $yacht = $video->yacht;
        if (!$yacht) {
            return $this->fail('Yacht not found for post.');
        }

        $publishers = $post->publishers ?: config('video_automation.default_publishers', []);
        if (is_string($publishers)) {
            $publishers = json_decode($publishers, true) ?: [];
        }
        $publishers = array_values(array_filter(array_map('strtolower', $publishers)));

        $payload = $this->buildPostPayload($post, $publishers, $entityId, $accountId, $apiKey);
        $url = $this->buildUrl("/v2/accounts/{$accountId}/posts", $apiKey);

        $response = Http::withHeaders(['Content-Type' => 'application/json'])
            ->timeout(30)
            ->post($url, $payload);

        $this->logResponse($post->id, 'create_post', $payload, $response->json(), $response->status());

        if ($response->failed()) {
            return $this->fail('Yext post failed: ' . $response->body());
        }

        $postId = $response->json('response.id') ?? $response->json('id');

        return [
            'success' => true,
            'post_id' => $postId,
            'response' => $response->json(),
        ];
    }

    public function fetchAnalytics(VideoPost $post): ?array
    {
        $config = config('services.yext');
        $endpoint = $config['analytics_endpoint'] ?? null;
        $apiKey = $config['api_key'] ?? null;
        $accountId = $post->yext_account_id ?: ($config['account_id'] ?? null);

        if (!$endpoint || !$apiKey || !$accountId || !$post->yext_post_id) {
            return null;
        }

        $url = str_replace(
            ['{accountId}', '{postId}'],
            [$accountId, $post->yext_post_id],
            $endpoint
        );
        $url = $this->appendKeyAndVersion($url, $apiKey);

        $response = Http::timeout(20)->get($url);

        $this->logResponse($post->id, 'analytics', null, $response->json(), $response->status());

        if ($response->failed()) {
            Log::warning('Yext analytics failed', ['post_id' => $post->id, 'status' => $response->status()]);
            return null;
        }

        return $response->json('response') ?? $response->json();
    }

    private function buildPostPayload(VideoPost $post, array $publishers, string $entityId, string $accountId, string $apiKey): array
    {
        $video = $post->video;
        $yacht = $video->yacht;
        $videoUrl = $video->video_url;
        $thumbnailUrl = $video->thumbnail_url;
        $caption = $video->caption ?: app(VideoCaptionService::class)->buildCaption($yacht);
        $clickthroughUrl = app(VideoAutomationService::class)->buildClickthroughUrl($yacht, $video);

        $payload = [
            'entityIds' => [$entityId],
            'postDate' => optional($post->scheduled_at)->toIso8601String() ?: now()->toIso8601String(),
            'text' => $caption,
            'clickthroughUrl' => $clickthroughUrl,
        ];

        $usePublisherTargets = (bool) (config('services.yext.use_publisher_targets') ?? false);
        if ($usePublisherTargets) {
            $payload['publisherTargets'] = $publishers;
        } else {
            $payload['publisher'] = $publishers[0] ?? 'facebook';
        }

        $videoPublishers = array_map('strtolower', config('services.yext.video_publishers', []));
        $supportsVideo = !empty(array_intersect($publishers, $videoPublishers));

        if ($supportsVideo && $videoUrl) {
            $payload['videoUrl'] = $this->maybeUploadVideo($videoUrl, $publishers, $accountId, $apiKey) ?? $videoUrl;
        } elseif ($thumbnailUrl) {
            $payload['photoUrls'] = [$thumbnailUrl];
        }

        return $payload;
    }

    private function maybeUploadVideo(string $videoUrl, array $publishers, string $accountId, string $apiKey): ?string
    {
        if (!config('services.yext.use_video_upload')) {
            return null;
        }

        $publisher = $publishers[0] ?? 'facebook';
        $payload = [
            'publisher' => $publisher,
            'videoUrl' => $videoUrl,
            'uploadType' => 'FETCH',
        ];

        $url = $this->buildUrl("/v2/accounts/{$accountId}/social/video", $apiKey);

        $response = Http::withHeaders(['Content-Type' => 'application/json'])
            ->timeout(30)
            ->post($url, $payload);

        $this->logResponse(null, 'upload_video', $payload, $response->json(), $response->status());

        if ($response->failed()) {
            Log::warning('Yext video upload failed', ['status' => $response->status(), 'body' => $response->body()]);
            return null;
        }

        return $response->json('response.videoUrl') ?? $response->json('videoUrl');
    }

    private function buildUrl(string $path, string $apiKey): string
    {
        $base = rtrim(config('services.yext.api_base', 'https://api.yextapis.com'), '/');
        $version = config('services.yext.api_version', '20240101');

        return "{$base}{$path}?api_key={$apiKey}&v={$version}";
    }

    private function appendKeyAndVersion(string $url, string $apiKey): string
    {
        $version = config('services.yext.api_version', '20240101');
        $separator = str_contains($url, '?') ? '&' : '?';
        return "{$url}{$separator}api_key={$apiKey}&v={$version}";
    }

    private function logResponse(?int $postId, string $event, ?array $request, ?array $response, ?int $statusCode): void
    {
        SocialLog::create([
            'video_post_id' => $postId,
            'provider' => 'yext',
            'event' => $event,
            'request_payload' => $request,
            'response_payload' => $response,
            'status_code' => $statusCode,
        ]);
    }

    private function fail(string $message): array
    {
        return [
            'success' => false,
            'error' => $message,
        ];
    }
}
