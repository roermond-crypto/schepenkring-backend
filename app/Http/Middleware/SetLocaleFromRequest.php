<?php

namespace App\Http\Middleware;

use App\Support\CopilotLanguage;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sets the app locale from the request's Accept-Language header (the
 * frontend's axios client sends this matching the current /nl|en|de|fr/
 * URL segment — see api.ts) so that everything locale-dependent and
 * request-scoped — most importantly Laravel's validator, which otherwise
 * always falls back to its compiled-in English messages — respects the
 * user's actual selected language instead of defaulting to English
 * regardless of locale.
 */
class SetLocaleFromRequest
{
    public function __construct(private readonly CopilotLanguage $language)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        // 'nl' (not config('app.fallback_locale'), which is Laravel's stock
        // 'en') — this is a Dutch-first brokerage; the frontend's own
        // DEFAULT_LOCALE is 'nl' too (src/lib/i18n.ts).
        $locale = $this->language->fromAcceptLanguage($request->header('Accept-Language'))
            ?? $this->language->normalize($request->header('X-Locale'))
            ?? 'nl';

        app()->setLocale($locale);

        return $next($request);
    }
}
