<?php

namespace App\Http\Middleware;

use App\Services\AttributionService;
use Closure;
use Illuminate\Http\Request;

class CaptureAttributionMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        app(AttributionService::class)->capture($request);

        return $next($request);
    }
}
