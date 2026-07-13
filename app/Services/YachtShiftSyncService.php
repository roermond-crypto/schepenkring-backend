<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\BoatField;
use App\Models\BoatFieldChange;
use App\Models\Location;
use App\Models\User;
use App\Models\Yacht;
use App\Models\YachtshiftSyncConflict;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class YachtShiftSyncService
{
    private const EXPORT_FIELD_MAP = [
        'boat_name' => 'name',
        'manufacturer' => 'brand',
        'model' => 'model',
        'year' => 'year',
        'price' => 'price',
        'status' => 'status',
        'short_description_nl' => 'description',
        'external_url' => 'url',
    ];

    private string $apiBase;
    private string $apiKey;
    private bool $guardEmptyResponses;
    private int $exportLimit;

    public function __construct()
    {
        $this->apiBase = rtrim((string) config('services.yachtshift.api_url', 'https://api.yachtshift.com/v1'), '/');
        $this->apiKey = (string) config('services.yachtshift.api_key', '');
        $this->guardEmptyResponses = (bool) config('services.yachtshift.guard_empty_imports', true);
        $this->exportLimit = (int) config('services.yachtshift.export_limit', 50);
    }

    public function import(bool $dryRun = false, ?User $actor = null): array
    {
        $response = Http::withToken($this->apiKey)
            ->acceptJson()
            ->timeout(30)
            ->get("{$this->apiBase}/listings");

        if ($response->failed()) {
            $this->logSyncAudit('yachtshift.import.failed', null, 'FAIL', [
                'direction' => 'import',
                'source' => 'yachtshift',
                'target' => 'schepenkring',
                'sync_type' => 'full',
                'error' => "YachtShift API unavailable: {$response->status()}",
            ], null, null, $actor);

            Log::error('YachtShiftSyncService: import failed', ['status' => $response->status()]);
            throw new \RuntimeException('YachtShift API unavailable: ' . $response->status());
        }

        $listings = $this->extractListings($response->json());

        if ($this->guardEmptyResponses && count($listings) === 0) {
            $this->logSyncAudit('yachtshift.import.empty_response', null, 'FAIL', [
                'direction' => 'import',
                'source' => 'yachtshift',
                'target' => 'schepenkring',
                'sync_type' => 'full',
                'error' => 'YachtShift returned zero listings.',
            ], null, null, $actor);

            throw new \RuntimeException('YachtShift returned an empty listing response; import aborted.');
        }

        $imported = 0;
        $updated = 0;
        $skipped = 0;
        $conflicts = [];
        $auditIds = [];
        $boatSummaries = [];

        foreach ($listings as $listing) {
            $externalId = $this->externalIdFromListing($listing);
            if (! $externalId) {
                $skipped++;
                continue;
            }

            $existing = Yacht::query()->where('yachtshift_id', $externalId)->first();
            $mapped = $this->filterImportFields($this->mapImportFields($listing, $externalId));

            if ($existing) {
                $fieldConflicts = $this->detectConflicts($existing, $mapped, $listing, $externalId, $dryRun, $actor);
                if (count($fieldConflicts) > 0) {
                    $conflicts = array_merge($conflicts, $fieldConflicts);
                    $skipped++;
                    if (! $dryRun) {
                        $existing->update(['yachtshift_publish_status' => 'needs_review']);
                    }
                    continue;
                }
            }

            if ($dryRun) {
                $existing ? $updated++ : $imported++;
                $boatSummaries[] = $this->buildBoatSummary('import', $mapped, $existing, true);
                continue;
            }

            $before = $existing?->only(array_keys($mapped)) ?? [];
            $payload = array_merge($mapped, [
                'yachtshift_id' => $externalId,
                'yachtshift_synced_at' => now(),
                'yachtshift_last_imported_at' => now(),
                'yachtshift_publish_status' => 'import_synced',
                'source' => 'yachtshift',
                'source_identifier' => $externalId,
            ]);

            if (! $existing && empty($payload['vessel_id'])) {
                $payload['vessel_id'] = 'YS-' . $externalId;
            }

            if (! $existing && empty($payload['status'])) {
                $payload['status'] = 'imported';
            }

            // Same location_id/ref_harbor_id split that previously left yachts
            // created via the wizard and public intake invisible to
            // location-scoped staff (visibleYachtsQuery() filters on
            // ref_harbor_id) — YachtShift-imported yachts need both columns
            // set on creation too.
            if (! $existing && empty($payload['location_id'])) {
                $resolvedLocationId = $this->resolveImportLocationId($listing, $actor);
                if ($resolvedLocationId) {
                    $payload['location_id'] = $resolvedLocationId;
                    $payload['ref_harbor_id'] = $resolvedLocationId;
                }
            }

            if ($existing) {
                $existing->fill($payload);
                $existing->yachtshift_sync_summary = $this->buildBoatSummary('import', $mapped, $existing, false);
                $existing->save();
                $yacht = $existing;
                $updated++;
            } else {
                $payload['yachtshift_sync_summary'] = $this->buildBoatSummary('import', $mapped, null, false);
                $yacht = Yacht::create($payload);
                $imported++;
            }

            $after = $yacht->fresh()->only(array_keys($mapped));
            $this->recordFieldChanges($yacht, $before, $after, 'import', 'yachtshift', 'schepenkring', 'full', $actor);

            $log = $this->logSyncAudit('yachtshift.import.boat_synced', $yacht, 'SUCCESS', [
                'direction' => 'import',
                'source' => 'yachtshift',
                'target' => 'schepenkring',
                'sync_type' => $existing ? 'update' : 'create',
                'external_id' => $externalId,
                'price_changed' => $this->fieldChanged('price', $before, $after),
            ], $before, $after, $actor);

            $auditIds[] = $log->id;
            $boatSummaries[] = $yacht->yachtshift_sync_summary;
        }

        $summary = $this->buildSyncSummary('import', [
            'imported' => $imported,
            'updated' => $updated,
            'skipped' => $skipped,
            'conflicts' => count($conflicts),
            'dry_run' => $dryRun,
        ]);

        $syncLog = $this->logSyncAudit('yachtshift.import.completed', null, 'SUCCESS', [
            'direction' => 'import',
            'source' => 'yachtshift',
            'target' => 'schepenkring',
            'sync_type' => 'full',
            'summary' => $summary,
            'dry_run' => $dryRun,
        ], null, null, $actor);
        $auditIds[] = $syncLog->id;

        return [
            'imported' => $imported,
            'updated' => $updated,
            'skipped' => $skipped,
            'conflicts' => count($conflicts),
            'conflict_details' => $conflicts,
            'audit_log_ids' => $auditIds,
            'summary' => $summary,
            'boat_summaries' => $boatSummaries,
            'dry_run' => $dryRun,
        ];
    }

    public function export(bool $dryRun = false, ?User $actor = null): array
    {
        $yachts = Yacht::query()
            ->whereIn('yachtshift_publish_status', ['queued', 'published'])
            ->where('is_test', false)
            ->where(function ($query) {
                $query->where('yachtshift_publish_status', 'queued')
                    ->orWhereNull('yachtshift_synced_at')
                    ->orWhere('yachtshift_synced_at', '<', now()->subHours(24));
            })
            ->limit($this->exportLimit)
            ->get();

        $exported = 0;
        $failed = 0;
        $auditIds = [];
        $boatSummaries = [];

        foreach ($yachts as $yacht) {
            $result = $this->exportYacht($yacht, $dryRun, $actor);
            $boatSummaries[] = $result['summary'] ?? null;
            $auditIds = array_merge($auditIds, $result['audit_log_ids'] ?? []);

            if (($result['success'] ?? false) === true) {
                $exported++;
            } else {
                $failed++;
            }
        }

        $summary = $this->buildSyncSummary('export', [
            'exported' => $exported,
            'failed' => $failed,
            'dry_run' => $dryRun,
        ]);

        $syncLog = $this->logSyncAudit($failed > 0 ? 'yachtshift.export.completed_with_errors' : 'yachtshift.export.completed', null, $failed > 0 ? 'FAIL' : 'SUCCESS', [
            'direction' => 'export',
            'source' => 'schepenkring',
            'target' => 'yachtshift',
            'sync_type' => 'batch',
            'summary' => $summary,
            'dry_run' => $dryRun,
            'errors' => $failed,
        ], null, null, $actor);
        $auditIds[] = $syncLog->id;

        return [
            'exported' => $exported,
            'failed' => $failed,
            'audit_log_ids' => $auditIds,
            'summary' => $summary,
            'boat_summaries' => array_values(array_filter($boatSummaries)),
            'dry_run' => $dryRun,
        ];
    }

    public function publishYacht(Yacht $yacht, bool $dryRun = false, ?User $actor = null): array
    {
        if (! $dryRun) {
            $yacht->forceFill([
                'yachtshift_publish_status' => 'queued',
                'yachtshift_last_export_error' => null,
            ])->save();
        }

        return $this->exportYacht($yacht->fresh(), $dryRun, $actor);
    }

    public function retryExport(Yacht $yacht, bool $dryRun = false, ?User $actor = null): array
    {
        if (! $dryRun) {
            $yacht->forceFill([
                'yachtshift_publish_status' => 'queued',
                'yachtshift_last_export_error' => null,
            ])->save();
        }

        return $this->exportYacht($yacht->fresh(), $dryRun, $actor);
    }

    public function exportYacht(Yacht $yacht, bool $dryRun = false, ?User $actor = null): array
    {
        $payload = $this->mapExportFields($yacht);
        $summary = $this->buildBoatSummary('export', $payload, $yacht, $dryRun);

        if ($dryRun) {
            return [
                'success' => true,
                'payload' => $payload,
                'summary' => $summary,
                'audit_log_ids' => [],
                'dry_run' => true,
            ];
        }

        $before = $yacht->only([
            'yachtshift_id',
            'yachtshift_synced_at',
            'yachtshift_publish_status',
            'yachtshift_last_export_error',
            'yachtshift_sync_summary',
        ]);

        $request = Http::withToken($this->apiKey)->acceptJson()->timeout(30);
        $response = $yacht->yachtshift_id
            ? $request->put("{$this->apiBase}/listings/{$yacht->yachtshift_id}", $payload)
            : $request->post("{$this->apiBase}/listings", $payload);

        if ($response->failed()) {
            $error = $this->responseError($response->status(), $response->json(), $response->body());
            $yacht->forceFill([
                'yachtshift_publish_status' => 'failed',
                'yachtshift_last_export_error' => $error,
                'yachtshift_sync_summary' => "Export failed for {$this->boatLabel($yacht)}: {$error}",
            ])->save();

            $log = $this->logSyncAudit('yachtshift.export.failed', $yacht, 'FAIL', [
                'direction' => 'export',
                'source' => 'schepenkring',
                'target' => 'yachtshift',
                'sync_type' => 'single_boat',
                'error' => $error,
                'external_id' => $yacht->yachtshift_id,
            ], $before, $yacht->fresh()->only(array_keys($before)), $actor);

            Log::warning('YachtShiftSyncService: export failed for yacht', [
                'yacht_id' => $yacht->id,
                'status' => $response->status(),
                'error' => $error,
            ]);

            return [
                'success' => false,
                'message' => $error,
                'summary' => $yacht->fresh()->yachtshift_sync_summary,
                'audit_log_ids' => [$log->id],
                'dry_run' => false,
            ];
        }

        $externalId = $this->externalIdFromResponse($response->json()) ?? $yacht->yachtshift_id;

        $yacht->forceFill([
            'yachtshift_id' => $externalId,
            'yachtshift_synced_at' => now(),
            'yachtshift_last_exported_at' => now(),
            'yachtshift_publish_status' => 'published',
            'yachtshift_last_export_error' => null,
            'yachtshift_sync_summary' => $summary,
        ])->save();

        $after = $yacht->fresh()->only(array_keys($before));
        $this->recordFieldChanges($yacht, $before, $after, 'export', 'schepenkring', 'yachtshift', 'single_boat', $actor);

        $log = $this->logSyncAudit('yachtshift.export.boat_synced', $yacht, 'SUCCESS', [
            'direction' => 'export',
            'source' => 'schepenkring',
            'target' => 'yachtshift',
            'sync_type' => 'single_boat',
            'external_id' => $externalId,
            'price_changed' => false,
        ], $before, $after, $actor);

        return [
            'success' => true,
            'external_id' => $externalId,
            'payload' => $payload,
            'summary' => $summary,
            'audit_log_ids' => [$log->id],
            'dry_run' => false,
        ];
    }

    public function resolveConflict(int $conflictId, string $resolution, ?User $actor = null, mixed $customValue = null): array
    {
        $conflict = YachtshiftSyncConflict::with('yacht')->findOrFail($conflictId);

        if ($conflict->status !== 'pending') {
            throw new \RuntimeException('Conflict is already resolved.');
        }

        if (! in_array($resolution, ['local', 'remote', 'custom'], true)) {
            throw new \InvalidArgumentException('Resolution must be local, remote, or custom.');
        }

        $yacht = $conflict->yacht;
        $before = $yacht->only([$conflict->field_name, 'yachtshift_sync_summary']);

        if ($resolution !== 'local') {
            $value = $resolution === 'custom' ? $customValue : $conflict->remote_value;
            $this->assertYachtColumnCanBeResolved($conflict->field_name);
            $yacht->forceFill([
                $conflict->field_name => $value,
                'yachtshift_sync_summary' => "Resolved YachtShift conflict for {$this->boatLabel($yacht)} using {$resolution} value.",
            ])->save();
        } else {
            $yacht->forceFill([
                'yachtshift_sync_summary' => "Resolved YachtShift conflict for {$this->boatLabel($yacht)} using local value.",
            ])->save();
        }

        $conflict->update([
            'status' => 'resolved',
            'resolution' => $resolution,
            'resolved_by_id' => $actor?->id,
            'resolved_at' => now(),
        ]);

        $after = $yacht->fresh()->only([$conflict->field_name, 'yachtshift_sync_summary']);
        $this->recordFieldChanges($yacht, $before, $after, 'import', 'yachtshift', 'schepenkring', 'conflict_resolution', $actor);

        $log = $this->logSyncAudit('yachtshift.conflict.resolved', $yacht, 'SUCCESS', [
            'direction' => 'import',
            'source' => 'yachtshift',
            'target' => 'schepenkring',
            'sync_type' => 'conflict_resolution',
            'conflict_id' => $conflict->id,
            'field_name' => $conflict->field_name,
            'resolution' => $resolution,
        ], $before, $after, $actor);

        return [
            'conflict' => $conflict->fresh(),
            'yacht' => $yacht->fresh(),
            'audit_log_id' => $log->id,
        ];
    }

    private function mapImportFields(array $listing, string $externalId): array
    {
        return [
            'vessel_id' => $this->firstString($listing, ['vessel_id', 'reference', 'external_id']),
            'boat_name' => $this->firstString($listing, ['boat_name', 'name', 'title']),
            'manufacturer' => $this->firstString($listing, ['manufacturer', 'brand', 'make']),
            'model' => $this->firstString($listing, ['model']),
            'year' => $this->firstInt($listing, ['year']),
            'price' => $this->firstFloat($listing, ['price', 'asking_price']),
            'status' => $this->firstString($listing, ['status']),
            'short_description_nl' => $this->firstString($listing, ['description', 'short_description_nl']),
            'external_url' => $this->firstString($listing, ['url', 'external_url']),
        ];
    }

    private function mapExportFields(Yacht $yacht): array
    {
        $payload = [
            'external_id' => (string) $yacht->id,
        ];

        foreach ($this->filterExportColumns(array_keys(self::EXPORT_FIELD_MAP)) as $column) {
            $value = $yacht->getAttribute($column);
            if ($value === null || $value === '') {
                continue;
            }

            $payload[self::EXPORT_FIELD_MAP[$column]] = $value;
        }

        return $payload;
    }

    private function filterImportFields(array $mapped): array
    {
        $allowedColumns = $this->filterImportColumns(array_keys($mapped));

        return collect($mapped)
            ->only($allowedColumns)
            ->filter(fn ($value) => $value !== null && $value !== '')
            ->all();
    }

    /**
     * @param array<int, string> $columns
     * @return array<int, string>
     */
    private function filterImportColumns(array $columns): array
    {
        $rules = $this->boatFieldRules($columns);

        return array_values(array_filter($columns, function (string $column) use ($rules): bool {
            return ! $rules->has($column) || (bool) $rules[$column]->can_import;
        }));
    }

    /**
     * @param array<int, string> $columns
     * @return array<int, string>
     */
    private function filterExportColumns(array $columns): array
    {
        $rules = $this->boatFieldRules($columns);

        return array_values(array_filter($columns, function (string $column) use ($rules): bool {
            if (! $rules->has($column)) {
                return true;
            }

            $rule = $rules[$column];

            return (bool) $rule->can_export && ! (bool) $rule->never_export;
        }));
    }

    private function boatFieldRules(array $columns)
    {
        if (count($columns) === 0 || ! Schema::hasColumn('boat_fields', 'can_import')) {
            return collect();
        }

        return BoatField::query()
            ->whereNull('storage_relation')
            ->whereIn('storage_column', $columns)
            ->get()
            ->keyBy('storage_column');
    }

    private function detectConflicts(Yacht $yacht, array $mapped, array $listing, string $externalId, bool $dryRun, ?User $actor): array
    {
        $remoteUpdatedAt = $this->parseDate($listing['updated_at'] ?? null);
        $localNewer = $remoteUpdatedAt && $yacht->updated_at && $yacht->updated_at->greaterThan($remoteUpdatedAt);

        if (! $localNewer) {
            return [];
        }

        $conflicts = [];

        foreach ($mapped as $field => $remoteValue) {
            $localValue = $yacht->getAttribute($field);

            if ($this->valuesMatch($localValue, $remoteValue) || $this->isBlank($localValue) || $this->isBlank($remoteValue)) {
                continue;
            }

            $detail = [
                'yacht_id' => $yacht->id,
                'external_id' => $externalId,
                'field_name' => $field,
                'local_value' => $this->stringValue($localValue),
                'remote_value' => $this->stringValue($remoteValue),
                'reason' => 'local_newer',
            ];

            if (! $dryRun) {
                YachtshiftSyncConflict::updateOrCreate(
                    [
                        'yacht_id' => $yacht->id,
                        'external_id' => $externalId,
                        'field_name' => $field,
                        'status' => 'pending',
                    ],
                    [
                        'local_value' => $detail['local_value'],
                        'remote_value' => $detail['remote_value'],
                        'metadata' => [
                            'remote_updated_at' => $remoteUpdatedAt?->toIso8601String(),
                            'local_updated_at' => $yacht->updated_at?->toIso8601String(),
                        ],
                    ]
                );
            }

            $conflicts[] = $detail;
        }

        if (count($conflicts) > 0 && ! $dryRun) {
            $this->logSyncAudit('yachtshift.import.conflict', $yacht, 'FAIL', [
                'direction' => 'import',
                'source' => 'yachtshift',
                'target' => 'schepenkring',
                'sync_type' => 'conflict_detection',
                'external_id' => $externalId,
                'conflicts' => $conflicts,
            ], null, null, $actor);
        }

        return $conflicts;
    }

    private function recordFieldChanges(Yacht $yacht, array $before, array $after, string $direction, string $source, string $target, string $syncType, ?User $actor): void
    {
        foreach (array_unique(array_merge(array_keys($before), array_keys($after))) as $field) {
            $old = $before[$field] ?? null;
            $new = $after[$field] ?? null;

            if ($this->valuesMatch($old, $new)) {
                continue;
            }

            BoatFieldChange::create([
                'yacht_id' => $yacht->id,
                'field_name' => $field,
                'old_value' => $this->stringValue($old),
                'new_value' => $this->stringValue($new),
                'changed_by_type' => $direction === 'import' ? 'import' : 'system',
                'changed_by_id' => $actor?->id,
                'source_type' => 'yachtshift',
                'reason' => 'yachtshift_' . $direction,
                'correction_label' => $field === 'price' ? 'price_change' : null,
                'meta' => [
                    'direction' => $direction,
                    'source' => $source,
                    'target' => $target,
                    'sync_type' => $syncType,
                    'price_changed' => $field === 'price',
                ],
            ]);
        }
    }

    private function logSyncAudit(string $action, ?Yacht $yacht, string $result, array $meta, ?array $before = null, ?array $after = null, ?User $actor = null): AuditLog
    {
        return AuditLog::create([
            'action' => $action,
            'risk_level' => $result === 'FAIL' ? 'MEDIUM' : 'LOW',
            'result' => $result,
            'actor_id' => $actor?->id,
            'location_id' => $yacht?->location_id,
            'target_type' => $yacht ? Yacht::class : 'yachtshift_sync',
            'target_id' => $yacht?->id,
            'entity_type' => $yacht ? Yacht::class : 'yachtshift_sync',
            'entity_id' => $yacht?->id,
            'meta' => $meta,
            'snapshot_before' => $before,
            'snapshot_after' => $after,
        ]);
    }

    private function extractListings(mixed $json): array
    {
        if (is_array($json) && array_is_list($json)) {
            return $json;
        }

        if (is_array($json) && isset($json['data']) && is_array($json['data'])) {
            return $json['data'];
        }

        if (is_array($json) && isset($json['listings']) && is_array($json['listings'])) {
            return $json['listings'];
        }

        return [];
    }

    private function externalIdFromListing(array $listing): ?string
    {
        return $this->firstString($listing, ['id', 'yachtshift_id', 'external_id']);
    }

    private function externalIdFromResponse(mixed $json): ?string
    {
        if (! is_array($json)) {
            return null;
        }

        return $this->firstString($json, ['id', 'yachtshift_id'])
            ?? (isset($json['data']) && is_array($json['data']) ? $this->firstString($json['data'], ['id', 'yachtshift_id']) : null);
    }

    private function responseError(int $status, mixed $json, string $body): string
    {
        $message = is_array($json) ? ($json['message'] ?? $json['error'] ?? null) : null;

        return trim((string) ($message ?: "HTTP {$status}: " . substr($body, 0, 300)));
    }

    private function buildSyncSummary(string $direction, array $counts): string
    {
        if ($direction === 'import') {
            return sprintf(
                'YachtShift import %s: %d imported, %d updated, %d skipped, %d conflicts.',
                $counts['dry_run'] ? 'dry run' : 'completed',
                $counts['imported'],
                $counts['updated'],
                $counts['skipped'],
                $counts['conflicts']
            );
        }

        return sprintf(
            'YachtShift export %s: %d exported, %d failed.',
            $counts['dry_run'] ? 'dry run' : 'completed',
            $counts['exported'],
            $counts['failed']
        );
    }

    private function buildBoatSummary(string $direction, array $payload, ?Yacht $yacht, bool $dryRun): string
    {
        $label = $yacht ? $this->boatLabel($yacht) : ($payload['boat_name'] ?? $payload['name'] ?? 'new boat');
        $prefix = $dryRun ? 'Dry run' : ucfirst($direction);

        if ($direction === 'import') {
            return "{$prefix}: {$label} will sync from YachtShift with " . count($payload) . ' mapped fields.';
        }

        return "{$prefix}: {$label} will publish to YachtShift with " . count($payload) . ' mapped fields.';
    }

    private function assertYachtColumnCanBeResolved(string $field): void
    {
        if (! Schema::hasColumn('yachts', $field)) {
            throw new \InvalidArgumentException("Cannot resolve unsupported yacht field: {$field}");
        }
    }

    private function firstString(array $source, array $keys): ?string
    {
        foreach ($keys as $key) {
            if (! array_key_exists($key, $source) || ! is_scalar($source[$key])) {
                continue;
            }

            $value = trim((string) $source[$key]);
            if ($value !== '') {
                return $value;
            }
        }

        return null;
    }

    private function firstInt(array $source, array $keys): ?int
    {
        $value = $this->firstString($source, $keys);

        return is_numeric($value) ? (int) $value : null;
    }

    private function firstFloat(array $source, array $keys): ?float
    {
        $value = $this->firstString($source, $keys);
        if ($value === null) {
            return null;
        }

        $normalized = str_replace(',', '.', preg_replace('/[^0-9,.\-]/', '', $value) ?? '');

        return is_numeric($normalized) ? (float) $normalized : null;
    }

    private function resolveImportLocationId(array $listing, ?User $actor): ?int
    {
        $code = $this->firstString($listing, ['location_code', 'harbor_code']);
        if ($code) {
            $locationId = Location::query()->where('code', $code)->value('id');
            if ($locationId) {
                return (int) $locationId;
            }
        }

        $id = $this->firstInt($listing, ['location_id', 'harbor_id']);
        if ($id && Location::query()->whereKey($id)->exists()) {
            return $id;
        }

        if ($actor?->client_location_id) {
            return (int) $actor->client_location_id;
        }

        return (int) (Location::query()->where('code', 'HQ')->value('id')
            ?? Location::query()->orderBy('id')->value('id')) ?: null;
    }

    private function parseDate(mixed $value): ?Carbon
    {
        if (! is_scalar($value) || trim((string) $value) === '') {
            return null;
        }

        try {
            return Carbon::parse((string) $value);
        } catch (\Throwable) {
            return null;
        }
    }

    private function valuesMatch(mixed $left, mixed $right): bool
    {
        if (is_numeric($left) && is_numeric($right)) {
            return abs((float) $left - (float) $right) < 0.0001;
        }

        return $this->stringValue($left) === $this->stringValue($right);
    }

    private function fieldChanged(string $field, array $before, array $after): bool
    {
        return ! $this->valuesMatch($before[$field] ?? null, $after[$field] ?? null);
    }

    private function isBlank(mixed $value): bool
    {
        return $value === null || trim($this->stringValue($value)) === '';
    }

    private function stringValue(mixed $value): string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format(DATE_ATOM);
        }

        if (is_array($value) || is_object($value)) {
            return json_encode($value, JSON_UNESCAPED_SLASHES) ?: '';
        }

        return trim((string) $value);
    }

    private function boatLabel(Yacht $yacht): string
    {
        return $yacht->boat_name ?: ($yacht->manufacturer || $yacht->model ? trim("{$yacht->manufacturer} {$yacht->model}") : "boat #{$yacht->id}");
    }
}
