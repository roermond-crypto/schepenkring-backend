<?php

namespace App\Jobs;

use App\Models\Harbor;
use App\Services\OutscraperEnrichmentService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class EnrichHarborThirdPartyJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;
    public int $backoff = 120;

    public function __construct(private Harbor $harbor)
    {
    }

    public function handle(OutscraperEnrichmentService $service): void
    {
        $this->harbor->refresh();

        // Restrict cost/risk: run only when contacts are missing.
        if (!empty($this->harbor->email)
            || !empty($this->harbor->website)
            || !empty($this->harbor->google_website)
        ) {
            return;
        }

        $result = $service->enrichByPlaceId($this->harbor);
        $service->applyToHarbor($this->harbor, $result);
    }
}
