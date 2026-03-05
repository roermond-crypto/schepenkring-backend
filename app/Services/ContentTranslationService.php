<?php

namespace App\Services;

use App\Models\Blog;
use App\Models\BlogTranslation;
use App\Models\InteractionTemplate;
use App\Models\InteractionTemplateTranslation;
use App\Support\TranslationStatus;

class ContentTranslationService
{
    public function __construct(
        private ContentAiTranslationService $ai,
        private TranslationHashService $hasher
    ) {
    }

    public function translateBlog(Blog $blog, string $targetLocale, bool $force = false): ?BlogTranslation
    {
        $sourceLocale = $blog->source_locale ?: config('locales.default');
        if ($targetLocale === $sourceLocale) {
            return null;
        }

        $sourceHash = $this->blogSourceHash($blog);
        $blog->source_hash = $sourceHash;
        $blog->save();

        $translation = BlogTranslation::where('blog_id', $blog->id)
            ->where('locale', $targetLocale)
            ->first();

        if ($translation && !$force && $translation->translated_from_hash === $sourceHash && $translation->status !== TranslationStatus::OUTDATED) {
            return $translation;
        }

        $fields = [
            'title' => $blog->title,
            'excerpt' => $blog->excerpt ?? '',
            'content' => $blog->content,
            'meta_title' => $blog->meta_title ?? '',
            'meta_description' => $blog->meta_description ?? '',
        ];

        [$protectedFields, $tokenMap] = $this->protectTokens($fields);
        $protectedTokens = array_keys($tokenMap);
        $translated = $this->ai->translateStructured(
            $protectedFields,
            $sourceLocale,
            $targetLocale,
            $protectedTokens,
            'blog'
        );

        if (!$translated) {
            return null;
        }

        $translated = $this->restoreTokens($translated, $tokenMap);

        $translation = $translation ?: new BlogTranslation([
            'blog_id' => $blog->id,
            'locale' => $targetLocale,
        ]);

        $translation->fill([
            'title' => trim((string) ($translated['title'] ?? $blog->title)),
            'excerpt' => trim((string) ($translated['excerpt'] ?? $blog->excerpt ?? '')),
            'content' => trim((string) ($translated['content'] ?? $blog->content)),
            'meta_title' => trim((string) ($translated['meta_title'] ?? $blog->meta_title ?? '')),
            'meta_description' => trim((string) ($translated['meta_description'] ?? $blog->meta_description ?? '')),
            'status' => TranslationStatus::AI_DRAFT,
            'source_hash' => $this->hasher->hash($translated),
            'translated_from_hash' => $sourceHash,
        ]);
        $translation->save();

        return $translation;
    }

    public function translateInteractionTemplate(InteractionTemplate $template, string $targetLocale, bool $force = false): ?InteractionTemplateTranslation
    {
        $sourceLocale = $template->source_locale ?: config('locales.default');
        if ($targetLocale === $sourceLocale) {
            return null;
        }

        $sourceHash = $this->templateSourceHash($template);
        $template->source_hash = $sourceHash;
        $template->save();

        $translation = InteractionTemplateTranslation::where('interaction_template_id', $template->id)
            ->where('locale', $targetLocale)
            ->first();

        if ($translation && !$force && $translation->translated_from_hash === $sourceHash && $translation->status !== TranslationStatus::OUTDATED) {
            return $translation;
        }

        $fields = [
            'subject' => $template->subject ?? '',
            'body' => $template->body,
        ];

        [$protectedFields, $tokenMap] = $this->protectTokens($fields);
        $protectedTokens = array_keys($tokenMap);
        $translated = $this->ai->translateStructured(
            $protectedFields,
            $sourceLocale,
            $targetLocale,
            $protectedTokens,
            'system_message'
        );

        if (!$translated) {
            return null;
        }

        $translated = $this->restoreTokens($translated, $tokenMap);

        $translation = $translation ?: new InteractionTemplateTranslation([
            'interaction_template_id' => $template->id,
            'locale' => $targetLocale,
        ]);

        $translation->fill([
            'subject' => trim((string) ($translated['subject'] ?? $template->subject)),
            'body' => trim((string) ($translated['body'] ?? $template->body)),
            'status' => TranslationStatus::AI_DRAFT,
            'source_hash' => $this->hasher->hash($translated),
            'translated_from_hash' => $sourceHash,
        ]);
        $translation->save();

        return $translation;
    }

    public function blogSourceHash(Blog $blog): string
    {
        return $this->hasher->hash([
            'title' => $blog->title,
            'excerpt' => $blog->excerpt,
            'content' => $blog->content,
            'meta_title' => $blog->meta_title,
            'meta_description' => $blog->meta_description,
        ]);
    }

    public function templateSourceHash(InteractionTemplate $template): string
    {
        return $this->hasher->hash([
            'subject' => $template->subject,
            'body' => $template->body,
            'placeholders' => $template->placeholders,
        ]);
    }

    private function protectTokens(array $fields): array
    {
        $tokens = [];
        $protected = [];
        $counter = 1;
        foreach ($fields as $value) {
            if (!is_string($value)) {
                continue;
            }
            preg_match_all('/\{[^}]+\}/', $value, $matches);
            if (!empty($matches[0])) {
                foreach ($matches[0] as $match) {
                    if (!isset($tokens[$match])) {
                        $token = '__PH_' . $counter . '__';
                        $counter++;
                        $tokens[$match] = $token;
                    }
                }
            }
        }
        foreach ($fields as $key => $value) {
            if (!is_string($value)) {
                $protected[$key] = $value;
                continue;
            }
            foreach ($tokens as $original => $token) {
                $value = str_replace($original, $token, $value);
            }
            $protected[$key] = $value;
        }

        $tokenMap = [];
        foreach ($tokens as $original => $token) {
            $tokenMap[$token] = $original;
        }

        return [$protected, $tokenMap];
    }

    private function restoreTokens(array $translated, array $tokenMap): array
    {
        foreach ($translated as $key => $value) {
            if (!is_string($value)) {
                continue;
            }
            foreach ($tokenMap as $token => $original) {
                $value = str_replace($token, $original, $value);
            }
            $translated[$key] = $value;
        }

        return $translated;
    }
}
