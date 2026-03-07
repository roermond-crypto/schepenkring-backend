<?php

namespace App\Services;

use App\Models\Lead;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class LeadGeocodingService
{
    private string $apiKey;

    public function __construct()
    {
        $this->apiKey = config('services.google.maps_api_key', env('GOOGLE_MAPS_API_KEY', ''));
    }

    /**
     * @return array{place_id?:string,lat?:float,lng?:float,formatted_address?:string,confidence?:string,maps_url?:string,components?:array,query_hash?:string,error?:string}
     */
    public function geocode(Lead $lead): array
    {
        if ($this->apiKey === '') {
            Log::error('[LeadGeocode] No GOOGLE_MAPS_API_KEY configured');
            return ['error' => 'API key not configured'];
        }

        $query = $this->buildQuery($lead);
        if ($query === '') {
            return ['error' => 'Missing address'];
        }

        $queryHash = md5(mb_strtolower(trim($query)));
        if (! empty($lead->gmaps_place_id) && $lead->geocode_query_hash === $queryHash) {
            return [
                'place_id' => $lead->gmaps_place_id,
                'lat' => $lead->lat,
                'lng' => $lead->lng,
                'formatted_address' => $lead->formatted_address,
                'confidence' => $lead->confidence,
                'maps_url' => $lead->maps_url,
                'components' => $lead->address_components ?? [],
                'query_hash' => $queryHash,
                'from_existing' => true,
            ];
        }

        $cacheKey = "lead_geocode_{$lead->id}_{$queryHash}";
        $cached = Cache::get($cacheKey);
        if ($cached) {
            return $cached;
        }

        try {
            $response = Http::timeout(10)->get('https://maps.googleapis.com/maps/api/geocode/json', [
                'address' => $query,
                'region' => 'nl',
                'language' => 'nl',
                'key' => $this->apiKey,
            ]);

            if (! $response->successful()) {
                Log::error("[LeadGeocode] HTTP error: {$response->status()}");
                return ['error' => "HTTP {$response->status()}"];
            }

            $data = $response->json();
            if (($data['status'] ?? '') !== 'OK' || empty($data['results'])) {
                Log::warning("[LeadGeocode] No results for lead {$lead->id}: " . ($data['status'] ?? 'UNKNOWN'));
                return ['error' => $data['status'] ?? 'UNKNOWN'];
            }

            $result = $data['results'][0];
            $location = $result['geometry']['location'] ?? ['lat' => null, 'lng' => null];

            $locationType = $result['geometry']['location_type'] ?? 'APPROXIMATE';
            $confidence = match ($locationType) {
                'ROOFTOP', 'RANGE_INTERPOLATED' => 'HIGH',
                'GEOMETRIC_CENTER' => 'MED',
                default => 'LOW',
            };

            $types = $result['types'] ?? [];
            if (in_array('establishment', $types, true) || in_array('point_of_interest', $types, true)) {
                $confidence = 'HIGH';
            }

            $geocodeResult = [
                'place_id' => $result['place_id'] ?? null,
                'lat' => $location['lat'],
                'lng' => $location['lng'],
                'formatted_address' => $result['formatted_address'] ?? null,
                'confidence' => $confidence,
                'maps_url' => "https://www.google.com/maps/place/?q=place_id:" . ($result['place_id'] ?? ''),
                'components' => $result['address_components'] ?? [],
                'query_hash' => $queryHash,
            ];

            Cache::put($cacheKey, $geocodeResult, now()->addDays(30));

            return $geocodeResult;
        } catch (\Throwable $e) {
            Log::error("[LeadGeocode] Exception for lead {$lead->id}: {$e->getMessage()}");
            return ['error' => $e->getMessage()];
        }
    }

    public function applyToLead(Lead $lead, array $geocodeResult): void
    {
        if (isset($geocodeResult['error'])) {
            return;
        }

        $lead->update([
            'gmaps_place_id' => $geocodeResult['place_id'] ?? null,
            'formatted_address' => $geocodeResult['formatted_address'] ?? null,
            'lat' => $geocodeResult['lat'] ?? null,
            'lng' => $geocodeResult['lng'] ?? null,
            'address_components' => $geocodeResult['components'] ?? null,
            'confidence' => $geocodeResult['confidence'] ?? null,
            'maps_url' => $geocodeResult['maps_url'] ?? null,
            'geocode_query_hash' => $geocodeResult['query_hash'] ?? $lead->geocode_query_hash,
            'last_geocode_at' => isset($geocodeResult['from_existing']) ? $lead->last_geocode_at : now(),
        ]);
    }

    private function buildQuery(Lead $lead): string
    {
        return collect([
            $lead->name,
            $lead->address_line1,
            $lead->address_line2,
            $lead->postal_code,
            $lead->city,
            $lead->state,
            $lead->country,
        ])->filter()->implode(', ');
    }
}
