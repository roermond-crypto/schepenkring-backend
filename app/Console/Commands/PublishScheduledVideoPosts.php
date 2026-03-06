<?php

namespace App\Console\Commands;

use App\Jobs\PublishVideoPost;
use App\Models\VideoPost;
use Illuminate\Console\Command;

class PublishScheduledVideoPosts extends Command
{
    protected $signature = 'social:publish-scheduled {--limit=10}';
    protected $description = 'Publish scheduled video posts via Yext';

    public function handle(): int
    {
        $limit = (int) $this->option('limit');

        $posts = VideoPost::where('status', 'scheduled')
            ->whereNotNull('scheduled_at')
            ->where('scheduled_at', '<=', now())
            ->orderBy('scheduled_at', 'asc')
            ->limit($limit)
            ->get();

        foreach ($posts as $post) {
            PublishVideoPost::dispatch($post->id)->onQueue('social-publishing');
        }

        $this->info('Queued ' . $posts->count() . ' scheduled post(s).');
        return 0;
    }
}
