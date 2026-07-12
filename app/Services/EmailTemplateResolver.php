<?php

namespace App\Services;

use App\Models\EmailTemplate;
use App\Models\Location;

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

        // Fill in location/company tags the caller didn't already provide —
        // a caller-supplied value always wins. This is what's missing when
        // an email is sent with no location context yet (e.g. registration,
        // locationId=null): without this, {{location_name}} etc. reach
        // replaceTags() unresolved and (previously) were left raw in the
        // sent HTML.
        $tags = array_merge($this->defaultTags($locationId), $tags);

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

    /**
     * @return array<string, string>
     */
    private function defaultTags(?int $locationId): array
    {
        $location = $locationId ? Location::find($locationId) : null;

        return [
            'location_name'    => $location?->name ?: config('company.name'),
            'location_phone'   => $location?->phone ?: config('company.phone'),
            'location_email'   => $location?->email ?: config('company.email'),
            'location_address' => $location?->address_line1 ?: config('company.address'),
            'location_url'     => $location?->website ?: config('company.website'),
            // Location has no dedicated logo column — always the global
            // company logo, never location-specific.
            'location_logo'    => (string) config('company.logo_url'),
            'company_name'     => (string) config('company.name'),
            'company_kvk'      => (string) config('company.kvk'),
            'company_btw'      => (string) config('company.btw'),
        ];
    }
}
