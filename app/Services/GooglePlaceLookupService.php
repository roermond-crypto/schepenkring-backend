<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GooglePlaceLookupService
{
    private string $apiKey;

    private array $fieldMask = [
        'displayName',
        'formattedAddress',
        'location',
        'addressComponents',
        'businessStatus',
    ];

    public function __construct()
    {
        $this->apiKey = config('services.google.maps_api_key', env('GOOGLE_MAPS_API_KEY', ''));
    }

    public function fetchByPlaceId(string $placeId): array
    {
        if ($this->apiKey === '') {
            Log::error('[PlaceLookup] No GOOGLE_MAPS_API_KEY configured');
            return ['error' => 'API key not configured'];
        }

        try {
            $url = "https://places.googleapis.com/v1/places/{$placeId}";

            $response = Http::timeout(10)
                ->withHeaders([
                    'X-Goog-Api-Key' => $this->apiKey,
                    'X-Goog-FieldMask' => implode(',', $this->fieldMask),
                    'Content-Type' => 'application/json',
                ])
                ->get($url);

            if (!$response->successful()) {
                Log::error("[PlaceLookup] HTTP error for {$placeId}: {$response->status()}", [
                    'body' => $response->body(),
                ]);
                return ['error' => "HTTP {$response->status()}"];
            }

            return $this->parseDetails($response->json());
        } catch (\Exception $e) {
            Log::error("[PlaceLookup] Exception for {$placeId}: {$e->getMessage()}");
            return ['error' => $e->getMessage()];
        }
    }

    private function parseDetails(array $data): array
    {
        $components = $data['addressComponents'] ?? [];
        $parts = $this->extractAddressParts($components);

        return [
            'place_id' => $data['id'] ?? null,
            'company_name' => $data['displayName']['text'] ?? null,
            'formatted_address' => $data['formattedAddress'] ?? null,
            'street' => $parts['street'],
            'postal_code' => $parts['postal_code'],
            'city' => $parts['city'],
            'country' => $parts['country'],
            'latitude' => $data['location']['latitude'] ?? null,
            'longitude' => $data['location']['longitude'] ?? null,
            'business_status' => $data['businessStatus'] ?? null,
            'raw' => $data,
        ];
    }

    private function extractAddressParts(array $components): array
    {
        $lookup = [];
        foreach ($components as $component) {
            $types = $component['types'] ?? [];
            foreach ($types as $type) {
                $lookup[$type] = $component['shortText'] ?? $component['longText'] ?? null;
            }
        }

        $street = trim(implode(' ', array_filter([
            $lookup['route'] ?? null,
            $lookup['street_number'] ?? null,
        ])));

        return [
            'street' => $street ?: null,
            'postal_code' => $lookup['postal_code'] ?? null,
            'city' => $lookup['locality'] ?? ($lookup['postal_town'] ?? $lookup['administrative_area_level_2'] ?? null),
            'country' => $lookup['country'] ?? null,
        ];
    }
}
