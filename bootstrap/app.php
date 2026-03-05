<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Auth\Access\AuthorizationException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // 1. We disable the custom Cors::class to stop the "*, *" duplicate error.
        // Laravel 11 handles CORS automatically via config/cors.php.
        // $middleware->append(\App\Http\Middleware\Cors::class); 

        // 2. Required for Sanctum authentication
        $middleware->statefulApi();
        // Add your middleware here
        $middleware->api(append: [
            \App\Http\Middleware\RequestIdMiddleware::class,
            \App\Http\Middleware\CaptureAttributionMiddleware::class,
            \App\Http\Middleware\SentryContextMiddleware::class,
            \App\Http\Middleware\LogApiRequests::class,
            \App\Http\Middleware\SecurityHeaders::class,
        ]);

        $middleware->web(append: [
            \App\Http\Middleware\CaptureAttributionMiddleware::class,
            \App\Http\Middleware\SecurityHeaders::class,
        ]);
        
        // Or you can add it to specific routes using aliases
        $middleware->alias([
            'log.api' => \App\Http\Middleware\LogApiRequests::class,
        ]);

        // 3. Register your Role/Permission aliases
        $middleware->alias([
            'role' => \Spatie\Permission\Middleware\RoleMiddleware::class,
            'permission' => \Spatie\Permission\Middleware\PermissionMiddleware::class,
            'admin.errors' => \App\Http\Middleware\EnsureAdminOrStaff::class,
            'admin.only' => \App\Http\Middleware\EnsureAdmin::class,
            'step_up' => \App\Http\Middleware\RequireStepUp::class,
            'security_level' => \App\Http\Middleware\RequireSecurityLevel::class,
            'idempotency' => \App\Http\Middleware\RequireIdempotencyKey::class,
            'action' => \App\Http\Middleware\EnforceActionSecurity::class,
            'internal.secret' => \App\Http\Middleware\VerifyInternalSecret::class,
            'onboarding.active' => \App\Http\Middleware\EnsureActiveUser::class,
            
            // We comment these out because if the class files have errors, 
            // they will throw a 500 Internal Server Error.
            // 'cors.public'  => \App\Http\Middleware\PublicCors::class,
            // 'cors.private' => \App\Http\Middleware\PrivateCors::class,
        ]);

        // 4. Ensure API doesn't trip over CSRF tokens
        $middleware->validateCsrfTokens(except: [
            'api/*',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (Throwable $e, Request $request) {
            if (!($request->is('api/*') || $request->expectsJson())) {
                return null;
            }

            $status = 500;
            if ($e instanceof HttpExceptionInterface) {
                $status = $e->getStatusCode();
            }

            $errorId = null;
            if (function_exists('\\Sentry\\captureException')) {
                $errorId = \Sentry\captureException($e);
            }

            $reference = $errorId ? (string) $errorId : 'ERR-' . strtoupper(Str::random(6));

            if ($e instanceof ValidationException) {
                return response()->json([
                    'message' => $e->getMessage(),
                    'errors' => $e->errors(),
                    'error_reference' => $reference,
                ], $e->status)->header('X-Error-Reference', $reference);
            }

            if ($e instanceof AuthenticationException) {
                return response()->json([
                    'message' => 'Unauthenticated.',
                    'error_reference' => $reference,
                ], 401)->header('X-Error-Reference', $reference);
            }

            if ($e instanceof AuthorizationException) {
                return response()->json([
                    'message' => 'Forbidden.',
                    'error_reference' => $reference,
                ], 403)->header('X-Error-Reference', $reference);
            }

            return response()->json([
                'message' => 'Something went wrong.',
                'error_reference' => $reference,
            ], $status)->header('X-Error-Reference', $reference);
        });
    })
    ->create();
