<?php

namespace App\Services;

use App\Models\User;
use App\Models\Yacht;

class BoatTaskTemplateRenderer
{
    public function matchesBoatTypeFilter(?array $filter, ?string $boatType): bool
    {
        if (empty($filter)) {
            return true;
        }

        if (empty($boatType)) {
            return false;
        }

        $normalizedType = strtolower(trim($boatType));
        $normalizedFilter = array_values(array_filter(array_map(
            static fn ($value) => strtolower(trim((string) $value)),
            (array) $filter
        )));

        return in_array($normalizedType, $normalizedFilter, true);
    }

    public function render(?string $value, Yacht $yacht, ?User $recipient = null): ?string
    {
        if ($value === null || $value === '') {
            return $value;
        }

        $baseUrl = rtrim((string) env('FRONTEND_URL', 'http://localhost:3000'), '/');
        $locale = app()->getLocale() ?: 'nl';
        $boatId = (string) $yacht->id;
        $boatName = $yacht->boat_name ?: "Yacht #{$boatId}";
        $boatDisplay = "{$boatName} (#{$boatId})";
        $boatType = (string) ($yacht->boat_type ?? '');
        $vesselId = (string) ($yacht->vessel_id ?? $boatId);
        $clientName = (string) ($recipient?->name ?? '');
        $boatUrl = "{$baseUrl}/{$locale}/dashboard/admin/fleet/{$boatId}";

        return strtr($value, [
            '{boat_url}' => $boatUrl,
            '{{boat_url}}' => $boatUrl,
            '#{boat_id}' => $boatDisplay,
            '{boat_id}' => $boatId,
            '{{boat_id}}' => $boatId,
            '{boat_display}' => $boatDisplay,
            '{{boat_display}}' => $boatDisplay,
            '{boat_name}' => $boatName,
            '{{boat_name}}' => $boatName,
            '{yacht_name}' => $boatName,
            '{{yacht_name}}' => $boatName,
            '{boat_type}' => $boatType,
            '{{boat_type}}' => $boatType,
            '{vessel_id}' => $vesselId,
            '{{vessel_id}}' => $vesselId,
            '{client_name}' => $clientName,
            '{{client_name}}' => $clientName,
        ]);
    }
}
