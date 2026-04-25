<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\RiskLevel;
use App\Http\Controllers\Controller;
use App\Models\Boat;
use App\Models\Conversation;
use App\Models\Lead;
use App\Models\Location;
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
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class AdminLocationController extends Controller
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

        $locations = $this->baseQuery($request)->get();
        $counts = $this->buildSnapshotCounts($locations->pluck('id'));

        return response()->json([
            'data' => $locations->map(fn (Location $location) => $this->serializeLocation($location, $counts)),
        ]);
    }

    public function show(Request $request, Location $location): JsonResponse
    {
        $this->authorizeAdmin($request);
        $location->load([
            'employees' => fn ($query) => $query
                ->select('users.id', 'users.name', 'users.email', 'users.type')
                ->orderBy('users.name'),
        ]);

        $counts = $this->buildSnapshotCounts(collect([$location->id]));

        return response()->json([
            'data' => $this->serializeLocation($location, $counts),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorizeAdmin($request);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:255', 'alpha_dash', Rule::unique('locations', 'code')],
            'status' => ['nullable', 'string', Rule::in(['ACTIVE', 'INACTIVE'])],
        ]);

        $location = Location::create([
            'name' => trim((string) $validated['name']),
            'code' => strtoupper(trim((string) $validated['code'])),
            'status' => $validated['status'] ?? 'ACTIVE',
        ]);

        $this->syncLocationKnowledgeSafely($location);

        return response()->json([
            'data' => $this->serializeLocation($location->fresh(), $this->buildSnapshotCounts(collect([$location->id]))),
        ], 201);
    }

    public function update(Request $request, Location $location): JsonResponse
    {
        $this->authorizeAdmin($request);

        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'code' => ['sometimes', 'required', 'string', 'max:255', 'alpha_dash', Rule::unique('locations', 'code')->ignore($location->id)],
            'status' => ['sometimes', 'required', 'string', Rule::in(['ACTIVE', 'INACTIVE'])],
        ]);

        if (array_key_exists('name', $validated)) {
            $validated['name'] = trim((string) $validated['name']);
        }

        if (array_key_exists('code', $validated)) {
            $validated['code'] = strtoupper(trim((string) $validated['code']));
        }

        $location->update($validated);
        $this->syncLocationKnowledgeSafely($location);

        return response()->json([
            'data' => $this->serializeLocation($location->fresh(), $this->buildSnapshotCounts(collect([$location->id]))),
        ]);
    }

    public function destroy(Request $request, Location $location): JsonResponse
    {
        $this->authorizeAdmin($request);

        $location->delete();
        $this->removeLocationKnowledgeSafely($location);

        return response()->json([
            'message' => 'Location deleted.',
        ]);
    }

    public function performance(Request $request): JsonResponse
    {
        $this->authorizeAdmin($request);

        [$start, $end, $range] = $this->resolvePeriod($request);

        $locations = $this->baseQuery($request)->get();
        $locationIds = $locations->pluck('id');

        $snapshotCounts = $this->buildSnapshotCounts($locationIds);
        $periodCounts = $this->buildPeriodCounts($locationIds, $start, $end);
        $traffic = $this->ga4->fetchTrafficMetrics(
            $start->toDateString(),
            $end->toDateString(),
            array_filter($request->only(['device', 'country', 'source', 'medium', 'campaign']))
        );

        $rows = $locations->map(function (Location $location) use ($snapshotCounts, $periodCounts, $traffic) {
            $id = $location->id;
            $trafficMetrics = $traffic[$id] ?? ['active_users' => 0, 'sessions' => 0];

            return [
                'id' => $id,
                'name' => $location->name,
                'code' => $location->code,
                'status' => $location->status,
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
            Log::warning('Location knowledge sync failed after location update', [
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
            Log::warning('Location knowledge cleanup failed after location delete', [
                'location_id' => $location->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function buildSnapshotCounts(Collection $locationIds): array
    {
        if ($locationIds->isEmpty()) {
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

        $ids = $locationIds->all();

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
                ->selectRaw('location_id as location_id, count(*) as aggregate')
                ->whereNotNull('location_id')
                ->whereIn('location_id', $ids)
                ->groupBy('location_id')
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

    private function buildPeriodCounts(Collection $locationIds, CarbonImmutable $start, CarbonImmutable $end): array
    {
        if ($locationIds->isEmpty()) {
            return [
                'leads_created' => collect(),
                'conversations_created' => collect(),
                'tasks_created' => collect(),
                'tasks_completed' => collect(),
            ];
        }

        $ids = $locationIds->all();
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

    private function serializeLocation(Location $location, array $counts): array
    {
        $id = $location->id;
        $employees = $location->relationLoaded('employees')
            ? $location->employees->values()
            : collect();

        return [
            'id' => $id,
            'name' => $location->name,
            'code' => $location->code,
            'status' => $location->status,
            'clients_total' => $counts['clients'][$id] ?? 0,
            'staff_total' => $counts['staff'][$id] ?? 0,
            'employee_count' => $employees->count(),
            'employees' => $employees->map(fn (User $employee) => [
                'id' => $employee->id,
                'name' => $employee->name,
                'email' => $employee->email,
                'role' => $employee->role,
                'location_role' => $employee->pivot?->role,
            ])->values(),
            'boats_total' => $counts['boats'][$id] ?? 0,
            'yachts_total' => $counts['yachts'][$id] ?? 0,
            'open_leads' => $counts['open_leads'][$id] ?? 0,
            'open_conversations' => $counts['open_conversations'][$id] ?? 0,
            'open_tasks' => $counts['open_tasks'][$id] ?? 0,
            'created_at' => $location->created_at,
            'updated_at' => $location->updated_at,
        ];
    }

    /**
     * @return array<string, int>
     */
    private function buildBlockingUsageCounts(int $locationId): array
    {
        return [
            'clients' => User::query()->where('client_location_id', $locationId)->count(),
            'staff_assignments' => DB::table('location_user')->where('location_id', $locationId)->count(),
            'boats' => Boat::query()->where('location_id', $locationId)->count(),
            'yachts' => Yacht::query()->where('location_id', $locationId)->count(),
            'leads' => Lead::query()->where('location_id', $locationId)->count(),
            'conversations' => Conversation::query()->where('location_id', $locationId)->count(),
            'tasks' => Task::query()->where('location_id', $locationId)->count(),
            'boards' => DB::table('boards')->where('location_id', $locationId)->count(),
            'columns' => DB::table('columns')->where('location_id', $locationId)->count(),
            'task_automations' => DB::table('task_automations')->where('location_id', $locationId)->count(),
            'task_automation_templates' => DB::table('task_automation_templates')->where('location_id', $locationId)->count(),
            'sign_requests' => DB::table('sign_requests')->where('location_id', $locationId)->count(),
            'location_channels' => DB::table('location_channels')->where('location_id', $locationId)->count(),
            'call_sessions' => DB::table('call_sessions')->where('location_id', $locationId)->count(),
        ];
    }
}
