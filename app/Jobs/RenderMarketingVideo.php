<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class RenderMarketingVideo implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 300;
    public int $tries = 3;
    public array $backoff = [60, 180, 300];

    private int $videoId;

    public function __construct(int $videoId)
    {
        $this->videoId = $videoId;
        $this->onQueue('video-rendering');
    }

    public function handle(): void
    {
        // Stub implementation to keep the API contract intact.
        Log::info('RenderMarketingVideo stub executed', ['video_id' => $this->videoId]);
    }
}
