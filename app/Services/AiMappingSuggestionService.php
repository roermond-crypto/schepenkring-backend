<?php

namespace App\Services;

use App\Models\OpenMarineFieldMapping;
use App\Models\Yacht;

/**
 * Suggests an OpenMarine mapping for a yacht field that has none yet.
 * Deterministic string-similarity only — no external API call, consistent
 * with the earlier decision on Platform Configuration tooltips to prefer
 * static/computed content over a live OpenAI integration.
 *
 * The suggestion isn't invented naming-convention guesswork: our existing
 * mapped paths use plain snake_case leaf segments (e.g. 'boat.boat_type',
 * 'boat.new_or_used'), not camelCase — so a suggestion reuses that exact
 * convention rather than inventing a different style no other row uses.
 */
class AiMappingSuggestionService
{
    /**
     * @return array<int, array{schepenkring_field: string, source: string, suggested_group_label: string, suggested_openmarine_xml_path: string, confidence: int}>
     */
    public function suggestions(): array
    {
        $mappedFields = OpenMarineFieldMapping::query()
            ->whereNotNull('schepenkring_field')
            ->where('schepenkring_field', '!=', '')
            ->get(['schepenkring_field', 'group_label']);

        $groupVocabulary = $this->buildGroupVocabulary($mappedFields);
        $alreadyMapped = $mappedFields->pluck('schepenkring_field')->flip();

        $suggestions = [];
        foreach (Yacht::SUB_TABLE_MAP as $subTable => $fields) {
            foreach ($fields as $field) {
                if (isset($alreadyMapped[$field])) {
                    continue;
                }

                $suggestions[] = $this->suggestFor($field, $subTable, $groupVocabulary);
            }
        }

        usort($suggestions, fn ($a, $b) => $b['confidence'] <=> $a['confidence']);

        return $suggestions;
    }

    public function suggestFor(string $field, string $subTable, ?array $groupVocabulary = null): array
    {
        $groupVocabulary ??= $this->buildGroupVocabulary(
            OpenMarineFieldMapping::query()
                ->whereNotNull('schepenkring_field')
                ->where('schepenkring_field', '!=', '')
                ->get(['schepenkring_field', 'group_label'])
        );

        $fieldTokens = $this->tokenize($field);

        $bestGroup = null;
        $bestScore = 0.0;
        foreach ($groupVocabulary as $groupLabel => $tokens) {
            $shared = count(array_intersect($fieldTokens, $tokens));
            if ($shared === 0) {
                continue;
            }
            $score = $shared / count($fieldTokens);
            if ($score > $bestScore) {
                $bestScore = $score;
                $bestGroup = $groupLabel;
            }
        }

        if ($bestGroup !== null) {
            $groupSlug = strtolower($bestGroup);
            $leaf = $this->stripGroupPrefix($field, $groupSlug);

            return [
                'schepenkring_field' => $field,
                'source' => $subTable,
                'suggested_group_label' => $bestGroup,
                'suggested_openmarine_xml_path' => "boat.{$groupSlug}.{$leaf}",
                'confidence' => (int) round(min($bestScore, 1.0) * 90),
            ];
        }

        // No token overlap with any existing group's fields — fall back to
        // our own equipment sub-table category as a new group, a
        // structural (not text-similarity) signal, so a lower confidence.
        $groupLabel = ucfirst($subTable);
        $groupSlug = strtolower($subTable);

        return [
            'schepenkring_field' => $field,
            'source' => $subTable,
            'suggested_group_label' => $groupLabel,
            'suggested_openmarine_xml_path' => "boat.{$groupSlug}.{$field}",
            'confidence' => 55,
        ];
    }

    /**
     * @return array<string, array<int, string>>
     */
    private function buildGroupVocabulary($mappedFields): array
    {
        $vocabulary = [];
        foreach ($mappedFields as $mapping) {
            $group = $mapping->group_label ?? 'Identity';
            $vocabulary[$group] ??= [];
            $vocabulary[$group] = array_merge($vocabulary[$group], $this->tokenize($mapping->schepenkring_field));
        }

        return array_map(fn ($tokens) => array_values(array_unique($tokens)), $vocabulary);
    }

    /**
     * @return array<int, string>
     */
    private function tokenize(string $field): array
    {
        return array_values(array_filter(explode('_', strtolower($field))));
    }

    private function stripGroupPrefix(string $field, string $groupSlug): string
    {
        $prefix = $groupSlug . '_';
        if (str_starts_with($field, $prefix)) {
            return substr($field, strlen($prefix));
        }

        return $field;
    }
}
