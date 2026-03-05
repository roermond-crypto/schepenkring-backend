<?php

namespace App\Services;

use App\Models\Conversation;

class ChatRagService
{
    public function __construct(
        private FaqPineconeService $faqPinecone,
        private ChatFaqPineconeService $chatFaqPinecone
    ) {
    }

    public function search(string $query, Conversation $conversation, string $language): array
    {
        $harborId = $conversation->harbor_id;

        $errorMatches = [];
        if ($this->looksLikeErrorQuery($query)) {
            $errorMatches = $this->faqPinecone->query($query, $language, null, 5, 'ERRORS');
        }

        $faqMatches = $this->faqPinecone->query($query, $language, null, 5);
        $chatMatches = $this->chatFaqPinecone->query($query, $language, $harborId, 5);

        $combined = [];
        foreach ($errorMatches as $match) {
            $combined[] = [
                'score' => $match['score'] ?? 0,
                'source' => 'error_faq',
                'metadata' => $match['metadata'] ?? [],
            ];
        }
        foreach ($faqMatches as $match) {
            $combined[] = [
                'score' => $match['score'] ?? 0,
                'source' => 'faq',
                'metadata' => $match['metadata'] ?? [],
            ];
        }
        foreach ($chatMatches as $match) {
            $combined[] = [
                'score' => $match['score'] ?? 0,
                'source' => 'chat_faq',
                'metadata' => $match['metadata'] ?? [],
            ];
        }

        usort($combined, function ($a, $b) {
            return ($b['score'] <=> $a['score']);
        });

        return $combined;
    }

    private function looksLikeErrorQuery(string $query): bool
    {
        $needle = strtolower($query);
        $keywords = [
            'error', 'issue', 'problem', 'bug', 'fail', 'failed', 'failure',
            'crash', 'broken', 'does not work', 'not working', 'unable',
            'fout', 'probleem', 'storing', 'werkt niet', 'mislukt',
            'fehler', 'problem', 'funktioniert nicht', 'fehlgeschlagen',
        ];

        foreach ($keywords as $word) {
            if (str_contains($needle, $word)) {
                return true;
            }
        }

        return false;
    }
}
