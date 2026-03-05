<?php

namespace App\Services;

use App\Models\Conversation;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ChatAiService
{
    public function __construct(private ChatRagService $rag)
    {
    }

    public function generateReply(Conversation $conversation, string $question, string $language): array
    {
        $matches = $this->rag->search($question, $conversation, $language);
        $top = $matches[0] ?? null;
        $score = $top['score'] ?? 0;

        $high = (float) env('CHAT_AI_HIGH_CONF', 0.82);
        $medium = (float) env('CHAT_AI_MED_CONF', 0.65);

        if ($score >= $high) {
            $answer = $this->generateFromSources($question, $language, $matches) ?? $this->fallbackAnswer($top);
            return ['status' => 'high', 'confidence' => $score, 'answer' => $answer, 'sources' => $matches];
        }

        if ($score >= $medium) {
            $clarify = $this->clarifyingQuestion($language);
            return ['status' => 'medium', 'confidence' => $score, 'answer' => $clarify, 'sources' => $matches];
        }

        return ['status' => 'low', 'confidence' => $score, 'answer' => null, 'sources' => $matches];
    }

    private function fallbackAnswer(?array $top): ?string
    {
        if (!$top) {
            return null;
        }

        $metadata = $top['metadata'] ?? [];
        return $metadata['answer'] ?? $metadata['best_answer'] ?? null;
    }

    private function clarifyingQuestion(string $language): string
    {
        if ($language === 'nl') {
            return 'Kun je kort verduidelijken wat je precies bedoelt?';
        }
        if ($language === 'de') {
            return 'Kannst du kurz praezisieren, was du genau meinst?';
        }
        return 'Could you clarify a bit more so I can help you better?';
    }

    private function generateFromSources(string $question, string $language, array $sources): ?string
    {
        if (!filter_var(env('CHAT_AI_USE_LLM', false), FILTER_VALIDATE_BOOL)) {
            return $this->fallbackAnswer($sources[0] ?? null);
        }

        $provider = env('CHAT_AI_PROVIDER', 'openai');
        try {
            return $provider === 'gemini'
                ? $this->callGemini($question, $language, $sources)
                : $this->callOpenAi($question, $language, $sources);
        } catch (\Throwable $e) {
            Log::warning('Chat AI generation failed: ' . $e->getMessage());
            return $this->fallbackAnswer($sources[0] ?? null);
        }
    }

    private function buildContext(array $sources): string
    {
        $lines = [];
        foreach (array_slice($sources, 0, 3) as $source) {
            $meta = $source['metadata'] ?? [];
            $question = $meta['question'] ?? 'Q';
            $answer = $meta['answer'] ?? $meta['best_answer'] ?? '';
            $lines[] = "Q: {$question}\nA: {$answer}";
        }

        return implode("\n\n", $lines);
    }

    private function callOpenAi(string $question, string $language, array $sources): ?string
    {
        $apiKey = env('OPENAI_API_KEY');
        if (!$apiKey) {
            return null;
        }

        $model = env('CHAT_AI_MODEL', 'gpt-4o-mini');
        $context = $this->buildContext($sources);

        $response = Http::withToken($apiKey)->timeout(60)->post('https://api.openai.com/v1/chat/completions', [
            'model' => $model,
            'messages' => [
                ['role' => 'system', 'content' => "Answer in {$language} using the context. Keep it concise and helpful."],
                ['role' => 'user', 'content' => "Question: {$question}\n\nContext:\n{$context}"],
            ],
            'temperature' => 0.2,
            'max_tokens' => 300,
        ]);

        if ($response->failed()) {
            Log::warning('Chat AI OpenAI failed: ' . $response->body());
            return null;
        }

        $text = $response->json('choices.0.message.content');
        return $text ? trim($text) : null;
    }

    private function callGemini(string $question, string $language, array $sources): ?string
    {
        $apiKey = env('GEMINI_API_KEY');
        if (!$apiKey) {
            return null;
        }

        $model = env('CHAT_AI_MODEL', 'gemini-2.5-flash-lite');
        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}";
        $context = $this->buildContext($sources);

        $response = Http::withHeaders(['Content-Type' => 'application/json'])
            ->timeout(60)
            ->post($url, [
                'system_instruction' => ['parts' => [[
                    'text' => "Answer in {$language} using the context. Keep it concise and helpful.",
                ]]],
                'contents' => [[
                    'role' => 'user',
                    'parts' => [[
                        'text' => "Question: {$question}\n\nContext:\n{$context}",
                    ]],
                ]],
                'generationConfig' => [
                    'temperature' => 0.2,
                    'maxOutputTokens' => 300,
                ],
            ]);

        if ($response->failed()) {
            Log::warning('Chat AI Gemini failed: ' . $response->body());
            return null;
        }

        $text = $response->json('candidates.0.content.parts.0.text');
        return $text ? trim($text) : null;
    }
}
