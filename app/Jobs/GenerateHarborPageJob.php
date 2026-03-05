<?php

namespace App\Jobs;

use App\Models\Harbor;
use App\Services\HarborAiPageService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class GenerateHarborPageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;
    public int $backoff = 30;

    public function __construct(
        private Harbor $harbor,
        private string $locale = 'nl'
    ) {}

    public function handle(HarborAiPageService $pageService): void
    {
        Log::info("[GenerateHarborPageJob] Generating page for harbor {$this->harbor->id} ({$this->locale})");

        $result = $pageService->generatePage($this->harbor, $this->locale);

        if (isset($result['error'])) {
            Log::error("[GenerateHarborPageJob] Failed: {$result['error']}");
            return;
        }

        Log::info("[GenerateHarborPageJob] Page generated successfully for harbor {$this->harbor->id}");
    }

    public function failed(\Throwable $exception): void
    {
        Log::error("[GenerateHarborPageJob] Failed for harbor {$this->harbor->id}: {$exception->getMessage()}");
    }
}
