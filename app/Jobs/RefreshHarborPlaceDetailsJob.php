<?php

namespace App\Jobs;

use App\Models\Harbor;
use App\Services\GooglePlaceDetailsService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class RefreshHarborPlaceDetailsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 60;

    public function __construct(
        private Harbor $harbor,
        private bool $includePhotos = true
    ) {
    }

    public function handle(GooglePlaceDetailsService $placeDetails): void
    {
        $this->harbor->refresh();

        if (empty($this->harbor->gmaps_place_id)) {
            Log::warning("[RefreshHarborPlaceDetailsJob] Harbor {$this->harbor->id} missing place_id");
            return;
        }

        $details = $placeDetails->getDetails(
            $this->harbor,
            includePhotos: $this->includePhotos,
            includeReviews: false
        );

        $placeDetails->applyToHarbor($this->harbor, $details, $this->includePhotos);
    }
}
