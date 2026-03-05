<?php

namespace App\Security;

final class ActionSecurityRegistry
{
    private static array $actions = [];

    public static function define(
        string $key,
        string $level = 'low',
        ?int $fresh = null,
        bool $audit = false,
        bool $idempotency = false,
        bool $snapshot = false,
        ?string $model = null,
        ?string $routeParam = null,
        array $with = []
    ): void {
        self::$actions[$key] = [
            'key' => $key,
            'level' => $level,
            'fresh' => $fresh,
            'audit' => $audit,
            'idempotency' => $idempotency,
            'snapshot' => $snapshot,
            'model' => $model,
            'route_param' => $routeParam,
            'with' => $with,
        ];
    }

    public static function get(string $key): ?array
    {
        return self::$actions[$key] ?? null;
    }

    public static function all(): array
    {
        return self::$actions;
    }
}
