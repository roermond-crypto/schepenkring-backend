<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Yacht;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class YachtShiftSyncService
{
    private string $apiBase;
    private string $apiKey;

    public function __construct()
    {
        $this->apiBase = config('services.yachtshift.api_url', 'https://api.yachtshift.com/v1');
        $this->apiKey = config('services.yachtshift.api_key', '');
    }

    public function import(bool $dryRun = false): array
    {
        $response = Http::withToken($this->apiKey)
            ->timeout(30)
            ->get("{$this->apiBase}/listings");

        if ($response->failed()) {
            Log::error('YachtShiftSyncService: import failed', ['status' => $response->status()]);
            throw new \RuntimeException('YachtShift API unavailable: ' . $response->status());
        }

        $listings = $response->json('data', []);
        $imported = 0;
        $conflicts = [];
        $auditIds = [];

        foreach ($listings as $listing) {
            $externalId = $listing['id'] ?? null;
            if (!$externalId) {
                continue;
            }

            $existing = Yacht::where('yachtshift_id', $externalId)->first();

            if ($existing) {
                $remoteUpdatedAt = $listing['updated_at'] ?? null;
                if ($remoteUpdatedAt && $existing->updated_at && $existing->updated_at->toISOString() > $remoteUpdatedAt) {
                    $conflicts[] = ['yacht_id' => $existing->id, 'external_id' => $externalId, 'reason' => 'local_newer'];
                    continue;
                }
            }

            if (!$dryRun) {
                $mapped = $this->mapImportFields($listing);

                if ($existing) {
                    $existing->update($mapped);
                    $yacht = $existing;
                } else {
                    $yacht = Yacht::create(array_merge($mapped, ['yachtshift_id' => $externalId]));
                }

                $log = AuditLog::create([
                    'user_id' => null,
                    'action' => 'yachtshift_import',
                    'auditable_type' => Yacht::class,
                    'auditable_id' => $yacht->id,
                    'new_values' => $mapped,
                ]);
                $auditIds[] = $log->id;
                $imported++;
            }
        }

        return [
            'imported' => $imported,
            'conflicts' => count($conflicts),
            'conflict_details' => $conflicts,
            'audit_log_ids' => $auditIds,
            'dry_run' => $dryRun,
        ];
    }

    public function export(bool $dryRun = false): array
    {
        $yachts = Yacht::where('status', 'published')
            ->whereNull('yachtshift_synced_at')
            ->orWhere('yachtshift_synced_at', '<', now()->subHours(24))
            ->limit(50)
            ->get();

        $exported = 0;
        $auditIds = [];

        foreach ($yachts as $yacht) {
            $payload = $this->mapExportFields($yacht);

            if (!$dryRun) {
                $response = Http::withToken($this->apiKey)
                    ->post("{$this->apiBase}/listings", $payload);

                if ($response->successful()) {
                    $yacht->update(['yachtshift_synced_at' => now()]);

                    $log = AuditLog::create([
                        'user_id' => null,
                        'action' => 'yachtshift_export',
                        'auditable_type' => Yacht::class,
                        'auditable_id' => $yacht->id,
                        'new_values' => $payload,
                    ]);
                    $auditIds[] = $log->id;
                    $exported++;
                } else {
                    Log::warning('YachtShiftSyncService: export failed for yacht', [
                        'yacht_id' => $yacht->id,
                        'status' => $response->status(),
                    ]);
                }
            }
        }

        return ['exported' => $exported, 'audit_log_ids' => $auditIds, 'dry_run' => $dryRun];
    }

    private function mapImportFields(array $listing): array
    {
        return [
            'name' => $listing['name'] ?? $listing['title'] ?? null,
            'brand' => $listing['brand'] ?? $listing['make'] ?? null,
            'model' => $listing['model'] ?? null,
            'year' => $listing['year'] ?? null,
            'price' => $listing['price'] ?? $listing['asking_price'] ?? null,
            'status' => 'imported',
        ];
    }

    private function mapExportFields(Yacht $yacht): array
    {
        return [
            'external_id' => (string) $yacht->id,
            'name' => $yacht->name,
            'brand' => $yacht->brand,
            'model' => $yacht->model,
            'year' => $yacht->year,
            'price' => $yacht->price,
        ];
    }
}
