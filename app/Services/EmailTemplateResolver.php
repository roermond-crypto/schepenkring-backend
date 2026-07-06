<?php

namespace App\Services;

use App\Models\EmailTemplate;

/**
 * Resolves the correct EmailTemplate for a given event type, location, and language.
 *
 * Resolution order:
 *   1. Location-specific template (matching type + location_id)
 *   2. Global template (matching type, is_global=true, location_id=null)
 *   3. null — caller falls back to legacy Blade mailable
 */
class EmailTemplateResolver
{
    public function resolve(string $type, ?int $locationId, string $lang = 'nl'): ?EmailTemplate
    {
        // 1. Try location-specific template
        if ($locationId !== null) {
            $template = EmailTemplate::where('type', $type)
                ->where('location_id', $locationId)
                ->where('is_active', true)
                ->where('is_archived', false)
                ->first();

            if ($template !== null) {
                return $template;
            }
        }

        // 2. Fall back to global template
        return EmailTemplate::where('type', $type)
            ->where('is_global', true)
            ->whereNull('location_id')
            ->where('is_active', true)
            ->where('is_archived', false)
            ->first();
    }

    /**
     * Resolve and immediately render to HTML + subject.
     * Returns null when no template exists (caller should use Blade fallback).
     *
     * @param  array  $tags   Tag replacements, e.g. ['buyer_name' => 'Jan']
     * @return array{html: string, subject: string}|null
     */
    public function resolveAndRender(
        string $type,
        ?int $locationId,
        string $lang,
        array $tags = [],
    ): ?array {
        $template = $this->resolve($type, $locationId, $lang);

        if ($template === null) {
            return null;
        }

        $renderer = app(EmailTemplateRendererService::class);

        $branding = $template->primary_color_override
            ? ['primary_color' => $template->primary_color_override]
            : null;

        $html    = $renderer->render($template->blocks, $lang, $tags, $branding);
        $subject = $renderer->replaceTags($template->subject[$lang] ?? $template->subject['nl'] ?? '', $tags);

        return [
            'html'      => $html,
            'subject'   => $subject,
            'preheader' => $template->preheader ?? '',
            'from_name' => $template->sender_name_override,
            'from_email'=> $template->sender_email_override,
            'reply_to'  => $template->reply_to_override,
            'template'  => $template,
        ];
    }
}
