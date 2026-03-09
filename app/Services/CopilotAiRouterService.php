<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CopilotAiRouterService
{
    public function route(string $input, array $actions, array $context = []): ?array
    {
        if (!$this->isEnabled()) {
            return null;
        }

        $provider = config('copilot.ai_provider', 'openai');

        try {
            if ($provider === 'gemini') {
                return $this->callGemini($input, $actions, $context);
            }

            return $this->callOpenAi($input, $actions, $context);
        } catch (\Throwable $e) {
            Log::warning('Copilot AI router failed', ['error' => $e->getMessage()]);
            return null;
        }
    }

    private function isEnabled(): bool
    {
        if (!config('copilot.ai_enabled')) {
            return false;
        }

        $provider = config('copilot.ai_provider', 'openai');
        if ($provider === 'gemini') {
            return (bool) env('GEMINI_API_KEY');
        }

        return (bool) env('OPENAI_API_KEY');
    }

    private function buildPrompt(string $input, array $actions, array $context): string
    {
        $actionList = array_map(function ($action) {
            return [
                'action_id' => $action['action_id'] ?? null,
                'title' => $action['title'] ?? null,
                'required_params' => $action['required_params'] ?? [],
                'input_schema' => $action['input_schema'] ?? null,
                'example_inputs' => $action['example_inputs'] ?? [],
            ];
        }, $actions);

        $contextText = json_encode($context, JSON_UNESCAPED_SLASHES);
        $actionsText = json_encode($actionList, JSON_UNESCAPED_SLASHES);
        $language = $context['language'] ?? 'en';

        return <<<PROMPT
You are a routing engine. Choose the best action_id from the list. Never invent new actions or URLs.
If unsure, set clarifying_question in the user's language ({$language}).
Return ONLY valid JSON.

Input: {$input}
Context: {$contextText}
Actions: {$actionsText}

JSON schema:
{
  "action_id": "string | null",
  "params": {"key": "value"},
  "confidence": 0.0-1.0,
  "clarifying_question": "string | null"
}
PROMPT;
    }

    private function callOpenAi(string $input, array $actions, array $context): ?array
    {
        $apiKey = env('OPENAI_API_KEY');
        if (!$apiKey) {
            return null;
        }

        $prompt = $this->buildPrompt($input, $actions, $context);
        $model = config('copilot.ai_model', 'gpt-4o-mini');

        $response = Http::withToken($apiKey)
            ->timeout(30)
            ->post('https://api.openai.com/v1/chat/completions', [
                'model' => $model,
                'messages' => [
                    ['role' => 'system', 'content' => 'You are a strict JSON generator.'],
                    ['role' => 'user', 'content' => $prompt],
                ],
                'temperature' => 0.1,
            ]);

        if ($response->failed()) {
            Log::warning('Copilot AI OpenAI failed', ['status' => $response->status()]);
            return null;
        }

        $content = $response->json('choices.0.message.content');
        return $this->decodeJson($content);
    }

    private function callGemini(string $input, array $actions, array $context): ?array
    {
        $apiKey = env('GEMINI_API_KEY');
        if (!$apiKey) {
            return null;
        }

        $prompt = $this->buildPrompt($input, $actions, $context);
        $model = env('COPILOT_AI_MODEL', 'gemini-2.5-flash');
        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}";

        $response = Http::timeout(30)->post($url, [
            'contents' => [[
                'parts' => [[
                    'text' => $prompt,
                ]],
            ]],
            'generationConfig' => [
                'temperature' => 0.1,
            ],
        ]);

        if ($response->failed()) {
            Log::warning('Copilot AI Gemini failed', ['status' => $response->status()]);
            return null;
        }

        $text = $response->json('candidates.0.content.parts.0.text');
        return $this->decodeJson($text);
    }

    private function decodeJson(?string $content): ?array
    {
        if (!$content) {
            return null;
        }

        $content = trim($content);
        $content = preg_replace('/^```json/i', '', $content);
        $content = preg_replace('/```$/', '', $content);
        $content = trim($content);

        $decoded = json_decode($content, true);
        if (!is_array($decoded)) {
            return null;
        }

        return $decoded;
    }
}
