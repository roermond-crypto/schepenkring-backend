<?php

namespace App\Services;

class BoatImportValidationService
{
    private const MISSING_MARKERS = [
        '-',
        '--',
        'n/a',
        'na',
        'onbekend',
        'unknown',
    ];

    private const PLACEHOLDER_SNIPPETS = [
        'testtekst',
        'testboot',
        'placeholder',
        'lorem ipsum',
        'uitgebreide omschrijving is hier mogelijk',
        'hier meer info',
        'dit is een test',
    ];

    public function validate(array $boat): array
    {
        $issues = [];

        $identityFields = [
            'manufacturer' => $this->firstString($boat, ['make', 'manufacturer']),
            'model' => $this->firstString($boat, ['model']),
            'boat_name' => $this->firstString($boat, ['boat_name', 'title']),
        ];

        $nonEmptyIdentity = array_filter(
            $identityFields,
            static fn (?string $value): bool => $value !== null && $value !== ''
        );

        $suspiciousIdentityFields = [];

        foreach ($nonEmptyIdentity as $field => $value) {
            if ($this->looksPlaceholder($value)) {
                $suspiciousIdentityFields[] = $field;
                $issues[] = "{$field} looks like placeholder text: {$this->sample($value)}";
                continue;
            }

            if ($this->isNumericTokenSequence($value)) {
                $suspiciousIdentityFields[] = $field;
                $issues[] = "{$field} looks like a counter sequence: {$this->sample($value)}";
                continue;
            }

            if (!$this->containsLetters($value)) {
                $suspiciousIdentityFields[] = $field;

                if ($field !== 'model') {
                    $issues[] = "{$field} lacks recognizable boat text: {$this->sample($value)}";
                }
            }
        }

        $combinedIdentity = trim(implode(' ', $nonEmptyIdentity));
        if ($combinedIdentity === '' || !$this->containsLetters($combinedIdentity)) {
            $issues[] = 'boat identity does not contain a recognizable name';
        } elseif (count(array_unique($suspiciousIdentityFields)) >= 2) {
            $issues[] = 'multiple identity fields look like counters or placeholder values';
        }

        $description = $this->firstString($boat, ['description', 'short_description_nl']);
        if ($description !== null) {
            if ($this->looksPlaceholder($description)) {
                $issues[] = 'description contains placeholder text';
            } elseif ($this->isNumericTokenSequence($description)) {
                $issues[] = 'description looks like a counter sequence';
            }
        }

        $year = $this->firstInt($boat, ['year']);
        $maxYear = (int) date('Y') + 1;
        if ($year !== null && ($year < 1800 || $year > $maxYear)) {
            $issues[] = "year is outside plausible bounds: {$year}";
        }

        $this->validateDimension($issues, 'length', $this->firstFloat($boat, ['length', 'loa']), 2.0, 100.0);
        $this->validateDimension($issues, 'beam', $this->firstFloat($boat, ['beam']), 0.5, 20.0);
        $this->validateDimension($issues, 'draft', $this->firstFloat($boat, ['draft']), 0.1, 10.0);

        $location = $this->firstString($boat, ['location', 'where', 'vessel_lying']);
        if ($location !== null && ($this->isNumericTokenSequence($location) || !$this->containsLetters($location))) {
            $issues[] = "location looks like a code instead of a place: {$this->sample($location)}";
        }

        $berths = $this->firstString($boat, ['berths']);
        if ($berths !== null && $this->looksPlaceholderBerths($berths)) {
            $issues[] = "berths looks corrupted: {$this->sample($berths)}";
        }

        $cabins = $this->firstInt($boat, ['cabins']);
        if ($cabins !== null && ($cabins < 0 || $cabins > 20)) {
            $issues[] = "cabins is outside plausible bounds: {$cabins}";
        }

        return [
            'valid' => empty($issues),
            'issues' => array_values(array_unique($issues)),
        ];
    }

