<?php

namespace App\Services;

use App\Models\Harbor;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OutscraperEnrichmentService
{
    public function enrichByPlaceId(Harbor $harbor): array
    {
        if (!config('services.outscraper.enabled')) {
            return ['error' => 'Outscraper enrichment is disabled'];
        }

        $apiKey = (string) config('services.outscraper.api_key');
        if (empty($apiKey)) {
            return ['error' => 'Missing OUTSCRAPER_API_KEY'];
        }

        if (empty($harbor->gmaps_place_id)) {
            return ['error' => 'No place_id available'];
        }

        try {
            $baseUrl = rtrim((string) config('services.outscraper.base_url'), '/');

            $response = Http::timeout(20)
                ->withHeaders(['X-API-KEY' => $apiKey])
                ->post("{$baseUrl}/maps/search-v3", [
                    'query' => "place_id:{$harbor->gmaps_place_id}",
                    'limit' => 1,
                    'language' => 'nl',
                    'region' => 'nl',
                ]);

            if (!$response->successful()) {
                return ['error' => "HTTP {$response->status()}", 'body' => $response->json() ?: $response->body()];
            }

            $raw = $response->json();
            $first = $raw['data'][0] ?? null;
            if (!$first) {
                return ['error' => 'No third-party data returned'];
            }

            return [
                'raw' => $raw,
                'email' => $first['email_1'] ?? $first['email'] ?? null,
                'website' => $first['site'] ?? null,
                'phone' => $first['phone_1'] ?? $first['phone'] ?? null,
                'socials' => array_filter([
                    'facebook' => $first['facebook'] ?? null,
                    'instagram' => $first['instagram'] ?? null,
                    'linkedin' => $first['linkedin'] ?? null,
                    'youtube' => $first['youtube'] ?? null,
                ]),
            ];
        } catch (\Throwable $e) {
            Log::warning('[Outscraper] Enrichment failed', [
                'harbor_id' => $harbor->id,
                'message' => $e->getMessage(),
            ]);

            return ['error' => $e->getMessage()];
        }
    }

    public function applyToHarbor(Harbor $harbor, array $enrichment): void
    {
        if (isset($enrichment['error'])) {
            return;
        }

        $payload = [
            'third_party_enrichment' => [
                'source' => 'outscraper',
                'fetched_at' => now()->toIso8601String(),
                'data' => $enrichment['raw'] ?? [],
                'socials' => $enrichment['socials'] ?? [],
            ],
            'last_third_party_enrichment_at' => now(),
        ];

        if (empty($harbor->email) && !empty($enrichment['email'])) {
            $payload['email'] = $enrichment['email'];
        }

        if (empty($harbor->google_website) && empty($harbor->website) && !empty($enrichment['website'])) {
            $payload['website'] = $enrichment['website'];
        }

        if (empty($harbor->primary_phone) && empty($harbor->phone) && !empty($enrichment['phone'])) {
            $payload['phone'] = $enrichment['phone'];
        }

        $harbor->update($payload);
    }
}
