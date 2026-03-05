<?php

namespace App\Console\Commands;

use App\Models\VideoPost;
use App\Services\YextSocialService;
use Illuminate\Console\Command;

class SyncYextPostAnalytics extends Command
{
    protected $signature = 'social:sync-analytics {--limit=50}';
    protected $description = 'Sync Yext analytics for published video posts';

    public function handle(): int
    {
        $limit = (int) $this->option('limit');

        $posts = VideoPost::where('status', 'published')
            ->whereNotNull('yext_post_id')
            ->orderBy('last_synced_at')
            ->limit($limit)
            ->get();

        $service = app(YextSocialService::class);

        foreach ($posts as $post) {
            $data = $service->fetchAnalytics($post);
            if (!$data) {
                continue;
            }

            $post->update([
                'views' => (int) ($data['views'] ?? $post->views),
                'impressions' => (int) ($data['impressions'] ?? $post->impressions),
                'clicks' => (int) ($data['clicks'] ?? $post->clicks),
                'engagement' => (int) ($data['engagement'] ?? $post->engagement),
                'last_synced_at' => now(),
            ]);
        }

        $this->info('Synced analytics for ' . $posts->count() . ' post(s).');
        return 0;
    }
}