    private function validateDimension(array &$issues, string $field, ?float $value, float $min, float $max): void
    {
        if ($value === null) {
            return;
        }

        if ($value < $min || $value > $max) {
            $issues[] = "{$field} is outside plausible bounds: {$value}";
        }
    }

    private function looksPlaceholder(string $value): bool
    {
        $normalized = $this->normalizeText($value);

        foreach (self::PLACEHOLDER_SNIPPETS as $snippet) {
            if (str_contains($normalized, $snippet)) {
                return true;
            }
        }

        return false;
    }

    private function looksPlaceholderBerths(string $value): bool
    {
        $normalized = $this->normalizeText($value);

        if (preg_match('/\b\d{3}\b/u', $normalized) === 1) {
            return true;
        }

        return $this->isNumericTokenSequence($value);
    }

    private function containsLetters(string $value): bool
    {
        return preg_match('/\p{L}/u', $value) === 1;
    }

    private function isNumericTokenSequence(string $value): bool
    {
        $tokens = preg_split('/\s+/u', trim($value)) ?: [];
        if (count($tokens) < 2) {
            return false;
        }

        foreach ($tokens as $token) {
            if (preg_match('/^\d+[a-z]?$/iu', $token) !== 1) {
                return false;
            }
        }

        return true;
    }

    private function firstString(array $boat, array $keys): ?string
    {
        foreach ($keys as $key) {
            if (!array_key_exists($key, $boat)) {
                continue;
            }

            $value = $boat[$key];
            if (!is_scalar($value)) {
                continue;
            }

            $string = trim((string) $value);
            if ($string === '' || $this->isMissingMarker($string)) {
                continue;
            }

            return $string;
        }

        return null;
    }

    private function isMissingMarker(string $value): bool
    {
        return in_array($this->lower(trim($value)), self::MISSING_MARKERS, true);
    }

    private function firstFloat(array $boat, array $keys): ?float
    {
        $value = $this->firstString($boat, $keys);
        if ($value === null) {
            return null;
        }

        $normalized = preg_replace('/[^0-9,.\-]/', '', $value);
        if ($normalized === null || $normalized === '' || $normalized === '-' || $normalized === '.' || $normalized === ',') {
            return null;
        }

        if (str_contains($normalized, ',') && str_contains($normalized, '.')) {
            if (strrpos($normalized, ',') > strrpos($normalized, '.')) {
                $normalized = str_replace('.', '', $normalized);
                $normalized = str_replace(',', '.', $normalized);
            } else {
                $normalized = str_replace(',', '', $normalized);
            }
        } elseif (str_contains($normalized, ',')) {
            $normalized = str_replace(',', '.', $normalized);
        }

        return is_numeric($normalized) ? (float) $normalized : null;
    }

    private function firstInt(array $boat, array $keys): ?int
    {
        $float = $this->firstFloat($boat, $keys);
        if ($float === null) {
            return null;
        }

        return (int) round($float);
    }

    private function normalizeText(string $value): string
    {
        $normalized = preg_replace('/\s+/u', ' ', trim($value));
        if ($normalized === null) {
            $normalized = trim($value);
        }

        return $this->lower($normalized);
    }

    private function sample(string $value, int $limit = 60): string
    {
        $trimmed = trim($value);
        if ($this->length($trimmed) <= $limit) {
            return $trimmed;
        }

        return rtrim($this->slice($trimmed, $limit - 3)) . '...';
    }

    private function lower(string $value): string
    {
        return function_exists('mb_strtolower') ? mb_strtolower($value) : strtolower($value);
    }

    private function length(string $value): int
    {
        return function_exists('mb_strlen') ? mb_strlen($value) : strlen($value);
    }

    private function slice(string $value, int $length): string
    {
        return function_exists('mb_substr') ? mb_substr($value, 0, $length) : substr($value, 0, $length);
    }
}
