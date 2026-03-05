<?php

namespace App\Jobs;

use App\Models\Blog;
use App\Services\ContentTranslationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class GenerateBlogTranslation implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public int $blogId,
        public string $targetLocale,
        public bool $force = false
    ) {
    }

    public function handle(ContentTranslationService $translations): void
    {
        $blog = Blog::find($this->blogId);
        if (!$blog) {
            return;
        }

        $target = strtolower($this->targetLocale);
        $result = $translations->translateBlog($blog, $target, $this->force);
        if ($result) {
            Log::info('Blog translation generated', [
                'blog_id' => $blog->id,
                'locale' => $target,
            ]);
        }
    }
}
