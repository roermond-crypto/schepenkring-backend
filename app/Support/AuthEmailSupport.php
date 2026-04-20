<?php

namespace App\Support;

class AuthEmailSupport
{
    public function __construct(private readonly CopilotLanguage $language)
    {
    }

    public function resolveLocale(?string $preferredLocale = null, ?string $acceptLanguage = null): string
    {
        return $this->language->normalize($preferredLocale)
            ?? $this->language->fromAcceptLanguage($acceptLanguage)
            ?? 'en';
    }

    public function frontendUrl(): string
    {
        $configured = (string) (config('app.frontend_url') ?: config('app.url') ?: 'http://localhost:3000');

        return rtrim($configured, '/');
    }

    public function logoUrl(): string
    {
        // 1. .env override (e.g. a CDN URL)
        $override = config('app.mail_logo_url');
        if ($override) {
            return $override;
        }

        // 2. Inline base64 – always works, even on localhost / dev env
        $logoPath = public_path('schepenkring-logo.png');
        if (file_exists($logoPath)) {
            $data = base64_encode(file_get_contents($logoPath));
            return 'data:image/png;base64,' . $data;
        }

        // 3. Fallback: try frontend URL
        return $this->frontendUrl() . '/schepenkring-logo.png';
    }

    public function localizedFrontendPath(string $path, ?string $locale = null): string
    {
        $resolvedLocale = $this->resolveLocale($locale);

        return $this->frontendUrl() . '/' . $resolvedLocale . '/' . ltrim($path, '/');
    }
}
