<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class YachtShiftFeedMatcherService
{
    private const CACHE_TTL_SECONDS = 1800;

    public function matchAndBuildConsensus(array $formValues, ?string $hintText, array $feedUrls): array
    {
        $warnings = [];
        $boatsBySource = [];

        foreach ($feedUrls as $url) {
            $boats = $this->fetchFeedBoats($url);
            if ($boats === null) {
                $warnings[] = "Failed to fetch YachtShift feed: {$url}";
                continue;
            }
            $boatsBySource[$url] = $boats;
        }

        if (empty($boatsBySource)) {
            return [
                'consensus_values' => [],
                'field_confidence' => [],
                'field_sources' => [],
                'top_matches' => [],
                'warnings' => $warnings,
            ];
        }

        $candidates = [];
        foreach ($boatsBySource as $sourceUrl => $boats) {
            foreach ($boats as $boat) {
                $score = $this->scoreBoatMatch($boat, $formValues, $hintText);
                if ($score < 20) {
                    continue;
                }

                $candidates[] = [
                    'source_url' => $sourceUrl,
                    'score' => $score,
                    'boat' => $boat,
                ];
            }
        }

        usort($candidates, fn ($a, $b) => $b['score'] <=> $a['score']);
        $topMatches = array_slice($candidates, 0, 5);

        if (empty($topMatches)) {
            $warnings[] = 'No high-confidence feed matches found for this boat.';
            return [
                'consensus_values' => [],
                'field_confidence' => [],
                'field_sources' => [],
                'top_matches' => [],
                'warnings' => $warnings,
            ];
        }

        [$consensusValues, $fieldConfidence, $fieldSources] = $this->buildFieldConsensus($topMatches);

        return [
            'consensus_values' => $consensusValues,
            'field_confidence' => $fieldConfidence,
            'field_sources' => $fieldSources,
            'top_matches' => $topMatches,
            'warnings' => $warnings,
        ];
    }

    private function fetchFeedBoats(string $url): ?array
    {
        $cacheKey = 'ai_pipeline_yachtshift_feed_' . md5($url);

        return Cache::remember($cacheKey, self::CACHE_TTL_SECONDS, function () use ($url) {
            try {
                $response = Http::timeout(30)->get($url);
                if (!$response->successful()) {
                    Log::warning('[AI Pipeline] Feed fetch failed', ['url' => $url, 'status' => $response->status()]);
                    return null;
                }

                $xml = simplexml_load_string($response->body(), 'SimpleXMLElement', LIBXML_NOCDATA);
                if (!$xml) {
                    Log::warning('[AI Pipeline] Feed XML parse failed', ['url' => $url]);
                    return null;
                }

                $boats = [];
                foreach ($xml->broker->adverts->advert ?? [] as $advert) {
                    $parsed = $this->parseAdvert($advert);
                    if (!empty($parsed)) {
                        $boats[] = $parsed;
                    }
                }

                return $boats;
            } catch (\Throwable $e) {
                Log::warning('[AI Pipeline] Feed fetch exception', ['url' => $url, 'error' => $e->getMessage()]);
                return null;
            }
        });
    }

    private function parseAdvert(\SimpleXMLElement $advert): array
    {
        $features = $advert->advert_features;
        $boatFeatures = $advert->boat_features;

        $boat = [
            'manufacturer' => $this->trimOrNull((string) ($features->manufacturer ?? '')),
            'model' => $this->trimOrNull((string) ($features->model ?? '')),
            'boat_type' => $this->trimOrNull((string) ($features->boat_type ?? '')),
            'boat_category' => $this->trimOrNull((string) ($features->boat_category ?? '')),
            'new_or_used' => $this->trimOrNull((string) ($features->new_or_used ?? '')),
            'boat_name' => $this->getFeature($boatFeatures, 'boat_name'),
            'year' => $this->toNumber($this->getFeature($boatFeatures, 'year', 'build')),
            'price' => $this->toNumber((string) ($features->asking_price ?? '')),
            'loa' => $this->getDimensionMeters($boatFeatures, 'loa'),
            'beam' => $this->getDimensionMeters($boatFeatures, 'beam'),
            'draft' => $this->getDimensionMeters($boatFeatures, 'draft'),
            'hull_colour' => $this->getFeature($boatFeatures, 'hull_colour', 'build'),
            'hull_construction' => $this->getFeature($boatFeatures, 'hull_construction', 'build'),
            'hull_type' => $this->getFeature($boatFeatures, 'hull_type', 'build'),
            'engine_manufacturer' => $this->getFeature($boatFeatures, 'engine_manufacturer', 'engine'),
            'fuel' => $this->getFeature($boatFeatures, 'fuel', 'engine'),
            'horse_power' => $this->toNumber($this->getFeature($boatFeatures, 'horse_power', 'engine')),
            'cabins' => $this->toNumber($this->getFeature($boatFeatures, 'cabins', 'accommodation')),
            'berths' => $this->toNumber($this->getFeature($boatFeatures, 'berths', 'accommodation')),
            'where' => $this->trimOrNull((string) ($features->vessel_lying ?? '')),
        ];

        return array_filter($boat, fn ($v) => $v !== null && $v !== '');
    }

    private function buildFieldConsensus(array $topMatches): array
    {
        $fields = [
            'manufacturer', 'model', 'boat_name', 'boat_type', 'boat_category', 'new_or_used',
            'year', 'price', 'loa', 'beam', 'draft', 'hull_colour', 'hull_construction', 'hull_type',
            'engine_manufacturer', 'fuel', 'horse_power', 'cabins', 'berths', 'where',
        ];

        $consensusValues = [];
        $fieldConfidence = [];
        $fieldSources = [];

        foreach ($fields as $field) {
            $buckets = [];
            $totalWeight = 0.0;

            foreach (array_slice($topMatches, 0, 3) as $match) {
                $value = $match['boat'][$field] ?? null;
                if ($value === null || $value === '') {
                    continue;
                }

                $bucketKey = is_numeric($value)
                    ? (string) round((float) $value, $field === 'price' ? 0 : 2)
                    : mb_strtolower(trim((string) $value));

                if (!isset($buckets[$bucketKey])) {
                    $buckets[$bucketKey] = [
                        'value' => $value,
                        'weight' => 0.0,
                        'source_url' => $match['source_url'],
                    ];
                }

                $weight = max(0.0, ((float) $match['score']) / 100.0);
                $buckets[$bucketKey]['weight'] += $weight;
                $totalWeight += $weight;
            }

            if (empty($buckets) || $totalWeight <= 0) {
                continue;
            }

            usort($buckets, fn ($a, $b) => $b['weight'] <=> $a['weight']);
            $winner = $buckets[0];
            $dominance = $winner['weight'] / $totalWeight;

            if ($dominance < 0.45) {
                continue;
            }

            $confidence = min(0.95, 0.60 + ($dominance * 0.35));

            $consensusValues[$field] = $winner['value'];
            $fieldConfidence[$field] = round($confidence, 2);
            $fieldSources[$field] = $winner['source_url'];
        }

        return [$consensusValues, $fieldConfidence, $fieldSources];
    }

    private function scoreBoatMatch(array $boat, array $formValues, ?string $hintText): int
    {
        $score = 0;

        $score += $this->scoreTextPair($formValues['manufacturer'] ?? null, $boat['manufacturer'] ?? null, 30);
        $score += $this->scoreTextPair($formValues['model'] ?? null, $boat['model'] ?? null, 28);
        $score += $this->scoreTextPair($formValues['boat_name'] ?? null, $boat['boat_name'] ?? null, 18);
        $score += $this->scoreTextPair($formValues['boat_type'] ?? null, $boat['boat_type'] ?? null, 10);

        $score += $this->scoreNumericPair($formValues['year'] ?? null, $boat['year'] ?? null, 12, 1);
        $score += $this->scoreNumericPair($formValues['loa'] ?? null, $boat['loa'] ?? null, 12, 1.5);

        $hint = $this->normalizeText((string) ($hintText ?? ''));
        if ($hint !== '') {
            $candidateText = $this->normalizeText(implode(' ', array_filter([
                $boat['manufacturer'] ?? null,
                $boat['model'] ?? null,
                $boat['boat_name'] ?? null,
                $boat['boat_type'] ?? null,
            ])));

            if ($candidateText !== '') {
                similar_text($hint, $candidateText, $percent);
                $score += (int) round(min(8, ($percent / 100) * 8));
            }
        }

        return min(100, max(0, $score));
    }

    private function scoreTextPair($left, $right, int $max): int
    {
        $l = $this->normalizeText((string) ($left ?? ''));
        $r = $this->normalizeText((string) ($right ?? ''));

        if ($l === '' || $r === '') {
            return 0;
        }

        if ($l === $r) {
            return $max;
        }

        if (str_contains($l, $r) || str_contains($r, $l)) {
            return (int) round($max * 0.6);
        }

        similar_text($l, $r, $percent);
        if ($percent >= 85) {
            return (int) round($max * 0.45);
        }
        if ($percent >= 70) {
            return (int) round($max * 0.25);
        }

        return 0;
    }

    private function scoreNumericPair($left, $right, int $max, float $tolerance): int
    {
        $l = $this->toFloat($left);
        $r = $this->toFloat($right);

        if ($l === null || $r === null) {
            return 0;
        }

        $diff = abs($l - $r);
        if ($diff === 0.0) {
            return $max;
        }

        if ($diff <= $tolerance) {
            return (int) round($max * 0.65);
        }

        if ($diff <= ($tolerance * 2)) {
            return (int) round($max * 0.35);
        }

        return 0;
    }

    private function getFeature(?\SimpleXMLElement $boatFeatures, string $name, ?string $section = null): ?string
    {
        if (!$boatFeatures) {
            return null;
        }

        $parent = $section ? ($boatFeatures->{$section} ?? $boatFeatures) : $boatFeatures;

        foreach ($parent->item ?? [] as $item) {
            if ((string) ($item->attributes()['name'] ?? '') === $name) {
                return $this->trimOrNull((string) $item);
            }
        }

        return null;
    }

    private function getDimensionMeters(?\SimpleXMLElement $boatFeatures, string $name): ?float
    {
        if (!$boatFeatures || !$boatFeatures->dimensions) {
            return null;
        }

        foreach ($boatFeatures->dimensions->item ?? [] as $item) {
            if ((string) ($item->attributes()['name'] ?? '') !== $name) {
                continue;
            }

            $value = $this->toFloat((string) $item);
            if ($value === null) {
                return null;
            }

            $unit = strtolower((string) ($item->attributes()['unit'] ?? 'centimetres'));
            if (str_contains($unit, 'cent')) {
                return round($value / 100, 2);
            }

            return round($value, 2);
        }

        return null;
    }

    private function toNumber(?string $value)
    {
        $float = $this->toFloat($value);
        if ($float === null) {
            return null;
        }

        if (floor($float) == $float) {
            return (int) $float;
        }

        return round($float, 2);
    }

    private function toFloat($value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            return (float) $value;
        }

        $raw = trim((string) $value);
        if ($raw === '') {
            return null;
        }

        $clean = preg_replace('/[^0-9.,-]/', '', $raw);
        if ($clean === '') {
            return null;
        }

        if (str_contains($clean, ',') && str_contains($clean, '.')) {
            $clean = str_replace(',', '', $clean);
        } else {
            $clean = str_replace(',', '.', $clean);
        }

        return is_numeric($clean) ? (float) $clean : null;
    }

    private function normalizeText(string $value): string
    {
        $value = mb_strtolower(trim($value));
        $value = preg_replace('/[^\p{L}\p{N}\s]+/u', ' ', $value);
        $value = preg_replace('/\s+/', ' ', (string) $value);
        return trim((string) $value);
    }

    private function trimOrNull(?string $value): ?string
    {
        $trimmed = trim((string) $value);
        return $trimmed === '' ? null : $trimmed;
    }
}
