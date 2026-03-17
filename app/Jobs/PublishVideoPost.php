<?php

namespace App\Jobs;

use App\Models\VideoPost;
use App\Services\YextSocialService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class PublishVideoPost implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public array $backoff = [60, 300, 900];
    private int $postId;

    public function __construct(int $postId)
    {
        $this->postId = $postId;
        $this->onQueue('social-publishing');
    }

    public function handle(): void
    {
        $post = VideoPost::with('video.yacht')->find($this->postId);
        if (!$post) {
            return;
        }

        if ($post->status === 'published') {
            return;
        }

        if (!$post->video || $post->video->status !== 'ready') {
            $post->update([
                'status' => 'failed',
                'error_message' => 'Video not ready for publishing.',
            ]);
            return;
        }

        $post->update([
            'status' => 'publishing',
            'attempts' => $post->attempts + 1,
        ]);

        $result = app(YextSocialService::class)->createPost($post);

        if (!($result['success'] ?? false)) {
            $post->update([
                'status' => 'failed',
                'error_message' => $result['error'] ?? 'Yext publish failed.',
            ]);
            Log::warning('Video post failed', ['post_id' => $post->id, 'error' => $result['error'] ?? null]);
            return;
        }

        $post->update([
            'status' => 'published',
            'published_at' => now(),
            'yext_post_id' => $result['post_id'] ?? $post->yext_post_id,
            'error_message' => null,
        ]);
    }
}
