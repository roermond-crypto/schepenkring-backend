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
        return $this->frontendUrl() . '/schepenkring-logo.png';
    }

    public function localizedFrontendPath(string $path, ?string $locale = null): string
    {
        $resolvedLocale = $this->resolveLocale($locale);

        return $this->frontendUrl() . '/' . $resolvedLocale . '/' . ltrim($path, '/');
    }
}
