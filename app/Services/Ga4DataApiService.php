<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class Ga4DataApiService
{
    public function runReport(array $payload): ?array
    {
        $propertyId = config('services.ga4.property_id');
        if (!$propertyId) {
            return null;
        }

        $token = $this->getAccessToken();
        if (!$token) {
            return null;
        }

        $cacheTtl = (int) config('services.ga4.cache_ttl', 3600);
        $cacheKey = 'ga4_report_' . sha1(json_encode($payload));

        return Cache::remember($cacheKey, $cacheTtl, function () use ($payload, $propertyId, $token) {
            try {
                $response = Http::withToken($token)
                    ->timeout(10)
                    ->post("https://analyticsdata.googleapis.com/v1beta/properties/{$propertyId}:runReport", $payload);

                if ($response->failed()) {
                    Log::warning('GA4 Data API failed', [
                        'status' => $response->status(),
                        'body' => $response->body(),
                    ]);
                    return null;
                }

                return $response->json();
            } catch (\Throwable $e) {
                Log::warning('GA4 Data API exception', ['error' => $e->getMessage()]);
                return null;
            }
        });
    }

    public function fetchEventCounts(array $eventNames, string $startDate, string $endDate, array $filters = []): array
    {
        $harborDimension = config('services.ga4.dimension_harbor_id', 'customEvent:harbor_id');

        $filterExpressions = [];
        if (!empty($eventNames)) {
            $filterExpressions[] = [
                'filter' => [
                    'fieldName' => 'eventName',
                    'inListFilter' => [
                        'values' => array_values($eventNames),
                    ],
                ],
            ];
        }

        foreach ($this->buildFilterExpressions($filters) as $filter) {
            $filterExpressions[] = $filter;
        }

        $dimensionFilter = null;
        if (count($filterExpressions) === 1) {
            $dimensionFilter = $filterExpressions[0];
        } elseif (count($filterExpressions) > 1) {
            $dimensionFilter = ['andGroup' => ['expressions' => $filterExpressions]];
        }

        $payload = [
            'dateRanges' => [[
                'startDate' => $startDate,
                'endDate' => $endDate,
            ]],
            'dimensions' => [
                ['name' => $harborDimension],
                ['name' => 'eventName'],
            ],
            'metrics' => [
                ['name' => 'eventCount'],
            ],
        ];

        if ($dimensionFilter) {
            $payload['dimensionFilter'] = $dimensionFilter;
        }

        $response = $this->runReport($payload);
        if (!$response || empty($response['rows'])) {
            return [];
        }

        $results = [];
        foreach ($response['rows'] as $row) {
            $dimensions = $row['dimensionValues'] ?? [];
            $metrics = $row['metricValues'] ?? [];
            $harborValue = $dimensions[0]['value'] ?? null;
            $eventName = $dimensions[1]['value'] ?? null;
            $count = isset($metrics[0]['value']) ? (int) $metrics[0]['value'] : 0;

            if (!$harborValue || $harborValue === '(not set)') {
                continue;
            }

            $harborId = ctype_digit($harborValue) ? (int) $harborValue : $harborValue;
            $results[$harborId][$eventName] = $count;
        }

        return $results;
    }

    public function fetchTrafficMetrics(string $startDate, string $endDate, array $filters = []): array
    {
        $harborDimension = config('services.ga4.dimension_harbor_id', 'customEvent:harbor_id');
        $payload = [
            'dateRanges' => [[
                'startDate' => $startDate,
                'endDate' => $endDate,
            ]],
            'dimensions' => [
                ['name' => $harborDimension],
            ],
            'metrics' => [
                ['name' => 'activeUsers'],
                ['name' => 'sessions'],
            ],
        ];

        $filtersExpr = $this->buildFilterExpressions($filters);
        if (count($filtersExpr) === 1) {
            $payload['dimensionFilter'] = $filtersExpr[0];
        } elseif (count($filtersExpr) > 1) {
            $payload['dimensionFilter'] = ['andGroup' => ['expressions' => $filtersExpr]];
        }

        $response = $this->runReport($payload);
        if (!$response || empty($response['rows'])) {
            return [];
        }

        $results = [];
        foreach ($response['rows'] as $row) {
            $dimensions = $row['dimensionValues'] ?? [];
            $metrics = $row['metricValues'] ?? [];
            $harborValue = $dimensions[0]['value'] ?? null;
            if (!$harborValue || $harborValue === '(not set)') {
                continue;
            }
            $harborId = ctype_digit($harborValue) ? (int) $harborValue : $harborValue;
            $results[$harborId] = [
                'active_users' => isset($metrics[0]['value']) ? (int) $metrics[0]['value'] : 0,
                'sessions' => isset($metrics[1]['value']) ? (int) $metrics[1]['value'] : 0,
            ];
        }

        return $results;
    }

    private function getAccessToken(): ?string
    {
        $cacheKey = 'ga4_access_token';

        return Cache::remember($cacheKey, 3400, function () {
            $clientEmail = config('services.ga4.client_email');
            $privateKey = config('services.ga4.private_key');

            if (!$clientEmail || !$privateKey) {
                return null;
            }

            $privateKey = str_replace("\\n", "\n", $privateKey);
            $jwt = $this->createJwt($clientEmail, $privateKey);
            if (!$jwt) {
                return null;
            }

            try {
                $response = Http::asForm()->timeout(10)->post('https://oauth2.googleapis.com/token', [
                    'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                    'assertion' => $jwt,
                ]);

                if ($response->failed()) {
                    Log::warning('GA4 auth failed', [
                        'status' => $response->status(),
                        'body' => $response->body(),
                    ]);
                    return null;
                }

                return $response->json('access_token');
            } catch (\Throwable $e) {
                Log::warning('GA4 auth exception', ['error' => $e->getMessage()]);
                return null;
            }
        });
    }

    private function createJwt(string $clientEmail, string $privateKey): ?string
    {
        $header = $this->base64UrlEncode(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
        $now = time();
        $payload = $this->base64UrlEncode(json_encode([
            'iss' => $clientEmail,
            'scope' => 'https://www.googleapis.com/auth/analytics.readonly',
            'aud' => 'https://oauth2.googleapis.com/token',
            'iat' => $now,
            'exp' => $now + 3600,
        ]));

        $data = $header . '.' . $payload;
        $signature = '';

        $ok = openssl_sign($data, $signature, $privateKey, 'sha256');
        if (!$ok) {
            Log::warning('GA4 JWT signing failed');
            return null;
        }

        return $data . '.' . $this->base64UrlEncode($signature);
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private function buildFilterExpressions(array $filters): array
    {
        $expressions = [];
        if (!empty($filters['device'])) {
            $expressions[] = [
                'filter' => [
                    'fieldName' => 'deviceCategory',
                    'stringFilter' => ['value' => $filters['device']],
                ],
            ];
        }
        if (!empty($filters['country'])) {
            $expressions[] = [
                'filter' => [
                    'fieldName' => 'country',
                    'stringFilter' => ['value' => $filters['country']],
                ],
            ];
        }
        if (!empty($filters['source'])) {
            $expressions[] = [
                'filter' => [
                    'fieldName' => 'sessionSource',
                    'stringFilter' => ['value' => $filters['source']],
                ],
            ];
        }
        if (!empty($filters['medium'])) {
            $expressions[] = [
                'filter' => [
                    'fieldName' => 'sessionMedium',
                    'stringFilter' => ['value' => $filters['medium']],
                ],
            ];
        }
        if (!empty($filters['campaign'])) {
            $expressions[] = [
                'filter' => [
                    'fieldName' => 'sessionCampaignName',
                    'stringFilter' => ['value' => $filters['campaign']],
                ],
            ];
        }

        return $expressions;
    }
}
