<?php

namespace App\Services;

use App\Models\Faq;
use App\Support\CopilotLanguage;
use Carbon\CarbonInterface;

class CopilotFaqService
{
    public function __construct(
        private CopilotMemoryService $memory,
        private CopilotFuzzyMatcher $matcher,
        private CopilotLanguage $language
    ) {
    }

    /**
     * @param  int|array<int>|null  $locationScope
     * @return array{answers:array<int, array<string, mixed>>,trace:array<string, mixed>,confidence:float,strategy:string}
     */
    public function answer(string $query, int|array|null $locationScope = null, array $context = [], int $limit = 3): array
    {
        $query = trim($query);
        if ($query === '') {
            return [
                'answers' => [],
                'trace' => [
                    'strategy' => 'empty',
                    'used_fallback' => false,
                    'matched_items' => [],
                ],
                'confidence' => 0.0,
                'strategy' => 'empty',
            ];
        }

        $language = $this->language->normalize($context['language'] ?? null);
        $ranked = $this->rankCandidates($query, $this->retrieveCandidates($query, $locationScope, $context), $language);
        $maxSources = max(1, min($limit, (int) config('copilot.knowledge.max_sources', 3)));

        $selectedSources = array_slice(array_values(array_filter(
            $ranked,
            fn (array $candidate) => (float) ($candidate['final_score'] ?? 0.0) >= (float) config('copilot.knowledge.minimum_match_score', 0.52)
        )), 0, $maxSources);

        $top = $selectedSources[0] ?? null;
        $second = $selectedSources[1] ?? null;
        $topScore = (float) ($top['final_score'] ?? 0.0);
        $scoreGap = $top ? ($topScore - (float) ($second['final_score'] ?? 0.0)) : 0.0;

        $strongThreshold = (float) config('copilot.knowledge.strong_match_score', 0.82);
        $mergeThreshold = (float) config('copilot.knowledge.merge_match_score', 0.68);
        $strongMargin = (float) config('copilot.knowledge.strong_margin', 0.05);

        if ($top && $topScore >= $strongThreshold && $scoreGap >= $strongMargin) {
            $strategy = 'faq_primary';
            $answerText = trim((string) $top['answer']);
            $usedSources = [$top];
        } elseif (count($selectedSources) > 1 && $topScore >= $mergeThreshold) {
            $strategy = 'faq_merge';
            $answerText = $this->mergeAnswers($selectedSources);
            $usedSources = $selectedSources;
        } elseif ($top) {
            $strategy = 'low_confidence';
            $answerText = trim((string) $top['answer']) . "\n\n" . $this->language->translate('knowledge_low_confidence', $language ?? 'en');
            $usedSources = [$top];
        } else {
            $strategy = 'no_match';
            $answerText = $this->language->translate('knowledge_not_found', $language ?? 'en');
            $usedSources = [];
        }

        $confidence = round($topScore, 3);
        $confidenceLabel = $this->confidenceLabel($confidence);
        $question = $top['question'] ?? $query;
        $category = $top['category'] ?? 'Knowledge Base';

        $answers = [[
            'question' => $question,
            'answer' => $answerText,
            'category' => $category,
            'source' => $top ? 'faq' : 'fallback',
            'source_type' => $top['source_type'] ?? ($top ? 'faq' : 'fallback'),
            'strategy' => $strategy,
            'confidence' => $confidence,
            'confidence_label' => $confidenceLabel,
            'used_fallback' => empty($usedSources),
            'sources' => array_map(fn (array $source) => $this->formatSource($source), $usedSources),
        ]];

        return [
            'answers' => $answers,
            'trace' => [
                'strategy' => $strategy,
                'confidence' => $confidence,
                'confidence_label' => $confidenceLabel,
                'used_fallback' => empty($usedSources),
                'filters' => $this->traceFilters($locationScope, $context, $language),
                'used_source_ids' => array_values(array_filter(array_map(fn (array $source) => $source['id'] ?? null, $usedSources))),
                'matched_items' => array_map(fn (array $candidate) => $this->formatTraceMatch($candidate), array_slice($ranked, 0, max(5, $maxSources))),
            ],
            'confidence' => $confidence,
            'strategy' => $strategy,
        ];
    }

    /**
     * @param  int|array<int>|null  $locationScope
     * @return array<int, array<string, mixed>>
     */
    public function search(string $query, int|array|null $locationScope = null, int $limit = 2, array $context = []): array
    {
        return array_map(
            fn (array $source) => [
                'id' => $source['id'],
                'question' => $source['question'],
                'answer' => $source['answer'],
                'category' => $source['category'],
                'location_id' => $source['location_id'],
                'score' => $source['final_score'] ?? $source['semantic_score'] ?? 0.0,
            ],
            array_slice($this->rankCandidates(
                trim($query),
                $this->retrieveCandidates($query, $locationScope, $context),
                $this->language->normalize($context['language'] ?? null)
            ), 0, $limit)
        );
    }

