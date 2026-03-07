<?php

namespace App\Services;

class CopilotFuzzyMatcher
{
    public function normalize(string $value): string
    {
        $value = trim(mb_strtolower($value));
        $value = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value) ?: $value;
        $value = preg_replace('/[^a-z0-9\s]/i', ' ', $value);
        $value = preg_replace('/\s+/', ' ', $value);
        return trim($value);
    }

    public function score(string $query, string $candidate): float
    {
        $query = $this->normalize($query);
        $candidate = $this->normalize($candidate);

        if ($query === '' || $candidate === '') {
            return 0.0;
        }

        if ($query === $candidate) {
            return 1.0;
        }

        if (str_contains($candidate, $query)) {
            return 0.9;
        }

        $distance = levenshtein($query, $candidate);
        $maxLen = max(mb_strlen($query), mb_strlen($candidate));
        if ($maxLen === 0) {
            return 0.0;
        }

        $score = 1 - ($distance / $maxLen);
        return max(0.0, min(1.0, $score));
    }
}
