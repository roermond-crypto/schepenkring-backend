<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\Harbor;

class GooglePlaceDetailsService
{
    private string $apiKey;

    // Base fields to request (controls cost via field mask)
    private array $baseFieldMask = [
        'displayName',
        'formattedAddress',
        'location',
        'types',
        'internationalPhoneNumber',
        'websiteUri',
        'regularOpeningHours',
        'currentOpeningHours',
        'rating',
        'userRatingCount',
        'editorialSummary',
    ];

    public function __construct()
    {
        $this->apiKey = config('services.google.maps_api_key', env('GOOGLE_MAPS_API_KEY', ''));
    }

    /**
     * Fetch Google Place Details (New API) for a harbor.
     *
     * @return array  Parsed place details or ['error' => ...]
     */
    public function getDetails(Harbor $harbor, bool $includePhotos = false, bool $includeReviews = false): array
    {
        if (empty($this->apiKey)) {
            Log::error('[PlaceDetails] No GOOGLE_MAPS_API_KEY configured');
            return ['error' => 'API key not configured'];
        }

        if (empty($harbor->gmaps_place_id)) {
            Log::warning("[PlaceDetails] Harbor {$harbor->id} has no place_id");
            return ['error' => 'No place_id available'];
        }

        try {
            // Use the Places API (New) — Place Details endpoint
            $url = "https://places.googleapis.com/v1/places/{$harbor->gmaps_place_id}";

            $response = Http::timeout(10)
                ->withHeaders([
                    'X-Goog-Api-Key'       => $this->apiKey,
                    'X-Goog-FieldMask'     => implode(',', $this->buildFieldMask($includePhotos, $includeReviews)),
                    'Content-Type'         => 'application/json',
                ])
                ->get($url);

            if (!$response->successful()) {
                Log::error("[PlaceDetails] HTTP error for harbor {$harbor->id}: {$response->status()}", [
                    'body' => $response->body(),
                ]);
                return ['error' => "HTTP {$response->status()}"];
            }

            $data = $response->json();

            return $this->parseDetails($data);
        } catch (\Exception $e) {
            Log::error("[PlaceDetails] Exception for harbor {$harbor->id}: {$e->getMessage()}");
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Parse raw Place Details API response into normalized fields.
     */
    private function parseDetails(array $data): array
    {
        // Opening hours
        $openingHours = null;
        if (isset($data['regularOpeningHours'])) {
            $openingHours = [
                'weekday_text' => $data['regularOpeningHours']['weekdayDescriptions'] ?? [],
                'periods'      => $data['regularOpeningHours']['periods'] ?? [],
                'open_now'     => $this->isOpenNow($data['regularOpeningHours']),
                'current_weekday_text' => $data['currentOpeningHours']['weekdayDescriptions'] ?? [],
            ];
        }

        // Photos (store references, not actual images)
        $photos = [];
        if (isset($data['photos']) && is_array($data['photos'])) {
            foreach (array_slice($data['photos'], 0, 10) as $photo) {
                $photos[] = [
                    'name'                => $photo['name'] ?? '',
                    'width'               => $photo['widthPx'] ?? null,
                    'height'              => $photo['heightPx'] ?? null,
                    'authorAttributions'  => $photo['authorAttributions'] ?? [],
                ];
            }
        }

        return [
            'display_name'       => $data['displayName']['text'] ?? null,
            'formatted_address'  => $data['formattedAddress'] ?? null,
            'lat'                => $data['location']['latitude'] ?? null,
            'lng'                => $data['location']['longitude'] ?? null,
            'types'              => $data['types'] ?? [],
            'phone'              => $data['internationalPhoneNumber'] ?? null,
            'website'            => $data['websiteUri'] ?? null,
            'opening_hours'      => $openingHours,
            'rating'             => $data['rating'] ?? null,
            'rating_count'       => $data['userRatingCount'] ?? null,
            'editorial_summary'  => $data['editorialSummary']['text'] ?? null,
            'photos'             => $photos,
            'reviews'            => array_slice($data['reviews'] ?? [], 0, 3),
            'raw'                => $data,
        ];
    }

    private function buildFieldMask(bool $includePhotos, bool $includeReviews): array
    {
        $mask = $this->baseFieldMask;

        if ($includePhotos) {
            $mask[] = 'photos';
        }

        if ($includeReviews) {
            $mask[] = 'reviews';
        }

        return $mask;
    }

    /**
     * Simple check if currently open based on periods.
     */
    private function isOpenNow(array $hours): ?bool
    {
        // The API gives currentOpeningHours for live status,
        // but for regularOpeningHours we can compute from periods
        $now = now()->setTimezone('Europe/Amsterdam');
        $dayOfWeek = $now->dayOfWeek; // 0=Sunday
        $currentTime = (int) $now->format('Hi');

        $periods = $hours['periods'] ?? [];
        foreach ($periods as $period) {
            $openDay  = $period['open']['day'] ?? null;
            $openTime = (int) ($period['open']['hour'] ?? '0') * 100 + (int) ($period['open']['minute'] ?? '0');

            if ($openDay === $dayOfWeek) {
                $closeTime = isset($period['close'])
                    ? (int) ($period['close']['hour'] ?? '23') * 100 + (int) ($period['close']['minute'] ?? '59')
                    : 2359;

                if ($currentTime >= $openTime && $currentTime <= $closeTime) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Apply place details to a harbor model and save.
     */
    public function applyToHarbor(Harbor $harbor, array $details, bool $includePhotos = false): void
    {
        if (isset($details['error'])) {
            Log::warning("[PlaceDetails] Skipping harbor {$harbor->id}: {$details['error']}");
            return;
        }

        $payload = [
            'opening_hours_json'          => $details['opening_hours'],
            'rating'                      => $details['rating'],
            'rating_count'                => $details['rating_count'],
            'primary_phone'               => $details['phone'] ?? $harbor->primary_phone,
            'google_website'              => $details['website'] ?? $harbor->google_website,
            'place_details_json'          => $details['raw'],
            'last_place_details_fetch_at' => now(),
        ];

        if ($includePhotos) {
            $payload['google_photos'] = $details['photos'];
            $payload['last_place_photos_fetch_at'] = now();
        }

        $harbor->update($payload);
    }
}
