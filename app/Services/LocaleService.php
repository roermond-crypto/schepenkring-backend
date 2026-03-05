<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class LocaleService
{
    public function supported(): array
    {
        return config('locales.supported', ['nl']);
    }

    public function default(): string
    {
        return (string) config('locales.default', 'nl');
    }

    public function fallbackChain(string $locale): array
    {
        $fallbacks = config('locales.fallbacks', []);
        $chain = array_merge([$locale], $fallbacks[$locale] ?? []);
        $unique = [];
        foreach ($chain as $item) {
            $item = strtolower((string) $item);
            if (!in_array($item, $unique, true) && in_array($item, $this->supported(), true)) {
                $unique[] = $item;
            }
        }

        if (!in_array($this->default(), $unique, true)) {
            $unique[] = $this->default();
        }

        return $unique;
    }

    public function resolve(Request $request, ?User $user = null): array
    {
        $supported = $this->supported();
        $default = $this->default();

        $userLocale = $user?->preferred_locale ? strtolower($user->preferred_locale) : null;
        if ($userLocale && in_array($userLocale, $supported, true)) {
            return [
                'locale' => $userLocale,
                'fallbacks' => $this->fallbackChain($userLocale),
            ];
        }

        $queryLocale = $request->query('locale') ?? $request->query('language');
        $queryLocale = $queryLocale ? strtolower((string) $queryLocale) : null;
        if ($queryLocale && in_array($queryLocale, $supported, true)) {
            return [
                'locale' => $queryLocale,
                'fallbacks' => $this->fallbackChain($queryLocale),
            ];
        }

        $accept = $this->pickFromAcceptLanguage($request->header('Accept-Language'));
        if ($accept && in_array($accept, $supported, true)) {
            return [
                'locale' => $accept,
                'fallbacks' => $this->fallbackChain($accept),
            ];
        }

        return [
            'locale' => $default,
            'fallbacks' => $this->fallbackChain($default),
        ];
    }

    private function pickFromAcceptLanguage(?string $header): ?string
    {
        if (!$header) {
            return null;
        }

        $parts = explode(',', $header);
        foreach ($parts as $part) {
            $lang = trim($part);
            if ($lang === '') {
                continue;
            }
            $lang = explode(';', $lang)[0] ?? $lang;
            $lang = strtolower($lang);
            $lang = Str::substr($lang, 0, 2);
            if (in_array($lang, $this->supported(), true)) {
                return $lang;
            }
        }

        return null;
    }
}
