<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class HarborWidgetAiService
{
    public function generate(array $payload): array
    {
        $provider = env('HARBOR_WIDGET_AI_PROVIDER', env('ERROR_AI_PROVIDER', 'gemini'));
        $payload = $this->sanitizePayload($payload);

        try {
            $result = $provider === 'openai'
                ? $this->callOpenAi($payload)
                : $this->callGemini($payload);

            if (is_array($result)) {
                return $this->normalize($result);
            }
        } catch (\Throwable $e) {
            Log::warning('Harbor widget AI failed: ' . $e->getMessage());
        }

        return $this->fallbackAdvice($payload);
    }

    private function sanitizePayload(array $payload): array
    {
        $redact = ['password', 'token', 'api_key', 'secret', 'authorization', 'iban', 'card', 'credit_card'];
        array_walk_recursive($payload, function (&$value, $key) use ($redact) {
            foreach ($redact as $needle) {
                if (stripos((string) $key, $needle) !== false) {
                    $value = '[REDACTED]';
                    return;
                }
            }
        });
        return $payload;
    }

    private function callGemini(array $payload): ?array
    {
        $apiKey = env('GEMINI_API_KEY');
        if (!$apiKey) {
            return null;
        }

        $model = env('HARBOR_WIDGET_AI_MODEL', 'gemini-2.5-flash-lite');
        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}";

        $schema = [
            'issues' => ['string'],
            'suggestions' => ['string'],
            'priority' => 'low|medium|high',
            'user_message' => 'string'
        ];

        $response = Http::withHeaders(['Content-Type' => 'application/json'])->post($url, [
            'system_instruction' => ['parts' => [[
                'text' => 'Return ONLY valid JSON. No markdown.'
            ]]],
            'contents' => [[
                'role' => 'user',
                'parts' => [[
                    'text' => "Analyze this harbor widget performance and return JSON matching this schema:\n" .
                        json_encode($schema) . "\n\nInput:\n" . json_encode($payload)
                ]]
            ]],
            'generationConfig' => [
                'temperature' => 0.2,
                'maxOutputTokens' => 700,
            ],
        ]);

        if ($response->failed()) {
            Log::warning('Gemini harbor widget AI failed: ' . $response->body());
            return null;
        }

        $text = $response->json('candidates.0.content.parts.0.text');
        if (!$text) {
            return null;
        }

        $decoded = json_decode($text, true);
        return is_array($decoded) ? $decoded : null;
    }

    private function callOpenAi(array $payload): ?array
    {
        $apiKey = env('OPENAI_API_KEY');
        if (!$apiKey) {
            return null;
        }

        $model = env('HARBOR_WIDGET_AI_MODEL', 'gpt-4o-mini');
        $schema = [
            'issues' => ['string'],
            'suggestions' => ['string'],
            'priority' => 'low|medium|high',
            'user_message' => 'string'
        ];

        $response = Http::withToken($apiKey)->post('https://api.openai.com/v1/chat/completions', [
            'model' => $model,
            'messages' => [
                ['role' => 'system', 'content' => 'Return ONLY valid JSON. No markdown.'],
                ['role' => 'user', 'content' => "Schema:\n" . json_encode($schema) . "\n\nInput:\n" . json_encode($payload)],
            ],
            'temperature' => 0.2,
        ]);

        if ($response->failed()) {
            Log::warning('OpenAI harbor widget AI failed: ' . $response->body());
            return null;
        }

        $text = $response->json('choices.0.message.content');
        $decoded = json_decode($text, true);
        return is_array($decoded) ? $decoded : null;
    }

    private function fallbackAdvice(array $payload): array
    {
        $issues = [];
        $suggestions = [];

        $ctr = (float) ($payload['ctr'] ?? 0);
        $visibleRate = (float) ($payload['visible_rate'] ?? 0);
        $mobileCtr = (float) ($payload['mobile_ctr'] ?? 0);
        $desktopCtr = (float) ($payload['desktop_ctr'] ?? 0);
        $reliability = (float) ($payload['reliability_score'] ?? 0);
        $benchmark = (float) ($payload['benchmark_ctr'] ?? 10);
        $widgetIssues = (int) ($payload['widget_issue_count'] ?? 0);

        if ($visibleRate > 0 && $visibleRate < 70) {
            $issues[] = "Button visibility is low ({$visibleRate}%).";
            $suggestions[] = 'Move the button higher on the page or ensure it is above the fold.';
        }

        if ($ctr < $benchmark) {
            $issues[] = "CTR is below the benchmark of {$benchmark}%.";
            $suggestions[] = 'Improve call-to-action text and increase contrast.';
        }

        if ($mobileCtr > 0 && $desktopCtr > 0 && $mobileCtr < $desktopCtr) {
            $issues[] = 'Mobile CTR is significantly lower than desktop.';
            $suggestions[] = 'Increase button size on mobile (min 44px) and reduce nearby distractions.';
        }

        if ($widgetIssues > 0 || $reliability < 80) {
            $issues[] = 'Widget reliability issues were detected this week.';
            $suggestions[] = 'Check script loading, CORS settings, and console errors on the harbor domain.';
        }

        if (empty($issues)) {
            $issues[] = 'Performance is stable with no major issues detected.';
        }

        if (empty($suggestions)) {
            $suggestions[] = 'Keep monitoring and test a stronger CTA to push CTR higher.';
        }

        $priority = 'low';
        if ($ctr < $benchmark || $reliability < 80) {
            $priority = 'high';
        } elseif ($visibleRate < 70 || $mobileCtr < $desktopCtr) {
            $priority = 'medium';
        }

        $userMessage = "Your harbor button performance summary: CTR {$ctr}%, visibility {$visibleRate}%. Review the suggestions to improve clicks.";

        return $this->normalize([
            'issues' => $issues,
            'suggestions' => $suggestions,
            'priority' => $priority,
            'user_message' => $userMessage,
        ]);
    }

    private function normalize(array $result): array
    {
        $priority = strtolower((string) ($result['priority'] ?? 'low'));
        if (!in_array($priority, ['low', 'medium', 'high'], true)) {
            $priority = 'low';
        }

        return [
            'issues' => array_values(array_filter((array) ($result['issues'] ?? []))),
            'suggestions' => array_values(array_filter((array) ($result['suggestions'] ?? []))),
            'priority' => $priority,
            'user_message' => (string) ($result['user_message'] ?? ''),
        ];
    }
}
