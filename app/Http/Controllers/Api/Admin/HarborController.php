<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\RiskLevel;
use App\Http\Controllers\Controller;
use App\Mail\LocationDeleteRequestMail;
use App\Models\AuditLog;
use App\Models\Boat;
use App\Models\Conversation;
use App\Models\Lead;
use App\Models\Location;
use App\Models\LocationDeleteRequest;
use App\Models\Task;
use App\Models\User;
use App\Models\Yacht;
use App\Services\ActionSecurity;
use App\Services\Ga4DataApiService;
use App\Services\KnowledgeGraphService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class HarborController extends Controller
{
    public function __construct(
        private Ga4DataApiService $ga4,
        private ActionSecurity $security,
        private KnowledgeGraphService $knowledgeGraph
    )
    {
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorizeAdmin($request);

        $harbors = $this->baseQuery($request)->get();
        $counts = $this->buildSnapshotCounts($harbors->pluck('id'));

        return response()->json([
            'data' => $harbors->map(fn (Location $harbor) => $this->serializeHarbor($harbor, $counts)),
        ]);
    }

    public function show(Request $request, Location $harbor): JsonResponse
    {
        $this->authorizeAdmin($request);
        $harbor->load([
            'employees' => fn ($query) => $query
                ->select('users.id', 'users.name', 'users.email', 'users.type')
                ->orderBy('users.name'),
        ]);

        $counts = $this->buildSnapshotCounts(collect([$harbor->id]));

        return response()->json([
            'data' => $this->serializeHarbor($harbor, $counts),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorizeAdmin($request);

        $validated = $request->validate(array_merge(
            $this->coreLocationRules(null),
            $this->addressRules()
        ));

        $harbor = Location::create($this->buildLocationPayload($validated));

        $this->syncLocationKnowledgeSafely($harbor);

        AuditLog::create([
            'action'      => 'location.created',
            'risk_level'  => 'medium',
            'result'      => 'success',
            'actor_id'    => $request->user()?->id,
            'entity_type' => 'location',
            'entity_id'   => $harbor->id,
            'meta'        => ['name' => $harbor->name, 'code' => $harbor->code],
            'ip_address'  => $request->ip(),
        ]);

        return response()->json([
            'data' => $this->serializeHarbor($harbor->fresh(), $this->buildSnapshotCounts(collect([$harbor->id]))),
        ], 201);
    }

    public function update(Request $request, Location $harbor): JsonResponse
    {
        $this->authorizeAdmin($request);

        $before = $harbor->toArray();

        $validated = $request->validate(array_merge(
            $this->coreLocationRules($harbor->id),
            $this->addressRules(true)
        ));

        $harbor->update($this->buildLocationPayload($validated));
        $this->syncLocationKnowledgeSafely($harbor);

        AuditLog::create([
            'action'          => 'location.updated',
            'risk_level'      => 'low',
            'result'          => 'success',
            'actor_id'        => $request->user()?->id,
            'entity_type'     => 'location',
            'entity_id'       => $harbor->id,
            'meta'            => ['name' => $harbor->name],
            'snapshot_before' => $before,
            'snapshot_after'  => $harbor->fresh()->toArray(),
            'ip_address'      => $request->ip(),
        ]);

        return response()->json([
            'data' => $this->serializeHarbor($harbor->fresh(), $this->buildSnapshotCounts(collect([$harbor->id]))),
        ]);
    }

    /**
     * GET /api/admin/locations/{harbor}/users
     */
    public function locationUsers(Request $request, Location $harbor): JsonResponse
    {
        $this->authorizeAdmin($request);

        $users = $harbor->users()
            ->select('users.id', 'users.name', 'users.email', 'users.type', 'users.status', 'users.phone', 'users.last_login_at')
            ->orderBy('users.name')
            ->get()
            ->map(fn (User $user) => [
                'id'             => $user->id,
                'name'           => $user->name,
                'email'          => $user->email,
                'type'           => $user->type?->value ?? $user->type,
                'status'         => $user->status?->value ?? $user->status,
                'phone'          => $user->phone,
                'location_role'  => $user->pivot?->role,
                'last_login_at'  => $user->last_login_at,
            ]);

        // Also include clients assigned to this location
        $clients = User::query()
            ->where('client_location_id', $harbor->id)
            ->select('id', 'name', 'email', 'type', 'status', 'phone', 'last_login_at')
            ->orderBy('name')
            ->get()
            ->map(fn (User $user) => [
                'id'             => $user->id,
                'name'           => $user->name,
                'email'          => $user->email,
                'type'           => $user->type?->value ?? $user->type,
                'status'         => $user->status?->value ?? $user->status,
                'phone'          => $user->phone,
                'location_role'  => 'client',
                'last_login_at'  => $user->last_login_at,
            ]);

        return response()->json([
            'data'    => $users->concat($clients)->values(),
            'total'   => $users->count() + $clients->count(),
        ]);
    }

    /**
     * POST /api/admin/locations/{harbor}/users
     * Add an existing user to this location.
     */
    public function addLocationUser(Request $request, Location $harbor): JsonResponse
    {
        $this->authorizeAdmin($request);

        $validated = $request->validate([
            'user_id'  => 'required|integer|exists:users,id',
            'role'     => 'nullable|string',
        ]);

        $user = User::findOrFail($validated['user_id']);
        $role = $validated['role'] ?? \App\Enums\LocationRole::LOCATION_EMPLOYEE->value;

        // For CLIENT types use client_location_id instead
        if ($user->isClient()) {
            $user->update(['client_location_id' => $harbor->id]);
        } else {
            $existingLocations = $user->locations->map(fn ($l) => [
                'location_id' => $l->id,
                'role'        => $l->pivot?->role ?? \App\Enums\LocationRole::LOCATION_EMPLOYEE->value,
            ])->toArray();

            $alreadyLinked = collect($existingLocations)->firstWhere('location_id', $harbor->id);
            if (! $alreadyLinked) {
                $existingLocations[] = ['location_id' => $harbor->id, 'role' => $role];
                app(\App\Repositories\UserRepository::class)->syncLocations($user, $existingLocations);
            }
        }

        AuditLog::create([
            'action'      => 'location.user_added',
            'risk_level'  => 'medium',
            'result'      => 'success',
            'actor_id'    => $request->user()?->id,
            'entity_type' => 'location',
            'entity_id'   => $harbor->id,
            'meta'        => [
                'user_id'   => $user->id,
                'user_name' => $user->name,
                'role'      => $role,
            ],
            'ip_address'  => $request->ip(),
        ]);

        return response()->json(['success' => true, 'message' => "User {$user->name} added to {$harbor->name}."]);
    }

    /**
     * DELETE /api/admin/locations/{harbor}/users/{userId}
     * Remove a user from this location.
     */
    public function removeLocationUser(Request $request, Location $harbor, int $userId): JsonResponse
    {
        $this->authorizeAdmin($request);

        $user = User::findOrFail($userId);

        if ($user->isClient()) {
            $user->update(['client_location_id' => null]);
        } else {
            $remainingLocations = $user->locations
                ->filter(fn ($l) => $l->id !== $harbor->id)
                ->map(fn ($l) => [
                    'location_id' => $l->id,
                    'role'        => $l->pivot?->role ?? \App\Enums\LocationRole::LOCATION_EMPLOYEE->value,
                ])->values()->toArray();

            app(\App\Repositories\UserRepository::class)->syncLocations($user, $remainingLocations);
        }

        AuditLog::create([
            'action'      => 'location.user_removed',
            'risk_level'  => 'medium',
            'result'      => 'success',
            'actor_id'    => $request->user()?->id,
            'entity_type' => 'location',
            'entity_id'   => $harbor->id,
            'meta'        => ['user_id' => $user->id, 'user_name' => $user->name],
            'ip_address'  => $request->ip(),
        ]);

        return response()->json(['success' => true, 'message' => "User {$user->name} removed from {$harbor->name}."]);
    }

    /**
     * GET /api/admin/locations/{harbor}/impact
     * Returns linked-record counts for the impact analysis panel.
     */
    public function impact(Request $request, Location $harbor): JsonResponse
    {
        $this->authorizeAdmin($request);

        $counts    = $this->buildBlockingUsageCounts($harbor->id);
        $total     = array_sum($counts);
        $canForce  = $total === 0;

        return response()->json([
            'location_id'          => $harbor->id,
            'location_name'        => $harbor->name,
            'impact'               => $counts,
            'total_linked_records' => $total,
            'safe_to_delete'       => $canForce,
        ]);
    }

    /**
     * POST /api/admin/locations/{harbor}/request-delete
     * Creates a deletion request and e-mails all platform admins for approval.
     */
    public function requestDeletion(Request $request, Location $harbor): JsonResponse
    {
        $this->authorizeAdmin($request);

        $validated = $request->validate([
            'reason'               => 'required|string|max:1000',
            'move_to_location_id'  => 'nullable|integer|exists:locations,id',
        ]);

        // Cancel any previous pending request for this location
        LocationDeleteRequest::where('location_id', $harbor->id)
            ->where('status', 'pending')
            ->update(['status' => 'cancelled']);

        $impact = $this->buildBlockingUsageCounts($harbor->id);
        $token  = Str::random(64);

        $deleteRequest = LocationDeleteRequest::create([
            'location_id'          => $harbor->id,
            'requested_by'         => $request->user()->id,
            'move_to_location_id'  => $validated['move_to_location_id'] ?? null,
            'reason'               => $validated['reason'],
            'token'                => $token,
            'status'               => 'pending',
            'affected_records'     => $impact,
            'ip_address'           => $request->ip(),
            'user_agent'           => substr($request->userAgent() ?? '', 0, 500),
            'expires_at'           => now()->addHours(24),
        ]);

        $approveUrl = url("/locations/delete/approve/{$token}");
        $cancelUrl  = url("/locations/delete/cancel/{$token}");

        // Send email to all ADMIN users
        $admins = User::where('type', \App\Enums\UserType::ADMIN->value)->get();
        foreach ($admins as $admin) {
            try {
                Mail::to($admin->email)->queue(
                    new LocationDeleteRequestMail(
                        $harbor,
                        $deleteRequest,
                        $request->user(),
                        $approveUrl,
                        $cancelUrl
                    )
                );
            } catch (\Throwable $e) {
                Log::warning('[LocationDelete] Email failed for admin ' . $admin->id, ['error' => $e->getMessage()]);
            }
        }

        // Audit log
        AuditLog::create([
            'action'      => 'location.delete_requested',
            'risk_level'  => 'high',
            'result'      => 'success',
            'actor_id'    => $request->user()->id,
            'entity_type' => 'location',
            'entity_id'   => $harbor->id,
            'meta'        => [
                'reason'              => $validated['reason'],
                'affected_records'    => $impact,
                'request_id'          => $deleteRequest->id,
                'move_to_location_id' => $validated['move_to_location_id'] ?? null,
            ],
            'ip_address'  => $request->ip(),
            'user_agent'  => substr($request->userAgent() ?? '', 0, 500),
        ]);

        return response()->json([
            'success'    => true,
            'message'    => 'Verwijderingsverzoek ingediend. Admins ontvangen een e-mail ter goedkeuring.',
            'request_id' => $deleteRequest->id,
            'expires_at' => $deleteRequest->expires_at,
        ]);
    }

    /**
     * GET /api/admin/locations/archived
     * Returns soft-deleted locations.
     */
    public function archived(Request $request): JsonResponse
    {
        $this->authorizeAdmin($request);

        $archived = Location::onlyTrashed()
            ->with('deletedByUser')
            ->orderByDesc('deleted_at')
            ->get()
            ->map(fn (Location $loc) => [
                'id'            => $loc->id,
                'name'          => $loc->name,
                'code'          => $loc->code,
                'status'        => $loc->status,
                'deleted_at'    => $loc->deleted_at,
                'deleted_by'    => $loc->deletedByUser?->name,
                'delete_reason' => $loc->delete_reason,
                'impact'        => $this->buildBlockingUsageCounts($loc->id),
            ]);

        return response()->json(['data' => $archived]);
    }

    /**
     * POST /api/admin/locations/{id}/restore
     * Restores an archived location.
     */
    public function restore(Request $request, int $id): JsonResponse
    {
        $this->authorizeAdmin($request);

        $harbor = Location::onlyTrashed()->findOrFail($id);
        $harbor->restore();
        $harbor->update(['deleted_by' => null, 'delete_reason' => null]);

        $this->syncLocationKnowledgeSafely($harbor->fresh());

        AuditLog::create([
            'action'      => 'location.restored',
            'risk_level'  => 'medium',
            'result'      => 'success',
            'actor_id'    => $request->user()?->id,
            'entity_type' => 'location',
            'entity_id'   => $harbor->id,
            'meta'        => ['location_name' => $harbor->name],
            'ip_address'  => $request->ip(),
        ]);

        return response()->json([
            'success' => true,
            'message' => "Locatie {$harbor->name} hersteld.",
        ]);
    }

    /**
     * DELETE /api/admin/locations/{id}/permanent
     * Permanently deletes an archived location — only if no linked records remain.
     */
    public function permanentDelete(Request $request, int $id): JsonResponse
    {
        $this->authorizeAdmin($request);

        $harbor = Location::onlyTrashed()->findOrFail($id);
        $impact = $this->buildBlockingUsageCounts($harbor->id);
        $total  = array_sum($impact);

        if ($total > 0 && ! $request->boolean('force')) {
            return response()->json([
                'success' => false,
                'message' => 'Kan niet permanent verwijderen: locatie heeft nog gekoppelde records.',
                'impact'  => $impact,
            ], 422);
        }

        $name = $harbor->name;
        $this->removeLocationKnowledgeSafely($harbor);
        $harbor->forceDelete();

        AuditLog::create([
            'action'      => 'location.permanently_deleted',
            'risk_level'  => 'critical',
            'result'      => 'success',
            'actor_id'    => $request->user()?->id,
            'entity_type' => 'location',
            'entity_id'   => $id,
            'meta'        => ['location_name' => $name, 'impact' => $impact],
            'ip_address'  => $request->ip(),
        ]);

        return response()->json([
            'success' => true,
            'message' => "Locatie {$name} permanent verwijderd.",
        ]);
    }

    public function performance(Request $request): JsonResponse
    {
        $this->authorizeAdmin($request);

        [$start, $end, $range] = $this->resolvePeriod($request);

        $harbors = $this->baseQuery($request)->get();
        $harborIds = $harbors->pluck('id');

        $snapshotCounts = $this->buildSnapshotCounts($harborIds);
        $periodCounts = $this->buildPeriodCounts($harborIds, $start, $end);
        $traffic = $this->ga4->fetchTrafficMetrics(
            $start->toDateString(),
            $end->toDateString(),
            array_filter($request->only(['device', 'country', 'source', 'medium', 'campaign']))
        );

        $rows = $harbors->map(function (Location $harbor) use ($snapshotCounts, $periodCounts, $traffic) {
            $id = $harbor->id;
            $trafficMetrics = $traffic[$id] ?? ['active_users' => 0, 'sessions' => 0];

            return [
                'id' => $id,
                'name' => $harbor->name,
                'code' => $harbor->code,
                'status' => $harbor->status,
                'metrics' => [
                    'clients_total' => $snapshotCounts['clients'][$id] ?? 0,
                    'staff_total' => $snapshotCounts['staff'][$id] ?? 0,
                    'boats_total' => $snapshotCounts['boats'][$id] ?? 0,
                    'yachts_total' => $snapshotCounts['yachts'][$id] ?? 0,
                    'open_leads' => $snapshotCounts['open_leads'][$id] ?? 0,
                    'open_conversations' => $snapshotCounts['open_conversations'][$id] ?? 0,
                    'open_tasks' => $snapshotCounts['open_tasks'][$id] ?? 0,
                    'leads_created' => $periodCounts['leads_created'][$id] ?? 0,
                    'conversations_created' => $periodCounts['conversations_created'][$id] ?? 0,
                    'tasks_created' => $periodCounts['tasks_created'][$id] ?? 0,
                    'tasks_completed' => $periodCounts['tasks_completed'][$id] ?? 0,
                    'active_users' => (int) ($trafficMetrics['active_users'] ?? 0),
                    'sessions' => (int) ($trafficMetrics['sessions'] ?? 0),
                ],
            ];
        })->values();

        return response()->json([
            'range' => $range,
            'from' => $start->toDateString(),
            'to' => $end->toDateString(),
            'data' => $rows,
        ]);
    }

    private function authorizeAdmin(Request $request): void
    {
        $user = $request->user();

        if (! $user) {
            abort(401, 'Unauthorized');
        }

        if (! $user->isAdmin()) {
            abort(403, 'Forbidden');
        }
    }

    private function baseQuery(Request $request)
    {
        $query = Location::query()->with([
            'employees' => fn ($query) => $query
                ->select('users.id', 'users.name', 'users.email', 'users.type')
                ->orderBy('users.name'),
        ]);

        $includeInactive = filter_var($request->query('include_inactive', false), FILTER_VALIDATE_BOOL);
        if (! $includeInactive) {
            $query->where('status', 'ACTIVE');
        }

        if ($request->filled('search')) {
            $search = '%' . trim((string) $request->query('search')) . '%';
            $query->where(function ($builder) use ($search) {
                $builder->where('name', 'like', $search)
                    ->orWhere('code', 'like', $search);
            });
        }

        if ($request->filled('id')) {
            $query->where('id', (int) $request->query('id'));
        }

        if ($request->filled('code')) {
            $query->where('code', (string) $request->query('code'));
        }

        if ($request->filled('status')) {
            $query->where('status', strtoupper((string) $request->query('status')));
        }

        return $query->orderBy('name');
    }

    private function normalizePayload(array $payload): array
    {
        $normalized = $payload;

        if (array_key_exists('name', $normalized)) {
            $normalized['name'] = trim((string) $normalized['name']);
        }

        if (array_key_exists('code', $normalized)) {
            $normalized['code'] = Str::upper(trim((string) $normalized['code']));
        }

        if (array_key_exists('status', $normalized)) {
            $normalized['status'] = Str::upper(trim((string) $normalized['status']));
        } elseif (! array_key_exists('status', $payload)) {
            $normalized['status'] = 'ACTIVE';
        }

        return $normalized;
    }

    private function resolvePeriod(Request $request): array
    {
        $end = $request->filled('to')
            ? CarbonImmutable::parse((string) $request->query('to'))->endOfDay()
            : CarbonImmutable::now()->endOfDay();

        if ($request->filled('from')) {
            $start = CarbonImmutable::parse((string) $request->query('from'))->startOfDay();

            return [$start, $end, 'custom'];
        }

        $range = strtolower(trim((string) $request->query('range', '30d')));
        if (preg_match('/^(\d+)([dwmy])$/', $range, $matches)) {
            $amount = max(1, (int) $matches[1]);
            $unit = $matches[2];

            $start = match ($unit) {
                'w' => $end->subWeeks($amount)->addDay()->startOfDay(),
                'm' => $end->subMonths($amount)->addDay()->startOfDay(),
                'y' => $end->subYears($amount)->addDay()->startOfDay(),
                default => $end->subDays($amount - 1)->startOfDay(),
            };

            return [$start, $end, $range];
        }

        return [$end->subDays(29)->startOfDay(), $end, '30d'];
    }

    private function syncLocationKnowledgeSafely(Location $location): void
    {
        try {
            $this->knowledgeGraph->syncLocation($location->fresh());
        } catch (\Throwable $e) {
            Log::warning('Location knowledge sync failed after harbor update', [
                'location_id' => $location->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function removeLocationKnowledgeSafely(Location $location): void
    {
        try {
            $this->knowledgeGraph->removeLocation($location);
        } catch (\Throwable $e) {
            Log::warning('Location knowledge cleanup failed after harbor delete', [
                'location_id' => $location->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function buildSnapshotCounts(Collection $harborIds): array
    {
        if ($harborIds->isEmpty()) {
            return [
                'clients' => collect(),
                'staff' => collect(),
                'boats' => collect(),
                'yachts' => collect(),
                'open_leads' => collect(),
                'open_conversations' => collect(),
                'open_tasks' => collect(),
            ];
        }

        $ids = $harborIds->all();

        return [
            'clients' => User::query()
                ->selectRaw('client_location_id as location_id, count(*) as aggregate')
                ->whereIn('client_location_id', $ids)
                ->groupBy('client_location_id')
                ->pluck('aggregate', 'location_id'),
            'staff' => DB::table('location_user')
                ->join('users', 'users.id', '=', 'location_user.user_id')
                ->selectRaw('location_user.location_id as location_id, count(distinct users.id) as aggregate')
                ->whereIn('location_user.location_id', $ids)
                ->whereIn('users.type', ['ADMIN', 'EMPLOYEE'])
                ->groupBy('location_user.location_id')
                ->pluck('aggregate', 'location_id'),
            'boats' => Boat::query()
                ->selectRaw('location_id, count(*) as aggregate')
                ->whereIn('location_id', $ids)
                ->groupBy('location_id')
                ->pluck('aggregate', 'location_id'),
            'yachts' => Yacht::query()
                ->selectRaw('ref_harbor_id as location_id, count(*) as aggregate')
                ->whereNotNull('ref_harbor_id')
                ->whereIn('ref_harbor_id', $ids)
                ->groupBy('ref_harbor_id')
                ->pluck('aggregate', 'location_id'),
            'open_leads' => Lead::query()
                ->selectRaw('location_id, count(*) as aggregate')
                ->whereIn('location_id', $ids)
                ->whereNotIn('status', ['converted', 'closed'])
                ->groupBy('location_id')
                ->pluck('aggregate', 'location_id'),
            'open_conversations' => Conversation::query()
                ->selectRaw('location_id, count(*) as aggregate')
                ->whereIn('location_id', $ids)
                ->where('status', 'open')
                ->groupBy('location_id')
                ->pluck('aggregate', 'location_id'),
            'open_tasks' => Task::query()
                ->selectRaw("location_id, sum(case when lower(status) not in ('done', 'completed', 'closed') then 1 else 0 end) as aggregate")
                ->whereIn('location_id', $ids)
                ->groupBy('location_id')
                ->pluck('aggregate', 'location_id'),
        ];
    }

    private function buildPeriodCounts(Collection $harborIds, CarbonImmutable $start, CarbonImmutable $end): array
    {
        if ($harborIds->isEmpty()) {
            return [
                'leads_created' => collect(),
                'conversations_created' => collect(),
                'tasks_created' => collect(),
                'tasks_completed' => collect(),
            ];
        }

        $ids = $harborIds->all();
        $window = [$start->toDateTimeString(), $end->toDateTimeString()];

        return [
            'leads_created' => Lead::query()
                ->selectRaw('location_id, count(*) as aggregate')
                ->whereIn('location_id', $ids)
                ->whereBetween('created_at', $window)
                ->groupBy('location_id')
                ->pluck('aggregate', 'location_id'),
            'conversations_created' => Conversation::query()
                ->selectRaw('location_id, count(*) as aggregate')
                ->whereIn('location_id', $ids)
                ->whereBetween('created_at', $window)
                ->groupBy('location_id')
                ->pluck('aggregate', 'location_id'),
            'tasks_created' => Task::query()
                ->selectRaw('location_id, count(*) as aggregate')
                ->whereIn('location_id', $ids)
                ->whereBetween('created_at', $window)
                ->groupBy('location_id')
                ->pluck('aggregate', 'location_id'),
            'tasks_completed' => Task::query()
                ->selectRaw("location_id, sum(case when lower(status) in ('done', 'completed', 'closed') then 1 else 0 end) as aggregate")
                ->whereIn('location_id', $ids)
                ->whereBetween('updated_at', $window)
                ->groupBy('location_id')
                ->pluck('aggregate', 'location_id'),
        ];
    }

    private function serializeHarbor(Location $harbor, array $counts): array
    {
        $id = $harbor->id;
        $employees = $harbor->relationLoaded('employees')
            ? $harbor->employees->values()
            : collect();

        return [
            'id'                   => $id,
            'name'                 => $harbor->name,
            'code'                 => $harbor->code,
            'status'               => $harbor->status,
            // Address
            'address_line1'        => $harbor->address_line1,
            'street_number'        => $harbor->street_number,
            'postal_code'          => $harbor->postal_code,
            'city'                 => $harbor->city,
            'country'              => $harbor->country,
            'phone'                => $harbor->phone,
            'email'                => $harbor->email,
            'website'              => $harbor->website,
            'latitude'             => $harbor->latitude,
            'longitude'            => $harbor->longitude,
            // Counts
            'clients_total'        => $counts['clients'][$id] ?? 0,
            'staff_total'          => $counts['staff'][$id] ?? 0,
            'employee_count'       => $employees->count(),
            'employees'            => $employees->map(fn (User $employee) => [
                'id'            => $employee->id,
                'name'          => $employee->name,
                'email'         => $employee->email,
                'role'          => $employee->role,
                'location_role' => $employee->pivot?->role,
            ])->values(),
            'boats_total'          => $counts['boats'][$id] ?? 0,
            'yachts_total'         => $counts['yachts'][$id] ?? 0,
            'open_leads'           => $counts['open_leads'][$id] ?? 0,
            'open_conversations'   => $counts['open_conversations'][$id] ?? 0,
            'open_tasks'           => $counts['open_tasks'][$id] ?? 0,
            'created_at'           => $harbor->created_at,
            'updated_at'           => $harbor->updated_at,
        ];
    }

    // ── Shared validation rules ─────────────────────────────────

    private function coreLocationRules(?int $ignoreId = null): array
    {
        $codeRule = ['string', 'max:255', 'alpha_dash'];
        if ($ignoreId !== null) {
            $codeRule[] = Rule::unique('locations', 'code')->ignore($ignoreId);
            return [
                'name'   => ['sometimes', 'required', 'string', 'max:255'],
                'code'   => array_merge(['sometimes', 'required'], $codeRule),
                'status' => ['sometimes', 'required', 'string', Rule::in(['ACTIVE', 'INACTIVE'])],
            ];
        }
        $codeRule[] = Rule::unique('locations', 'code');
        return [
            'name'   => ['required', 'string', 'max:255'],
            'code'   => array_merge(['required'], $codeRule),
            'status' => ['nullable', 'string', Rule::in(['ACTIVE', 'INACTIVE'])],
        ];
    }

    private function addressRules(bool $sometimes = false): array
    {
        $prefix = $sometimes ? ['sometimes'] : [];
        return [
            'address_line1'  => array_merge($prefix, ['nullable', 'string', 'max:255']),
            'street_number'  => array_merge($prefix, ['nullable', 'string', 'max:20']),
            'postal_code'    => array_merge($prefix, ['nullable', 'string', 'max:20']),
            'city'           => array_merge($prefix, ['nullable', 'string', 'max:120']),
            'country'        => array_merge($prefix, ['nullable', 'string', 'max:120']),
            'phone'          => array_merge($prefix, ['nullable', 'string', 'max:30']),
            'email'          => array_merge($prefix, ['nullable', 'email', 'max:255']),
            'website'        => array_merge($prefix, ['nullable', 'string', 'max:500']),
            'latitude'       => array_merge($prefix, ['nullable', 'numeric', 'between:-90,90']),
            'longitude'      => array_merge($prefix, ['nullable', 'numeric', 'between:-180,180']),
        ];
    }

    private function buildLocationPayload(array $validated): array
    {
        $payload = [];

        if (array_key_exists('name', $validated)) {
            $payload['name'] = trim((string) $validated['name']);
        }
        if (array_key_exists('code', $validated)) {
            $payload['code'] = strtoupper(trim((string) $validated['code']));
        }
        if (array_key_exists('status', $validated)) {
            $payload['status'] = $validated['status'] ?? 'ACTIVE';
        } elseif (! array_key_exists('code', $payload)) {
            // store (not update) default
        }

        $addressFields = ['address_line1', 'street_number', 'postal_code', 'city', 'country', 'phone', 'email', 'website', 'latitude', 'longitude'];
        foreach ($addressFields as $field) {
            if (array_key_exists($field, $validated)) {
                $payload[$field] = $validated[$field];
            }
        }

        if (! array_key_exists('status', $payload) && ! array_key_exists('code', $payload)) {
            $payload['status'] = 'ACTIVE';
        }

        return $payload;
    }

    /**
     * @return array<string, int>
     */
    private function buildBlockingUsageCounts(int $harborId): array
    {
        return [
            'clients' => User::query()->where('client_location_id', $harborId)->count(),
            'staff_assignments' => DB::table('location_user')->where('location_id', $harborId)->count(),
            'boats' => Boat::query()->where('location_id', $harborId)->count(),
            'yachts' => Yacht::query()->where('ref_harbor_id', $harborId)->count(),
            'leads' => Lead::query()->where('location_id', $harborId)->count(),
            'conversations' => Conversation::query()->where('location_id', $harborId)->count(),
            'tasks' => Task::query()->where('location_id', $harborId)->count(),
            'boards' => DB::table('boards')->where('location_id', $harborId)->count(),
            'columns' => DB::table('columns')->where('location_id', $harborId)->count(),
            'task_automations' => DB::table('task_automations')->where('location_id', $harborId)->count(),
            'task_automation_templates' => DB::table('task_automation_templates')->where('location_id', $harborId)->count(),
            'sign_requests' => DB::table('sign_requests')->where('location_id', $harborId)->count(),
            'harbor_channels' => DB::table('harbor_channels')->where('harbor_id', $harborId)->count(),
            'call_sessions' => DB::table('call_sessions')->where('harbor_id', $harborId)->count(),
        ];
    }
}