    /**
     * @param  int|array<int>|null  $locationScope
     * @return array<int, array<string, mixed>>
     */
    private function retrieveCandidates(string $query, int|array|null $locationScope, array $context): array
    {
        $limit = max((int) config('copilot.knowledge.top_k', 8), 3);
        $pineconeMatches = $this->searchPinecone($query, $locationScope, $context, $limit);
        $pineconeFaqIds = array_values(array_unique(array_filter(array_map(
            fn (array $match) => isset($match['faq_id']) ? (int) $match['faq_id'] : null,
            $pineconeMatches
        ))));

        if ($pineconeFaqIds === []) {
            $rowsById = collect();
        } else {
            $rowsByIdQuery = Faq::query()
                ->whereNull('deprecated_at');
            $rowsByIdQuery = $this->scopeLocation($rowsByIdQuery, $locationScope);
            $rowsByIdQuery = $this->applyContextFilters($rowsByIdQuery, $context, $this->language->normalize($context['language'] ?? null));
            $rowsById = $rowsByIdQuery
                ->whereIn('id', $pineconeFaqIds)
                ->get()
                ->keyBy('id');
        }

        $candidates = [];

        foreach ($pineconeMatches as $match) {
            $faqId = isset($match['faq_id']) ? (int) $match['faq_id'] : null;
            $row = $faqId ? $rowsById->get($faqId) : null;

            if ($faqId && ! $row) {
                continue;
            }

            $candidates[] = $this->formatCandidate(
                $row,
                $match,
                'pinecone'
            );
        }

        $fallbackRows = Faq::query()
            ->whereNull('deprecated_at');
        $fallbackRows = $this->scopeLocation($fallbackRows, $locationScope);
        $fallbackRows = $this->applyContextFilters($fallbackRows, $context, $this->language->normalize($context['language'] ?? null));
        $fallbackRows->where(function ($builder) use ($query) {
            $builder->where('question', 'like', '%' . $query . '%')
                ->orWhere('answer', 'like', '%' . $query . '%')
                ->orWhere('category', 'like', '%' . $query . '%')
                ->orWhere('department', 'like', '%' . $query . '%')
                ->orWhere('brand', 'like', '%' . $query . '%')
                ->orWhere('model', 'like', '%' . $query . '%');
        });

        $fallbackRows = $fallbackRows
            ->limit((int) config('copilot.knowledge.db_fallback_limit', 5))
            ->get();

        foreach ($fallbackRows as $row) {
            if (collect($candidates)->contains(fn (array $candidate) => (int) ($candidate['id'] ?? 0) === (int) $row->id)) {
                continue;
            }

            $candidates[] = $this->formatCandidate($row, [], 'database');
        }

        return $candidates;
    }

