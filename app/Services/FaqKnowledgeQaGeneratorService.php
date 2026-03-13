<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class FaqKnowledgeQaGeneratorService
{
    /**
     * @return array<int, array{question:string,answer:string}>
     */
    public function generate(string $chunk, ?string $language = null): array
    {
        $apiKey = (string) config('services.openai.key');
        if ($apiKey === '') {
            throw new RuntimeException('OPENAI_API_KEY is not configured.');
        }

        $response = Http::withToken($apiKey)
            ->timeout(45)
            ->post('https://api.openai.com/v1/chat/completions', [
                'model' => config('copilot.ai_model', 'gpt-4o-mini'),
                'response_format' => ['type' => 'json_object'],
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'You generate business FAQ entries from internal documents. Return valid JSON only with the shape {"items":[{"question":"...","answer":"..."}]}. Create 3 to 5 realistic, non-duplicate FAQs. Keep answers factual and grounded only in the provided text.',
                    ],
                    [
                        'role' => 'user',
                        'content' => trim(implode("\n\n", array_filter([
                            $language ? 'Preferred language: '.$language : null,
                            "Text:\n".$chunk,
                        ]))),
                    ],
                ],
            ]);

        if ($response->failed()) {
            throw new RuntimeException('OpenAI Q&A generation failed.');
        }

        $content = (string) $response->json('choices.0.message.content');
        $decoded = json_decode($content, true);
        if (! is_array($decoded)) {
            throw new RuntimeException('OpenAI returned invalid Q&A JSON.');
        }

        $items = $decoded['items'] ?? [];
        if (! is_array($items)) {
            return [];
        }

        $results = [];
        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }

            $question = trim((string) ($item['question'] ?? ''));
            $answer = trim((string) ($item['answer'] ?? ''));

            if ($question === '' || $answer === '') {
                continue;
            }

            $results[] = [
                'question' => $question,
                'answer' => $answer,
            ];
        }

        return array_values(array_unique($results, SORT_REGULAR));
    }
}
