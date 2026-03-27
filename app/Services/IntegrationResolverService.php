<?php

namespace App\Services;

use App\Models\Integration;

class IntegrationResolverService
{
    /**
     * Resolve the best matching integration record.
     *
     * Priority:
     *  1. Active, location-specific record for the given type + environment
     *  2. Active, global record (location_id IS NULL) for the given type + environment
     */
    public function resolve(string $type, ?int $locationId = null, string $environment = 'live'): ?Integration
    {
        // Try location-specific first
        if ($locationId) {
            $record = Integration::active()
                ->forType($type)
                ->forEnvironment($environment)
                ->forLocation($locationId)
                ->first();

            if ($record) {
                return $record;
            }
        }

        // Fallback to global
        return Integration::active()
            ->forType($type)
            ->forEnvironment($environment)
            ->whereNull('location_id')
            ->first();
    }

    /**
     * Shortcut: get the decrypted API key.
     */
    public function getApiKey(string $type, ?int $locationId = null, string $environment = 'live'): ?string
    {
        return $this->resolve($type, $locationId, $environment)?->apiKey();
    }

    /**
     * Shortcut: get the decrypted password.
     */
    public function getPassword(string $type, ?int $locationId = null, string $environment = 'live'): ?string
    {
        return $this->resolve($type, $locationId, $environment)?->password();
    }

    /**
     * Shortcut: get the username.
     */
    public function getUsername(string $type, ?int $locationId = null, string $environment = 'live'): ?string
    {
        return $this->resolve($type, $locationId, $environment)?->username;
    }
}