    /**
     * @param  int|array<int>|null  $locationScope
     * @return array<int, array<string, mixed>>
     */
    private function searchPinecone(string $query, int|array|null $locationScope, array $context, int $limit): array
    {
        $filter = ['kind' => ['$eq' => 'faq']];

        if (is_int($locationScope)) {
            $filter['location_id'] = ['$eq' => $locationScope];
        } elseif (is_array($locationScope)) {
            $locationIds = array_values(array_filter(array_map('intval', $locationScope)));
            if ($locationIds === []) {
                return [];
            }
            $filter['location_id'] = ['$in' => $locationIds];
        }

        if (! empty($context['visibility']) && is_string($context['visibility'])) {
            $filter['visibility'] = ['$eq' => trim($context['visibility'])];
        }

        return collect($this->memory->searchSimilar($query, $limit, $filter))
            ->map(function (array $match) {
                $metadata = $match['metadata'] ?? [];

                return [
                    'faq_id' => isset($metadata['faq_id']) ? (int) $metadata['faq_id'] : null,
                    'question' => $metadata['question'] ?? null,
                    'answer' => $metadata['answer'] ?? null,
                    'category' => $metadata['category'] ?? 'General',
                    'language' => $metadata['language'] ?? null,
                    'department' => $metadata['department'] ?? null,
                    'visibility' => $metadata['visibility'] ?? 'internal',
                    'brand' => $metadata['brand'] ?? null,
                    'model' => $metadata['model'] ?? null,
                    'tags' => is_array($metadata['tags'] ?? null) ? $metadata['tags'] : [],
                    'location_id' => isset($metadata['location_id']) ? (int) $metadata['location_id'] : null,
                    'source_type' => $metadata['source_type'] ?? 'faq',
                    'semantic_score' => (float) ($match['score'] ?? 0),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @param  int|array<int>|null  $locationScope
     */
    private function scopeLocation($query, int|array|null $locationScope)
    {
        if (is_int($locationScope)) {
            return $query->where('location_id', $locationScope);
        }

        if (is_array($locationScope)) {
            $locationIds = array_values(array_filter(array_map('intval', $locationScope)));
            if ($locationIds === []) {
                return $query->whereRaw('1 = 0');
            }

            return $query->whereIn('location_id', $locationIds);
        }

        return $query;
    }

    /**
     * @return array<string, mixed>
     */
    private function formatCandidate(?Faq $row, array $match, string $retrievalSource): array
    {
        return [
            'id' => $row?->id ?? ($match['faq_id'] ?? null),
            'question' => $row?->question ?? ($match['question'] ?? null),
            'answer' => $row?->answer ?? ($match['answer'] ?? null),
            'category' => $row?->category ?? ($match['category'] ?? 'General'),
            'language' => $row?->language ?? ($match['language'] ?? null),
            'department' => $row?->department ?? ($match['department'] ?? null),
            'visibility' => $row?->visibility ?? ($match['visibility'] ?? 'internal'),
            'brand' => $row?->brand ?? ($match['brand'] ?? null),
            'model' => $row?->model ?? ($match['model'] ?? null),
            'tags' => $row?->tags ?? ($match['tags'] ?? []),
            'source_type' => $row?->source_type ?? ($match['source_type'] ?? 'faq'),
            'location_id' => $row?->location_id ?? ($match['location_id'] ?? null),
            'helpful' => (int) ($row?->helpful ?? 0),
            'not_helpful' => (int) ($row?->not_helpful ?? 0),
            'updated_at' => $row?->updated_at,
            'retrieval_source' => $retrievalSource,
            'semantic_score' => (float) ($match['semantic_score'] ?? 0),
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $candidates
     * @return array<int, array<string, mixed>>
     */
    private function rankCandidates(string $query, array $candidates, ?string $language): array
    {
        $normalizedQuery = $this->matcher->normalize($query);

        $ranked = array_map(function (array $candidate) use ($normalizedQuery, $language) {
            $questionScore = $this->matcher->score($normalizedQuery, (string) ($candidate['question'] ?? ''));
            $answerScore = $this->matcher->score($normalizedQuery, (string) ($candidate['answer'] ?? ''));
            $metadataScore = $this->matcher->score($normalizedQuery, implode(' ', array_filter([
                $candidate['category'] ?? null,
                $candidate['department'] ?? null,
                $candidate['brand'] ?? null,
                $candidate['model'] ?? null,
                implode(' ', $candidate['tags'] ?? []),
            ])));

            $languageBoost = 0.0;
            if ($language && ! empty($candidate['language'])) {
                $languageBoost = $candidate['language'] === $language ? 0.05 : -0.08;
            }

            $helpfulBoost = min(0.04, ((int) ($candidate['helpful'] ?? 0)) / 25);
            $retrievalBoost = ($candidate['retrieval_source'] ?? null) === 'pinecone' ? 0.05 : 0.0;
            $semanticScore = (float) ($candidate['semantic_score'] ?? 0.0);

            $weightedScore = ($semanticScore * 0.5)
                + ($questionScore * 0.25)
                + ($answerScore * 0.15)
                + ($metadataScore * 0.1)
                + $languageBoost
                + $helpfulBoost
                + $retrievalBoost;

            $exactMatchScore = max(
                $questionScore,
                ($answerScore >= 0.9 ? 0.78 : 0.0)
            );

            $candidate['question_score'] = round($questionScore, 3);
            $candidate['answer_score'] = round($answerScore, 3);
            $candidate['metadata_score'] = round($metadataScore, 3);
            $candidate['final_score'] = round(max(0.0, min(1.0, max($weightedScore, $exactMatchScore))), 3);

            return $candidate;
        }, $candidates);

        usort($ranked, fn (array $a, array $b) => ($b['final_score'] ?? 0) <=> ($a['final_score'] ?? 0));

        return array_values($ranked);
    }

    private function mergeAnswers(array $sources): string
    {
        $lead = trim((string) ($sources[0]['answer'] ?? ''));
        $extraPoints = [];
        $seen = [$this->matcher->normalize($lead)];

        foreach (array_slice($sources, 1) as $source) {
            foreach ($this->splitSentences((string) ($source['answer'] ?? '')) as $sentence) {
                $normalized = $this->matcher->normalize($sentence);
                if ($normalized === '' || in_array($normalized, $seen, true)) {
                    continue;
                }

                $seen[] = $normalized;
                $extraPoints[] = '- ' . $sentence;

                if (count($extraPoints) >= 3) {
                    break 2;
                }
            }
        }

        if ($extraPoints === []) {
            return $lead;
        }

        return trim($lead . "\n\nAdditional details:\n" . implode("\n", $extraPoints));
    }

    /**
     * @return array<int, string>
     */
    private function splitSentences(string $text): array
    {
        $sentences = preg_split('/(?<=[.!?])\s+/u', trim($text)) ?: [];

        return array_values(array_filter(array_map(static fn (string $sentence) => trim($sentence), $sentences)));
    }

    private function confidenceLabel(float $confidence): string
    {
        if ($confidence >= (float) config('copilot.knowledge.strong_match_score', 0.82)) {
            return 'high';
        }

        if ($confidence >= (float) config('copilot.knowledge.merge_match_score', 0.68)) {
            return 'medium';
        }

        return 'low';
    }

    /**
     * @param  int|array<int>|null  $locationScope
     * @return array<string, mixed>
     */
    private function traceFilters(int|array|null $locationScope, array $context, ?string $language): array
    {
        return array_filter([
            'location_scope' => $locationScope,
            'language' => $language,
            'category' => $context['category'] ?? null,
            'department' => $context['department'] ?? null,
            'visibility' => $context['visibility'] ?? null,
            'brand' => $context['brand'] ?? null,
            'model' => $context['model'] ?? null,
        ], static fn ($value) => $value !== null && $value !== []);
    }

    /**
     * @return array<string, mixed>
     */
    private function formatSource(array $source): array
    {
        return [
            'faq_id' => $source['id'] ?? null,
            'question' => $source['question'] ?? null,
            'category' => $source['category'] ?? null,
            'location_id' => $source['location_id'] ?? null,
            'language' => $source['language'] ?? null,
            'department' => $source['department'] ?? null,
            'visibility' => $source['visibility'] ?? null,
            'brand' => $source['brand'] ?? null,
            'model' => $source['model'] ?? null,
            'tags' => $source['tags'] ?? [],
            'source_type' => $source['source_type'] ?? 'faq',
            'retrieval_source' => $source['retrieval_source'] ?? null,
            'semantic_score' => $source['semantic_score'] ?? 0.0,
            'confidence' => $source['final_score'] ?? 0.0,
            'last_updated_at' => $this->formatTimestamp($source['updated_at'] ?? null),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function formatTraceMatch(array $candidate): array
    {
        return [
            'faq_id' => $candidate['id'] ?? null,
            'question' => $candidate['question'] ?? null,
            'category' => $candidate['category'] ?? null,
            'retrieval_source' => $candidate['retrieval_source'] ?? null,
            'source_type' => $candidate['source_type'] ?? 'faq',
            'semantic_score' => $candidate['semantic_score'] ?? 0.0,
            'question_score' => $candidate['question_score'] ?? 0.0,
            'answer_score' => $candidate['answer_score'] ?? 0.0,
            'metadata_score' => $candidate['metadata_score'] ?? 0.0,
            'confidence' => $candidate['final_score'] ?? 0.0,
            'location_id' => $candidate['location_id'] ?? null,
            'language' => $candidate['language'] ?? null,
            'department' => $candidate['department'] ?? null,
            'brand' => $candidate['brand'] ?? null,
            'model' => $candidate['model'] ?? null,
            'tags' => $candidate['tags'] ?? [],
            'last_updated_at' => $this->formatTimestamp($candidate['updated_at'] ?? null),
        ];
    }

    private function formatTimestamp(mixed $value): ?string
    {
        return $value instanceof CarbonInterface ? $value->toIso8601String() : null;
    }

    private function applyContextFilters($query, array $context, ?string $language)
    {
        if ($language) {
            $query->where(function ($builder) use ($language) {
                $builder->whereNull('language')
                    ->orWhere('language', $language);
            });
        }

        foreach (['category', 'department', 'visibility', 'brand', 'model'] as $filter) {
            if (! empty($context[$filter]) && is_string($context[$filter])) {
                $query->where($filter, trim($context[$filter]));
            }
        }

        if (! empty($context['tag']) && is_string($context['tag'])) {
            $query->whereJsonContains('tags', trim($context['tag']));
        }

        return $query;
    }
}
