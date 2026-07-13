<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withCommands([
        __DIR__.'/../app/Console/Commands',
    ])
    ->withBroadcasting(__DIR__.'/../routes/channels.php', ['middleware' => ['auth:sanctum']])
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->redirectGuestsTo(function (\Illuminate\Http\Request $request) {
            if ($request->is('api/*')) {
                return null;
            }
            return route('login');
        });

        $middleware->web(append: [
            \App\Http\Middleware\HandleInertiaRequests::class,
            \Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets::class,
        ]);

        $middleware->api(prepend: [
            \App\Http\Middleware\SetLocaleFromRequest::class,
        ]);

        $middleware->api(append: [
            \App\Http\Middleware\EnsureActiveUser::class,
            \App\Http\Middleware\ResolveImpersonation::class,
            \App\Http\Middleware\SentryContextMiddleware::class,
        ]);

        $middleware->alias([
            'role' => \App\Http\Middleware\EnsureUserHasRole::class,
            'admin' => \App\Http\Middleware\EnsureAdmin::class,
            'admin.errors' => \App\Http\Middleware\EnsureAdminOrStaff::class,
            'bid.session' => \App\Http\Middleware\EnsureBidSession::class,
            'internal.secret' => \App\Http\Middleware\VerifyInternalSecret::class,
            'retell.tools' => \App\Http\Middleware\VerifyRetellToolSecret::class,
            'auth.optional' => \App\Http\Middleware\OptionalSanctumAuth::class,
            'onboarding.active' => \App\Http\Middleware\EnsureActiveUser::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->renderable(function (\Illuminate\Auth\AuthenticationException $e, $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'message' => 'Unauthenticated.'
                ], 401);
            }
        });

        // Thrown by Laravel's ValidatePostSize middleware — which runs in the
        // global stack before SetLocaleFromRequest ever gets a chance to set
        // app()->getLocale(), so the locale is resolved from the request
        // header directly here instead of relying on app()->getLocale().
        $exceptions->renderable(function (\Illuminate\Http\Exceptions\PostTooLargeException $e, $request) {
            if ($request->is('api/*')) {
                $language = new \App\Support\CopilotLanguage();
                $locale = $language->fromAcceptLanguage($request->header('Accept-Language'))
                    ?? $language->fromAcceptLanguage($request->header('X-Locale'))
                    ?? 'nl';

                return response()->json([
                    'message' => trans('errors.upload_too_large', [], $locale),
                ], 413);
            }
        });
    })->create();
