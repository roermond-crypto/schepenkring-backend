<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CopilotSuggestionAiService
{
    /**
     * @param  array<string, mixed>  $candidate
     * @return array<string, mixed>|null
     */
    public function suggest(array $candidate): ?array
    {
        if (! $this->isEnabled()) {
            return null;
        }

        try {
            return config('copilot.ai_provider', 'openai') === 'gemini'
                ? $this->callGemini($candidate)
                : $this->callOpenAi($candidate);
        } catch (\Throwable $e) {
            Log::warning('Copilot suggestion AI failed', ['error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * @param  array<string, mixed>  $candidate
     */
    private function buildPrompt(array $candidate): string
    {
        $payload = json_encode($candidate, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        return <<<PROMPT
You are improving a copilot action suggestion generated from audits and usage history.
Refine the suggestion without inventing unsupported behavior.
Keep route_template and action_id aligned with the evidence.
If the suggestion targets an existing action, keep suggestion_type as "phrase".
Return ONLY valid JSON.

Input:
{$payload}

JSON schema:
{
  "suggestion_type": "action|phrase",
  "action_id": "string|null",
  "title": "string",
  "short_description": "string|null",
  "description": "string|null",
  "module": "string|null",
  "route_template": "string|null",
  "query_template": "string|null",
  "required_params": ["param"],
  "phrases": [{"phrase":"string","language":"en","priority":50}],
  "permission_key": "string|null",
  "required_role": "string|null",
  "risk_level": "low|medium|high",
  "confirmation_required": true,
  "confidence": 0.0,
  "reasoning": "string"
}
PROMPT;
    }

    /**
     * @param  array<string, mixed>  $candidate
     * @return array<string, mixed>|null
     */
    private function callOpenAi(array $candidate): ?array
    {
        $response = Http::withToken((string) config('services.openai.key'))
            ->timeout(30)
            ->post('https://api.openai.com/v1/chat/completions', [
                'model' => config('copilot.ai_model', 'gpt-4o-mini'),
                'messages' => [
                    ['role' => 'system', 'content' => 'You are a strict JSON generator.'],
                    ['role' => 'user', 'content' => $this->buildPrompt($candidate)],
                ],
                'temperature' => 0.1,
            ]);

        if ($response->failed()) {
            Log::warning('Copilot suggestion OpenAI failed', ['status' => $response->status()]);
            return null;
        }

        return $this->decodeJson($response->json('choices.0.message.content'));
    }

    /**
     * @param  array<string, mixed>  $candidate
     * @return array<string, mixed>|null
     */
    private function callGemini(array $candidate): ?array
    {
        $model = config('copilot.ai_model', 'gemini-2.5-flash');
        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key=" . config('services.gemini.key');

        $response = Http::timeout(30)->post($url, [
            'contents' => [[
                'parts' => [[
                    'text' => $this->buildPrompt($candidate),
                ]],
            ]],
            'generationConfig' => [
                'temperature' => 0.1,
            ],
        ]);

        if ($response->failed()) {
            Log::warning('Copilot suggestion Gemini failed', ['status' => $response->status()]);
            return null;
        }

        return $this->decodeJson($response->json('candidates.0.content.parts.0.text'));
    }

    /**
     * @return array<string, mixed>|null
     */
    private function decodeJson(?string $content): ?array
    {
        if (! $content) {
            return null;
        }

        $content = trim($content);
        $content = preg_replace('/^```json/i', '', $content);
        $content = preg_replace('/```$/', '', $content);
        $content = trim($content);

        $decoded = json_decode($content, true);

        return is_array($decoded) ? $decoded : null;
    }

    private function isEnabled(): bool
    {
        if (! config('copilot.ai_enabled')) {
            return false;
        }

        return config('copilot.ai_provider', 'openai') === 'gemini'
            ? (bool) config('services.gemini.key')
            : (bool) config('services.openai.key');
    }
}
