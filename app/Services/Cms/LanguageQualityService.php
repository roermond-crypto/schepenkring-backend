<?php

namespace App\Services\Cms;

use App\Models\CmsPage;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Checks a CmsPage's content for the two things that are actually
 * checkable from stored content: missing translations (cheap, exact), and
 * semantic mistranslation / wrong-language text (needs an LLM — this is
 * what actually caught "boot" -> "laars" in the original bug report,
 * which no static/regex check could have found).
 *
 * Deliberately NOT attempted here: "truncated mobile labels" and "broken
 * placeholders" are rendering/CSS concerns, not content concerns — no
 * amount of inspecting the stored JSON tells you whether a label
 * overflows its container at 375px wide. "Hardcoded public text outside
 * CMS/I18N" is a codebase static-analysis concern (grep for JSX string
 * literals), not something a per-page content check can see. Flagging
 * these as out of scope for this service rather than faking a check that
 * can't actually catch them.
 */
class LanguageQualityService
{
    private const LOCALES = ['nl', 'en', 'de', 'fr'];
    private const SOURCE_LOCALE = 'nl'; // this brokerage's primary language

    /**
     * @return array<int, array{section_index: int, field_key: string, locale: string, issue: string, suggestion: ?string}>
     */
    public function check(CmsPage $page): array
    {
        $page->loadMissing('sections');
        $issues = [];

        foreach ($page->sections as $sectionIndex => $section) {
            foreach ($section->content ?? [] as $fieldKey => $value) {
                if (! is_array($value) || ! $this->looksLikeLocaleValue($value)) {
                    continue;
                }

                $issues = array_merge(
                    $issues,
                    $this->checkMissingTranslations($sectionIndex, $fieldKey, $value),
                );
            }
        }

        $issues = array_merge($issues, $this->checkMistranslations($page));

        return $issues;
    }

    private function looksLikeLocaleValue(array $value): bool
    {
        foreach (array_keys($value) as $key) {
            if (! in_array($key, self::LOCALES, true)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function checkMissingTranslations(int $sectionIndex, string $fieldKey, array $value): array
    {
        $sourceValue = $value[self::SOURCE_LOCALE] ?? null;
        $sourceIsList = is_array($sourceValue);
        $sourceHasContent = $sourceIsList ? count(array_filter($sourceValue)) > 0 : trim((string) $sourceValue) !== '';

        if (! $sourceHasContent) {
            return [];
        }

        $issues = [];
        foreach (self::LOCALES as $locale) {
            if ($locale === self::SOURCE_LOCALE) {
                continue;
            }

            $localeValue = $value[$locale] ?? null;
            $isEmpty = is_array($localeValue)
                ? count(array_filter($localeValue)) === 0
                : trim((string) $localeValue) === '';

            if ($isEmpty) {
                $issues[] = [
                    'section_index' => $sectionIndex,
                    'field_key' => $fieldKey,
                    'locale' => $locale,
                    'issue' => 'missing_translation',
                    'suggestion' => null,
                ];
            }
        }

        return $issues;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function checkMistranslations(CmsPage $page): array
    {
        $apiKey = config('services.gemini.key') ?: env('GEMINI_API_KEY');
        if (! $apiKey) {
            return [];
        }

        $textFields = [];
        foreach ($page->sections as $sectionIndex => $section) {
            foreach ($section->content ?? [] as $fieldKey => $value) {
                if (is_array($value) && $this->looksLikeLocaleValue($value)) {
                    $hasNonEmptyString = collect($value)->contains(fn ($v) => is_string($v) && trim($v) !== '');
                    if ($hasNonEmptyString) {
                        $textFields["{$sectionIndex}.{$fieldKey}"] = $value;
                    }
                }
            }
        }

        if ($textFields === []) {
            return [];
        }

        $model = 'gemini-2.5-flash';
        $endpoint = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}";

        $instruction = "You are reviewing translated marketing copy for a yacht brokerage website. "
            . "For each field below, compare the nl/en/de/fr versions. Flag ONLY genuine problems: "
            . "a translation that uses the wrong meaning of an ambiguous source word (e.g. Dutch 'boot' "
            . "meaning 'boat' mistranslated as 'laars', the word for a footwear boot), text that is in the "
            . "wrong language entirely, or a broken/garbled machine-translation artifact. Do NOT flag "
            . "stylistic differences or normal translation variation. Return ONLY JSON: "
            . '{"issues": [{"field": "sectionIndex.fieldKey", "locale": "en", "issue": "short description", "suggestion": "corrected text"}]}. '
            . "Return {\"issues\": []} if nothing is wrong.\n\nFields:\n" . json_encode($textFields, JSON_UNESCAPED_UNICODE);

        try {
            $response = Http::timeout(25)->post($endpoint, [
                'contents' => [['parts' => [['text' => $instruction]]]],
            ]);

            if (! $response->successful()) {
                return [];
            }

            $text = (string) data_get($response->json(), 'candidates.0.content.parts.0.text', '');
            $decoded = $this->decodeJsonResponse($text);

            return collect($decoded['issues'] ?? [])
                ->map(function (array $issue) {
                    [$sectionIndex, $fieldKey] = array_pad(explode('.', (string) ($issue['field'] ?? ''), 2), 2, null);

                    return [
                        'section_index' => $sectionIndex !== null ? (int) $sectionIndex : null,
                        'field_key' => $fieldKey,
                        'locale' => $issue['locale'] ?? null,
                        'issue' => 'possible_mistranslation: ' . ($issue['issue'] ?? ''),
                        'suggestion' => $issue['suggestion'] ?? null,
                    ];
                })
                ->filter(fn (array $issue) => $issue['field_key'] !== null)
                ->values()
                ->all();
        } catch (\Throwable $e) {
            Log::warning('[LanguageQualityService] Gemini call failed', ['error' => $e->getMessage()]);

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
}
