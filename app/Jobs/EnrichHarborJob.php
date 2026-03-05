<?php

namespace App\Jobs;

use App\Models\Harbor;
use App\Services\GoogleGeocodingService;
use App\Services\GooglePlaceDetailsService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class EnrichHarborJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 60; // retry after 60 seconds

    public function __construct(
        private Harbor $harbor
    ) {}

    public function handle(
        GoogleGeocodingService $geocoder,
        GooglePlaceDetailsService $placeDetails
    ): void {
        Log::info("[EnrichHarborJob] Enriching harbor {$this->harbor->id}: {$this->harbor->name}");

        $this->harbor->refresh();
        $query = collect([
            $this->harbor->name,
            $this->harbor->street_address,
            $this->harbor->postal_code,
            $this->harbor->city,
            'Netherlands',
        ])->filter()->implode(', ');
        $currentQueryHash = md5(mb_strtolower(trim($query)));

        // Step 1: Geocode if missing or address changed
        $shouldGeocode = empty($this->harbor->gmaps_place_id)
            || $this->harbor->geocode_query_hash !== $currentQueryHash;

        if ($shouldGeocode) {
            $geocodeResult = $geocoder->geocode($this->harbor);
            $geocoder->applyToHarbor($this->harbor, $geocodeResult);
            $this->harbor->refresh();

            if (isset($geocodeResult['error'])) {
                Log::warning("[EnrichHarborJob] Geocoding failed for harbor {$this->harbor->id}");
                return; // can't do place details without place_id
            }
        }

        // Step 2: Fetch Place Details if place_id is available
        if (!empty($this->harbor->gmaps_place_id)) {
            $shouldRefreshDetails = empty($this->harbor->last_place_details_fetch_at)
                || $this->harbor->last_place_details_fetch_at->lt(now()->subDays(30));
            $shouldRefreshPhotos = empty($this->harbor->last_place_photos_fetch_at)
                || $this->harbor->last_place_photos_fetch_at->lt(now()->subDays(90));

            if ($shouldRefreshDetails || $shouldRefreshPhotos) {
                $details = $placeDetails->getDetails(
                    $this->harbor,
                    includePhotos: $shouldRefreshPhotos,
                    includeReviews: false
                );
                $placeDetails->applyToHarbor($this->harbor, $details, $shouldRefreshPhotos);
            }
        }

        Log::info("[EnrichHarborJob] Completed enrichment for harbor {$this->harbor->id}");
    }

    public function failed(\Throwable $exception): void
    {
        Log::error("[EnrichHarborJob] Failed for harbor {$this->harbor->id}: {$exception->getMessage()}");
    }
}
