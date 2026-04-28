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

            return ['error' => 'Google Maps is not configured on the backend.'];
        }

        try {
            $newResponse = Http::timeout(10)
                ->withHeaders([
                    'X-Goog-Api-Key' => $this->apiKey,
                    'X-Goog-FieldMask' => implode(',', $this->fieldMask),
                    'Content-Type' => 'application/json',
                    'Referer' => config('app.url', 'http://localhost'),
                ])
                ->get($this->buildPlacesDetailsUrl($placeId));

            if ($newResponse->successful()) {
                return $this->parseDetails($newResponse->json(), $placeId);
            }

            Log::error("[PlaceLookup] HTTP error for {$placeId}: {$newResponse->status()}", [
                'body' => $newResponse->body(),
            ]);

            $legacyResponse = Http::timeout(10)
                ->get('https://maps.googleapis.com/maps/api/place/details/json', [
                    'place_id' => $this->stripPlacesPrefix($placeId),
                    'fields' => 'name,formatted_address,address_component,geometry',
                    'language' => 'en',
                    'key' => $this->apiKey,
                ]);

            if ($legacyResponse->successful() && ($legacyResponse->json('status') === 'OK')) {
                return $this->parseLegacyDetails($legacyResponse->json('result', []), $placeId);
            }

            Log::error("[PlaceLookup] legacy details failed for {$placeId}: {$legacyResponse->status()}", [
                'body' => $legacyResponse->body(),
            ]);

            return ['error' => $this->buildFriendlyError($newResponse->body(), $legacyResponse->body())];
        } catch (\Throwable $e) {
            Log::error("[PlaceLookup] Exception for {$placeId}: {$e->getMessage()}");

            return ['error' => 'Google Maps address lookup is temporarily unavailable.'];
        }
    }

    public function searchPredictions(string $query): array
    {
        if ($this->apiKey === '') {
            Log::error('[PlaceLookup] No GOOGLE_MAPS_API_KEY configured for autocomplete');

            return [
                'items' => [],
                'error' => 'Google Maps autocomplete is not configured on the backend.',
            ];
        }

        try {
            $response = Http::timeout(10)
                ->withHeaders([
                    'X-Goog-Api-Key' => $this->apiKey,
                    'X-Goog-FieldMask' => 'suggestions.placePrediction.placeId,suggestions.placePrediction.text,suggestions.placePrediction.structuredFormat',
                    'Content-Type' => 'application/json',
                    'Referer' => config('app.url', 'http://localhost'),
                ])
                ->post('https://places.googleapis.com/v1/places:autocomplete', [
                    'input' => $query,
                    'languageCode' => 'en',
                ]);

            if ($response->successful()) {
                $items = collect($response->json('suggestions', []))
                    ->map(function (array $suggestion) {
                        $prediction = $suggestion['placePrediction'] ?? [];
                        $structured = $prediction['structuredFormat'] ?? [];
                        $text = $prediction['text'] ?? [];

                        return [
                            'place_id' => $prediction['placeId'] ?? null,
                            'main_text' => $structured['mainText']['text'] ?? null,
                            'secondary_text' => $structured['secondaryText']['text'] ?? null,
                            'description' => $text['text'] ?? null,
                        ];
                    })
                    ->filter(fn (array $item) => filled($item['place_id']))
                    ->values()
                    ->all();

                if (!empty($items)) {
                    return ['items' => $items, 'error' => null];
                }
            } else {
                Log::error("[PlaceLookup] autocomplete failed: {$response->status()}", [
                    'body' => $response->body(),
                ]);
            }

            $legacyResponse = Http::timeout(10)
                ->get('https://maps.googleapis.com/maps/api/place/autocomplete/json', [
                    'input' => $query,
                    'types' => 'geocode',
                    'language' => 'en',
                    'key' => $this->apiKey,
                ]);

            if ($legacyResponse->successful() && ($legacyResponse->json('status') === 'OK')) {
                $items = collect($legacyResponse->json('predictions', []))
                    ->map(function (array $prediction) {
                        $structured = $prediction['structured_formatting'] ?? [];

                        return [
                            'place_id' => $prediction['place_id'] ?? null,
                            'main_text' => $structured['main_text'] ?? null,
                            'secondary_text' => $structured['secondary_text'] ?? null,
                            'description' => $prediction['description'] ?? null,
                        ];
                    })
                    ->filter(fn (array $item) => filled($item['place_id']))
                    ->values()
                    ->all();

                if (!empty($items)) {
                    return ['items' => $items, 'error' => null];
                }
            } else {
                Log::error("[PlaceLookup] legacy autocomplete failed: {$legacyResponse->status()}", [
                    'body' => $legacyResponse->body(),
                ]);
            }

            return [
                'items' => [],
                'error' => $this->buildFriendlyError($response->body(), $legacyResponse->body()),
            ];
        } catch (\Throwable $e) {
            Log::error("[PlaceLookup] autocomplete exception: {$e->getMessage()}");

            return [
                'items' => [],
                'error' => 'Google Maps autocomplete is temporarily unavailable. Please try again in a moment.',
            ];
        }
    }

    private function buildPlacesDetailsUrl(string $placeId): string
    {
        return 'https://places.googleapis.com/v1/places/' . $this->stripPlacesPrefix($placeId);
    }

    private function normalizeForMatch(?string $value): string
    {
        $value = mb_strtolower(trim((string) $value));
        $value = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $value);

        return trim((string) $value);
    }

    private function stripPlacesPrefix(string $placeId): string
    {
        return str_starts_with($placeId, 'places/')
            ? substr($placeId, 7)
            : $placeId;
    }

    private function parseDetails(array $data, string $placeId): array
    {
        $components = $data['addressComponents'] ?? [];
        $parts = $this->extractAddressParts($components);

        return [
            'place_id' => $this->stripPlacesPrefix($data['id'] ?? $placeId),
            'company_name' => $data['displayName']['text'] ?? null,
            'formatted_address' => $data['formattedAddress'] ?? null,
            'street' => $parts['street'],
            'house_number' => $parts['house_number'],
            'postal_code' => $parts['postal_code'],
            'city' => $parts['city'],
            'region' => $parts['region'],
            'country' => $parts['country'],
            'country_code' => $parts['country_code'],
            'latitude' => $data['location']['latitude'] ?? null,
            'longitude' => $data['location']['longitude'] ?? null,
            'business_status' => $data['businessStatus'] ?? null,
            'raw' => $data,
        ];
    }

    private function parseLegacyDetails(array $data, string $placeId): array
    {
        $components = $data['address_components'] ?? [];
        $parts = $this->extractAddressParts($components, true);

        return [
            'place_id' => $this->stripPlacesPrefix($placeId),
            'company_name' => $data['name'] ?? null,
            'formatted_address' => $data['formatted_address'] ?? null,
            'street' => $parts['street'],
            'house_number' => $parts['house_number'],
            'postal_code' => $parts['postal_code'],
            'city' => $parts['city'],
            'region' => $parts['region'],
            'country' => $parts['country'],
            'country_code' => $parts['country_code'],
            'latitude' => $data['geometry']['location']['lat'] ?? null,
            'longitude' => $data['geometry']['location']['lng'] ?? null,
            'business_status' => null,
            'raw' => $data,
        ];
    }

    private function extractAddressParts(array $components, bool $legacy = false): array
    {
        $lookup = [];

        foreach ($components as $component) {
            $types = $component['types'] ?? [];

            foreach ($types as $type) {
                $value = $legacy
                    ? ($component['short_name'] ?? $component['long_name'] ?? null)
                    : ($component['shortText'] ?? $component['longText'] ?? null);

                $lookup[$type] = $value;

                if ($type === 'country' && $legacy) {
                    $lookup['country_long'] = $component['long_name'] ?? null;
                }
            }
        }

        $street = trim((string) ($lookup['route'] ?? ''));
        $houseNumber = trim((string) ($lookup['street_number'] ?? ''));

        return [
            'street' => $street ?: null,
            'house_number' => $houseNumber ?: null,
            'postal_code' => $lookup['postal_code'] ?? null,
            'city' => $lookup['locality'] ?? ($lookup['postal_town'] ?? $lookup['administrative_area_level_2'] ?? null),
            'region' => $lookup['administrative_area_level_1'] ?? null,
            'country' => $legacy ? ($lookup['country_long'] ?? $lookup['country'] ?? null) : ($lookup['country'] ?? null),
            'country_code' => strtoupper((string) ($lookup['country'] ?? '')) ?: null,
        ];
    }

    private function buildFriendlyError(?string $primaryBody, ?string $fallbackBody = null): string
    {
        $combined = strtolower(trim(($primaryBody ?? '') . ' ' . ($fallbackBody ?? '')));

        if (str_contains($combined, 'api_key_service_blocked') || str_contains($combined, 'permission_denied')) {
            return 'Google Maps Places access is blocked for the current API key. Enable Places API access for this key or remove the API restriction.';
        }

        if (str_contains($combined, 'api key not valid')) {
            return 'The configured Google Maps API key is invalid.';
        }

        return 'Google Maps place lookup failed. Check the API key configuration and Places API access.';
    }
}
