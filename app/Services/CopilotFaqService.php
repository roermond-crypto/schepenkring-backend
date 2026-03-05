<?php

namespace App\Services;

use App\Models\Faq;

class CopilotFaqService
{
    public function search(string $query, int $limit = 2): array
    {
        $query = trim($query);
        if ($query === '') {
            return [];
        }

        $rows = Faq::query()
            ->where('question', 'like', '%' . $query . '%')
            ->orWhere('answer', 'like', '%' . $query . '%')
            ->limit($limit)
            ->get();

        $results = [];
        foreach ($rows as $row) {
            $results[] = [
                'id' => $row->id,
                'question' => $row->question,
                'answer' => $row->answer,
                'category' => $row->category,
            ];
        }

        return $results;
    }
}
