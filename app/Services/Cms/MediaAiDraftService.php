<?php

namespace App\Services\Cms;

use App\Models\Media;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * AI drafting for media metadata (alt text, SEO title) — always written
 * back as a draft (Media.ai_*_is_draft = true), never auto-published,
 * per the spec's "AI output is always draft only" rule. Reuses the same
 * Gemini vision-call shape as ImagePipelineController::classifyStoredImages
 * (inline base64 image + a JSON-only instruction) rather than inventing a
 * second AI-calling pattern.
 */
class MediaAiDraftService
{
    private const LOCALES = ['nl', 'en', 'de', 'fr'];

    public function generateAltText(Media $media): ?array
    {
        $result = $this->callGeminiVision(
            $media,
            'Write a concise, descriptive image alt text (max 125 characters) for accessibility and SEO purposes, for a yacht brokerage website. Return ONLY JSON: {"alt_text": {"nl": "...", "en": "...", "de": "...", "fr": "..."}}',
        );

        if (! isset($result['alt_text']) || ! is_array($result['alt_text'])) {
            return null;
        }

        $media->update([
            'alt_text' => $this->onlyKnownLocales($result['alt_text']),
            'ai_alt_text_is_draft' => true,
        ]);

        return $media->alt_text;
    }

    public function generateSeoTitle(Media $media): ?array
    {
        $result = $this->callGeminiVision(
            $media,
            'Write a short, SEO-friendly image title (max 60 characters) for a yacht brokerage website. Return ONLY JSON: {"seo_title": {"nl": "...", "en": "...", "de": "...", "fr": "..."}}',
        );

        if (! isset($result['seo_title']) || ! is_array($result['seo_title'])) {
            return null;
        }

        $media->update([
            'seo_title' => $this->onlyKnownLocales($result['seo_title']),
            'ai_seo_title_is_draft' => true,
        ]);

        return $media->seo_title;
    }

    private function callGeminiVision(Media $media, string $instruction): array
    {
        $apiKey = config('services.gemini.key') ?: env('GEMINI_API_KEY');
        if (! $apiKey) {
            return [];
        }

        $absolutePath = storage_path('app/public/' . ltrim($media->disk_path, '/'));
        if (! file_exists($absolutePath)) {
            return [];
        }

        $model = 'gemini-2.5-flash';
        $endpoint = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}";

        try {
            $response = Http::timeout(25)->post($endpoint, [
                'contents' => [[
                    'parts' => [
                        ['text' => $instruction],
                        [
                            'inline_data' => [
                                'mime_type' => mime_content_type($absolutePath) ?: 'image/jpeg',
                                'data' => base64_encode(file_get_contents($absolutePath)),
                            ],
                        ],
                    ],
                ]],
            ]);

            if (! $response->successful()) {
                return [];
            }

            $text = (string) data_get($response->json(), 'candidates.0.content.parts.0.text', '');

            return $this->decodeJsonResponse($text);
        } catch (\Throwable $e) {
            Log::warning('[MediaAiDraftService] Gemini call failed', ['error' => $e->getMessage()]);

            return [];
        }
    }

    private function decodeJsonResponse(string $text): array
    {
        $trimmed = trim($text);
        if ($trimmed === '') {
            return [];
        }

        $trimmed = preg_replace('/^```(?:json)?\s*/i', '', $trimmed) ?? $trimmed;
        $trimmed = preg_replace('/\s*```$/', '', $trimmed) ?? $trimmed;

        $decoded = json_decode($trimmed, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            return $decoded;
        }

        if (preg_match('/\{.*\}/s', $trimmed, $matches) === 1) {
            $decoded = json_decode($matches[0], true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return $decoded;
            }
        }

        return [];
    }

    private function onlyKnownLocales(array $values): array
    {
        return collect($values)
            ->only(self::LOCALES)
            ->map(fn ($v) => trim((string) $v))
            ->filter(fn ($v) => $v !== '')
            ->all();
    }
}
