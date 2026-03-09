<?php

namespace App\Support;

use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class AuditResourceType
{
    public static function resolve(?string $type): ?string
    {
        if (! is_string($type)) {
            return null;
        }

        $type = trim($type);

        if ($type === '') {
            return null;
        }

        if (class_exists($type)) {
            return $type;
        }

        $alias = self::alias($type);
        if ($alias !== null) {
            return $alias;
        }

        $baseName = Str::of($type)
            ->replace(['-', '_'], ' ')
            ->trim()
            ->singular()
            ->studly()
            ->value();

        $candidate = "App\\Models\\{$baseName}";

        return class_exists($candidate) ? $candidate : $type;
    }

    /**
     * @return array<int, string>
     */
    public static function resolveMany(mixed $value): array
    {
        if (is_string($value)) {
            $value = array_filter(array_map('trim', explode(',', $value)));
        }

        return array_values(array_unique(array_filter(array_map(
            static fn (mixed $type) => self::resolve(is_string($type) ? $type : null),
            Arr::wrap($value)
        ))));
    }

    private static function alias(string $type): ?string
    {
        return match (Str::lower(trim($type))) {
            'user', 'users', 'client', 'clients', 'employee', 'employees', 'admin', 'admins' => \App\Models\User::class,
            'harbor', 'harbors', 'harbour', 'harbours', 'location', 'locations' => \App\Models\Location::class,
            default => null,
        };
    }
}
