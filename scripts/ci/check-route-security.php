<?php

$routesJson = shell_exec('php artisan route:list --json');
if (!$routesJson) {
    fwrite(STDERR, "Unable to read routes. Ensure artisan is available.\n");
    exit(2);
}

$routes = json_decode($routesJson, true);
if (!is_array($routes)) {
    fwrite(STDERR, "Invalid route list JSON.\n");
    exit(2);
}

$violations = [];

$requireAuthPatterns = [
    '#^api/admin/#',
    '#^api/deals/#',
    '#^api/wallet#',
    '#^api/invoices#',
];

$moneyPatterns = [
    '#^api/deals/.*/contract#',
    '#^api/deals/.*/payments#',
    '#^api/deals/.*/escrow#',
    '#^api/wallet#',
];

foreach ($routes as $route) {
    $uri = $route['uri'] ?? '';
    $method = $route['method'] ?? '';
    $middleware = $route['middleware'] ?? [];

    if (is_string($middleware)) {
        $middleware = array_filter(array_map('trim', explode(',', $middleware)));
    }
    if (!is_array($middleware)) {
        $middleware = [];
    }

    $middlewareStr = implode('|', $middleware);
    $methods = array_map('trim', explode('|', $method));

    if (!str_starts_with($uri, 'api/')) {
        continue;
    }

    foreach ($requireAuthPatterns as $pattern) {
        if (preg_match($pattern, $uri)) {
            if (!in_array('auth:sanctum', $middleware, true)) {
                $violations[] = "{$method} {$uri} missing auth:sanctum";
            }
            break;
        }
    }

    if (preg_match('#^api/admin/#', $uri)) {
        $hasAdmin = in_array('admin.errors', $middleware, true) || in_array('admin.only', $middleware, true);
        if (!$hasAdmin) {
            $violations[] = "{$method} {$uri} missing admin.errors/admin.only";
        }
    }

    foreach ($moneyPatterns as $pattern) {
        if (preg_match($pattern, $uri)) {
            $hasAction = str_contains($middlewareStr, 'action:');
            $hasIdempotency = in_array('idempotency', $middleware, true);
            $hasSecurity = str_contains($middlewareStr, 'security_level:high');
            if (!($hasAction || $hasIdempotency || $hasSecurity)) {
                $violations[] = "{$method} {$uri} missing action/idempotency/security_level:high";
            }
            break;
        }
    }

    if (preg_match('#(export|download)#', $uri)) {
        $hasThrottle = false;
        foreach ($middleware as $mw) {
            if (str_starts_with($mw, 'throttle:')) {
                $hasThrottle = true;
                break;
            }
        }
        if (!$hasThrottle) {
            $violations[] = "{$method} {$uri} missing throttle for export/download";
        }
    }

    if (array_intersect($methods, ['POST', 'PATCH', 'DELETE'])) {
        if (preg_match('#^api/(deals|wallet|payments|escrow)#', $uri)) {
            $hasAction = str_contains($middlewareStr, 'action:');
            $hasIdempotency = in_array('idempotency', $middleware, true);
            if (!($hasAction || $hasIdempotency)) {
                $violations[] = "{$method} {$uri} missing idempotency/action for write";
            }
        }
    }
}

if (!empty($violations)) {
    fwrite(STDERR, "Route security policy violations:\n" . implode("\n", $violations) . "\n");
    exit(1);
}

echo "Route security policy check passed.\n";
