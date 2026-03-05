<?php

namespace App\Jobs;

use App\Models\Harbor;
use App\Services\GoogleGeocodingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class GeocodeHarborJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 60;

    public function __construct(private Harbor $harbor)
    {
    }

    public function handle(GoogleGeocodingService $geocoder): void
    {
        Log::info("[GeocodeHarborJob] Geocoding harbor {$this->harbor->id}");

        $this->harbor->refresh();
        $result = $geocoder->geocode($this->harbor);
        $geocoder->applyToHarbor($this->harbor, $result);
    }
}
