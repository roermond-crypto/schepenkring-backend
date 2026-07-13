<?php

namespace App\Services;

use App\Models\Platform;
use App\Models\Yacht;
use Illuminate\Support\Facades\Http;

/**
 * Backs the Platform Configuration editor's "Test & Validation" tools.
 * No platform in this system exposes a real push API integration today
 * (BoatPlatformPublicationController::sync() only flips a status flag), so
 * `previewPayload()` builds a minimal, honest representation of what an
 * export would contain rather than pretending to call a real integration.
 */
class PlatformExportToolsService
{
    private const EXPORTABLE_STATUSES = ['approved', 'published', 'active'];

    public function __construct(
        private readonly OpenMarineService $openMarine,
    ) {
    }

    public function testConnection(Platform $platform): array
    {
        $target = match ($platform->export_method) {
            'api' => $platform->api_url,
            'openmarine', 'xml' => $platform->feed_url,
            default => null,
        };

        $checkedAt = now()->toIso8601String();

        if (! $target) {
            return [
                'success' => false,
                'message' => "No URL is configured to test for export method '{$platform->export_method}'.",
                'latency_ms' => null,
                'checked_at' => $checkedAt,
            ];
        }

        $start = microtime(true);

        try {
            $response = Http::timeout(8)->get($target);
            $latencyMs = (int) round((microtime(true) - $start) * 1000);

            return [
                'success' => $response->successful(),
                'message' => $response->successful()
                    ? "Reached {$target} (HTTP {$response->status()})."
                    : "{$target} responded with HTTP {$response->status()}.",
                'latency_ms' => $latencyMs,
                'checked_at' => $checkedAt,
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'message' => "Could not reach {$target}: " . $e->getMessage(),
                'latency_ms' => (int) round((microtime(true) - $start) * 1000),
                'checked_at' => $checkedAt,
            ];
        }
    }

    /**
     * @return array{valid: bool, issues: array<int, array{field: string, severity: string, message: string}>}
     */
    public function validateConfiguration(Platform $platform): array
    {
        $issues = [];

        if (empty(trim($platform->name ?? ''))) {
            $issues[] = ['field' => 'name', 'severity' => 'error', 'message' => 'Platform name is required.'];
        }

        match ($platform->export_method) {
            'api' => $this->validateApiMethod($platform, $issues),
            'xml' => $this->validateFeedMethod($platform, $issues),
            'openmarine' => $this->validateOpenMarineMethod($platform, $issues),
            default => null,
        };

        if (empty($platform->supported_countries)) {
            $issues[] = ['field' => 'supported_countries', 'severity' => 'warning', 'message' => 'No countries selected — this platform will not be eligible for country-scoped exports.'];
        }

        if (empty($platform->contact_email)) {
            $issues[] = ['field' => 'contact_email', 'severity' => 'warning', 'message' => 'No contact email set — useful when this integration breaks.'];
        }

        $hasErrors = collect($issues)->contains(fn (array $i) => $i['severity'] === 'error');

        return ['valid' => ! $hasErrors, 'issues' => $issues];
    }

    private function validateApiMethod(Platform $platform, array &$issues): void
    {
        if (empty($platform->api_url)) {
            $issues[] = ['field' => 'api_url', 'severity' => 'error', 'message' => 'API URL is required for the API export method.'];
        }
        if (! $platform->hasCredentialSecret('api_key')) {
            $issues[] = ['field' => 'credentials.api_key', 'severity' => 'error', 'message' => 'An API key is required for the API export method.'];
        }
        if (! $platform->hasCredentialSecret('api_secret')) {
            $issues[] = ['field' => 'credentials.api_secret', 'severity' => 'warning', 'message' => 'No API secret set — confirm this platform genuinely doesn\'t require one.'];
        }
    }

    private function validateFeedMethod(Platform $platform, array &$issues): void
    {
        if (empty($platform->feed_url)) {
            $issues[] = ['field' => 'feed_url', 'severity' => 'error', 'message' => 'A feed URL is required for the XML feed export method.'];
        }
        $this->validateCategoryMap($platform, $issues);
    }

    private function validateOpenMarineMethod(Platform $platform, array &$issues): void
    {
        if (empty($platform->openmarine_dealer_id)) {
            $issues[] = ['field' => 'openmarine_dealer_id', 'severity' => 'warning', 'message' => 'No dealer ID set — the platform may not be able to link exported boats to your account.'];
        }
        $this->validateCategoryMap($platform, $issues);
    }

    private function validateCategoryMap(Platform $platform, array &$issues): void
    {
        $map = $platform->openmarine_category_map ?? [];
        foreach ($map as $ours => $theirs) {
            if (empty(trim((string) $theirs))) {
                $issues[] = ['field' => 'openmarine_category_map', 'severity' => 'warning', 'message' => "Category '{$ours}' has no mapped platform code."];
            }
        }
    }

    public function previewFeed(Platform $platform, ?int $yachtId): array
    {
        $yacht = $this->resolveYacht($yachtId);
        if (! $yacht) {
            return ['error' => 'No exportable yacht was found to preview.'];
        }

        $result = $this->openMarine->generate($yacht);

        return array_merge($result, ['yacht_id' => $yacht->id, 'yacht_name' => $yacht->boat_name]);
    }

    public function previewPayload(Platform $platform, ?int $yachtId): array
    {
        $yacht = $this->resolveYacht($yachtId);
        if (! $yacht) {
            return ['error' => 'No exportable yacht was found to preview.'];
        }

        $categoryMap = $platform->openmarine_category_map ?? [];
        $mappedCategory = $categoryMap[$yacht->boat_type] ?? $yacht->boat_type;

        $payload = [
            'external_reference' => (string) $yacht->id,
            'title' => $yacht->boat_name,
            'manufacturer' => $yacht->manufacturer,
            'model' => $yacht->model,
            'year' => $yacht->year,
            'price' => $yacht->price,
            'category' => $mappedCategory,
            'main_image_url' => $yacht->main_image_url,
            'description' => $yacht->short_description_nl ?: $yacht->short_description_en,
        ];

        return ['payload' => $payload, 'yacht_id' => $yacht->id, 'yacht_name' => $yacht->boat_name];
    }

    private function resolveYacht(?int $yachtId): ?Yacht
    {
        if ($yachtId) {
            return Yacht::find($yachtId);
        }

        return Yacht::whereIn('status', self::EXPORTABLE_STATUSES)
            ->latest('updated_at')
            ->first();
    }
}
