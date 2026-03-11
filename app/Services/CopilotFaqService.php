<?php

namespace App\Services;

use App\Models\Faq;

class CopilotFaqService
{
    public function __construct(private CopilotMemoryService $memory)
    {
    }

    /**
     * @param  int|array<int>|null  $locationScope
     * @return array<int, array<string, mixed>>
     */
    public function search(string $query, int|array|null $locationScope = null, int $limit = 2): array
    {
        $query = trim($query);
        if ($query === '') {
            return [];
        }

        $results = $this->searchPinecone($query, $locationScope, $limit);
        if (count($results) >= $limit) {
            return array_slice($results, 0, $limit);
        }

        $rows = Faq::query();
        $rows = $this->scopeLocation($rows, $locationScope);
        $rows->where(function ($builder) use ($query) {
            $builder->where('question', 'like', '%' . $query . '%')
                ->orWhere('answer', 'like', '%' . $query . '%');
        });

        $rows = $rows
            ->limit($limit)
            ->get();

        foreach ($rows as $row) {
            if (collect($results)->contains(fn (array $item) => ($item['id'] ?? null) === $row->id)) {
                continue;
            }

            $results[] = $this->formatFaq($row);
        }

        return array_slice($results, 0, $limit);
    }

    /**
     * @param  int|array<int>|null  $locationScope
     * @return array<int, array<string, mixed>>
     */
    private function searchPinecone(string $query, int|array|null $locationScope, int $limit): array
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

        return collect($this->memory->searchSimilar($query, $limit, $filter))
            ->map(function (array $match) {
                $metadata = $match['metadata'] ?? [];

                return [
                    'id' => $metadata['faq_id'] ?? null,
                    'question' => $metadata['question'] ?? null,
                    'answer' => $metadata['answer'] ?? null,
                    'category' => $metadata['category'] ?? 'General',
                    'location_id' => $metadata['location_id'] ?? null,
                    'score' => $match['score'] ?? 0,
                ];
            })
            ->filter(fn (array $row) => ! empty($row['question']) && ! empty($row['answer']))
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
    private function formatFaq(Faq $row): array
    {
        return [
            'id' => $row->id,
            'question' => $row->question,
            'answer' => $row->answer,
            'category' => $row->category,
            'location_id' => $row->location_id,
        ];
    }
}
