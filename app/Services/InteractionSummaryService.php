<?php

namespace App\Services;

use App\Models\InteractionTimelineEntry;
use App\Models\UserInteractionSummary;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class InteractionSummaryService
{
    public function buildSummary(int $userId, int $days = 1): ?UserInteractionSummary
    {
        $entries = InteractionTimelineEntry::where('user_id', $userId)
            ->where('occurred_at', '>=', now()->subDays($days))
            ->orderBy('occurred_at')
            ->get();

        if ($entries->isEmpty()) {
            return null;
        }

        $lastActivity = $entries->last()?->occurred_at ?? now();
        $counts = $entries->groupBy('channel')->map->count()->toArray();

        $summaryText = $this->aiSummary($entries) ?? $this->fallbackSummary($entries, $counts);
        $source = $summaryText['source'] ?? 'fallback';
        $summary = $summaryText['summary'] ?? $this->fallbackSummary($entries, $counts)['summary'];

        return UserInteractionSummary::updateOrCreate([
            'user_id' => $userId,
        ], [
            'summary' => $summary,
            'source' => $source,
            'last_activity_at' => $lastActivity,
            'metadata' => [
                'counts' => $counts,
                'window_days' => $days,
            ],
        ]);
    }

    private function aiSummary($entries): ?array
    {
        $provider = env('CHAT_AI_PROVIDER', 'openai');
        $apiKey = $provider === 'gemini' ? env('GEMINI_API_KEY') : env('OPENAI_API_KEY');
        if (!$apiKey) {
            return null;
        }

        $contextLines = $entries->take(50)->map(function ($entry) {
            $label = strtoupper($entry->channel) . '/' . strtoupper($entry->direction);
            $body = $entry->body ?? $entry->title ?? '';
            $time = $entry->occurred_at?->toIso8601String();
            return "[{$time}] {$label}: {$body}";
        })->implode("\n");

        $prompt = "Summarize the following customer interactions in 4-6 bullet points. Highlight key events, requests, and follow-ups.\n\n" . $contextLines;

        try {
            if ($provider === 'gemini') {
                $model = env('CHAT_AI_MODEL', 'gemini-2.5-flash-lite');
                $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}";
                $response = Http::timeout(60)->post($url, [
                    'contents' => [[
                        'parts' => [[
                            'text' => $prompt,
                        ]],
                    ]],
                    'generationConfig' => [
                        'temperature' => 0.2,
                        'maxOutputTokens' => 400,
                    ],
                ]);
                if ($response->failed()) {
                    Log::warning('Interaction summary Gemini failed: ' . $response->body());
                    return null;
                }
                $text = $response->json('candidates.0.content.parts.0.text');
                return $text ? ['summary' => trim($text), 'source' => 'ai'] : null;
            }

            $model = env('CHAT_AI_MODEL', 'gpt-4o-mini');
            $response = Http::withToken($apiKey)->timeout(60)->post('https://api.openai.com/v1/chat/completions', [
                'model' => $model,
                'messages' => [
                    ['role' => 'system', 'content' => 'You are a concise summarizer.'],
                    ['role' => 'user', 'content' => $prompt],
                ],
                'temperature' => 0.2,
                'max_tokens' => 400,
            ]);

            if ($response->failed()) {
                Log::warning('Interaction summary OpenAI failed: ' . $response->body());
                return null;
            }

            $text = $response->json('choices.0.message.content');
            return $text ? ['summary' => trim($text), 'source' => 'ai'] : null;
        } catch (\Throwable $e) {
            Log::warning('Interaction summary AI exception: ' . $e->getMessage());
            return null;
        }
    }

    private function fallbackSummary($entries, array $counts): array
    {
        $last = $entries->last();
        $lines = [
            'Recent activity summary:',
            'Total interactions: ' . $entries->count(),
        ];
        foreach ($counts as $channel => $count) {
            $lines[] = strtoupper($channel) . ': ' . $count;
        }
        if ($last) {
            $lines[] = 'Last activity: ' . ($last->title ?? $last->body ?? 'event') . ' at ' . $last->occurred_at?->toDateTimeString();
        }

        return [
            'summary' => implode("\n", $lines),
            'source' => 'fallback',
        ];
    }
}
