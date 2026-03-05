<?php

namespace App\Services;

use App\Models\InteractionTemplate;

class InteractionTemplateService
{
    public function render(InteractionTemplate $template, array $data, ?string $locale = null, array $fallbacks = []): array
    {
        $content = $this->resolveContent($template, $locale, $fallbacks);
        $subject = $content['subject'];
        $body = $content['body'];

        foreach ($data as $key => $value) {
            if (is_scalar($value)) {
                $subject = $subject ? str_replace('{' . $key . '}', (string) $value, $subject) : $subject;
                $body = str_replace('{' . $key . '}', (string) $value, $body);
            }
        }

        return [
            'subject' => $subject,
            'body' => $body,
            'locale' => $content['locale'],
            'status' => $content['status'],
        ];
    }

    private function resolveContent(InteractionTemplate $template, ?string $locale, array $fallbacks): array
    {
        $sourceLocale = $template->source_locale ?: config('locales.default', 'nl');
        if (!$locale) {
            return [
                'subject' => $template->subject,
                'body' => $template->body,
                'locale' => $sourceLocale,
                'status' => 'REVIEWED',
            ];
        }

        $locale = strtolower($locale);
        $fallbacks = $fallbacks ?: config('locales.fallbacks', [])[$locale] ?? [];
        $chain = array_values(array_unique(array_merge([$locale], $fallbacks, [$sourceLocale])));

        $translations = $template->translations()
            ->whereIn('locale', $chain)
            ->get()
            ->keyBy('locale');

        foreach ($chain as $candidate) {
            if ($candidate === $sourceLocale) {
                return [
                    'subject' => $template->subject,
                    'body' => $template->body,
                    'locale' => $candidate,
                    'status' => 'REVIEWED',
                ];
            }
            $translation = $translations->get($candidate);
            if ($translation) {
                return [
                    'subject' => $translation->subject,
                    'body' => $translation->body,
                    'locale' => $translation->locale,
                    'status' => $translation->status,
                ];
            }
        }

        return [
            'subject' => $template->subject,
            'body' => $template->body,
            'locale' => $sourceLocale,
            'status' => 'REVIEWED',
        ];
    }
}
