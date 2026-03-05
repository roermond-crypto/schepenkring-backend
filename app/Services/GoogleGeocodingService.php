<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use App\Models\Harbor;

class GoogleGeocodingService
{
    private string $apiKey;

    public function __construct()
    {
        $this->apiKey = config('services.google.maps_api_key', env('GOOGLE_MAPS_API_KEY', ''));
    }

    /**
     * Geocode a harbor's address and populate Google fields.
     *
     * @return array  ['place_id', 'lat', 'lng', 'formatted_address', 'confidence', 'maps_url', 'components']
     */
    public function geocode(Harbor $harbor): array
    {
        if (empty($this->apiKey)) {
            Log::error('[GoogleGeocode] No GOOGLE_MAPS_API_KEY configured');
            return ['error' => 'API key not configured'];
        }

        // Build a precise query for NL accuracy (include postal code + city)
        $query = collect([
            $harbor->name,
            $harbor->street_address,
            $harbor->postal_code,
            $harbor->city,
            'Netherlands',
        ])->filter()->implode(', ');
        $queryHash = md5(mb_strtolower(trim($query)));

        if (!empty($harbor->gmaps_place_id) && $harbor->geocode_query_hash === $queryHash) {
            return [
                'place_id' => $harbor->gmaps_place_id,
                'lat' => $harbor->lat,
                'lng' => $harbor->lng,
                'formatted_address' => $harbor->gmaps_formatted_address,
                'confidence' => $harbor->geocode_confidence,
                'maps_url' => $harbor->maps_url,
                'components' => $harbor->address_components ?? [],
                'query_hash' => $queryHash,
                'from_existing' => true,
            ];
        }

        // Check cache to avoid re-geocoding unchanged addresses
        $cacheIdentity = $harbor->hiswa_company_id ?: $harbor->id;
        $cacheKey = "harbor_geocode_{$cacheIdentity}_{$queryHash}";
        $cached = Cache::get($cacheKey);
        if ($cached) {
            Log::debug("[GoogleGeocode] Cache hit for harbor {$harbor->id}");
            return $cached;
        }

        try {
            $response = Http::timeout(10)->get('https://maps.googleapis.com/maps/api/geocode/json', [
                'address'  => $query,
                'region'   => 'nl',
                'language' => 'nl',
                'key'      => $this->apiKey,
            ]);

            if (!$response->successful()) {
                Log::error("[GoogleGeocode] HTTP error: {$response->status()}");
                return ['error' => "HTTP {$response->status()}"];
            }

            $data = $response->json();

            if ($data['status'] !== 'OK' || empty($data['results'])) {
                Log::warning("[GoogleGeocode] No results for harbor {$harbor->id}: {$data['status']}");
                return ['error' => $data['status']];
            }

            $result = $data['results'][0];
            $location = $result['geometry']['location'];

            // Determine confidence based on location_type
            $locationType = $result['geometry']['location_type'] ?? 'APPROXIMATE';
            $confidence = match ($locationType) {
                'ROOFTOP'              => 'HIGH',
                'RANGE_INTERPOLATED'   => 'HIGH',
                'GEOMETRIC_CENTER'     => 'MED',
                default                => 'LOW',
            };

            // Also check if result types suggest a "point of interest" (higher quality)
            $types = $result['types'] ?? [];
            if (in_array('establishment', $types) || in_array('point_of_interest', $types)) {
                $confidence = 'HIGH';
            }

            $geocodeResult = [
                'place_id'          => $result['place_id'] ?? null,
                'lat'               => $location['lat'],
                'lng'               => $location['lng'],
                'formatted_address' => $result['formatted_address'] ?? '',
                'confidence'        => $confidence,
                'maps_url'          => "https://www.google.com/maps/place/?q=place_id:" . ($result['place_id'] ?? ''),
                'components'        => $result['address_components'] ?? [],
                'query_hash'        => $queryHash,
            ];

            // Cache for 30 days
            Cache::put($cacheKey, $geocodeResult, now()->addDays(30));

            return $geocodeResult;
        } catch (\Exception $e) {
            Log::error("[GoogleGeocode] Exception for harbor {$harbor->id}: {$e->getMessage()}");
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Apply geocode results to a harbor model and save.
     */
    public function applyToHarbor(Harbor $harbor, array $geocodeResult): void
    {
        if (isset($geocodeResult['error'])) {
            $harbor->update(['needs_review' => true]);
            return;
        }

        $harbor->update([
            'gmaps_place_id'          => $geocodeResult['place_id'],
            'gmaps_formatted_address' => $geocodeResult['formatted_address'],
            'lat'                     => $geocodeResult['lat'],
            'lng'                     => $geocodeResult['lng'],
            'address_components'      => $geocodeResult['components'],
            'geocode_confidence'      => $geocodeResult['confidence'],
            'maps_url'                => $geocodeResult['maps_url'],
            'geocode_query_hash'      => $geocodeResult['query_hash'] ?? $harbor->geocode_query_hash,
            'last_geocode_at'         => isset($geocodeResult['from_existing']) ? $harbor->last_geocode_at : now(),
            'needs_review'            => $geocodeResult['confidence'] === 'LOW',
        ]);
    }
}
