<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Sentry\State\Scope;

class SentryContextMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        if (function_exists('\\Sentry\\configureScope')) {
            \Sentry\configureScope(function (Scope $scope) use ($request) {
                $user = $request->user();
                if ($user) {
                    $scope->setUser([
                        'id' => (string) $user->id,
                        'email' => $user->email,
                        'username' => $user->name,
                    ]);
                    $scope->setTag('user_id', (string) $user->id);
                    $scope->setTag('role', (string) $user->role);
                }

                $requestId = $request->attributes->get('request_id');
                if ($requestId) {
                    $scope->setTag('request_id', (string) $requestId);
                }

                $route = $request->route();
                if ($route) {
                    $scope->setTag('route', (string) $route->uri());
                }

                $params = $route ? $route->parameters() : [];
                $lang = $request->get('lang') ?: $request->header('Accept-Language');
                if ($lang) {
                    $scope->setTag('language', substr((string) $lang, 0, 5));
                }

                $harborId = $request->get('harbor_id') ?? $params['harbor_id'] ?? null;
                $boatId = $request->get('boat_id') ?? $params['boat_id'] ?? $params['boatId'] ?? null;
                $dealId = $request->get('deal_id') ?? $params['deal_id'] ?? $params['dealId'] ?? null;

                // If route contains yachts/{id}, treat id as boat_id
                if (!$boatId && $route && str_contains($route->uri(), 'yachts/{')) {
                    $boatId = $params['id'] ?? null;
                }

                if ($harborId) {
                    $scope->setTag('harbor_id', (string) $harborId);
                }
                if ($boatId) {
                    $scope->setTag('boat_id', (string) $boatId);
                }
                if ($dealId) {
                    $scope->setTag('deal_id', (string) $dealId);
                }
            });
        }

        return $next($request);
    }
}
