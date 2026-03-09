<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Boat;
use App\Models\Conversation;
use App\Models\Lead;
use App\Models\Location;
use App\Models\Task;
use App\Models\User;
use App\Models\Yacht;
use App\Services\Ga4DataApiService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class HarborController extends Controller
{
    public function __construct(private Ga4DataApiService $ga4)
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

        $counts = $this->buildSnapshotCounts(collect([$harbor->id]));

        return response()->json([
            'data' => $this->serializeHarbor($harbor, $counts),
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
        $query = Location::query();

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

        return $query->orderBy('name');
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

        return [
            'id' => $id,
            'name' => $harbor->name,
            'code' => $harbor->code,
            'status' => $harbor->status,
            'clients_total' => $counts['clients'][$id] ?? 0,
            'staff_total' => $counts['staff'][$id] ?? 0,
            'boats_total' => $counts['boats'][$id] ?? 0,
            'yachts_total' => $counts['yachts'][$id] ?? 0,
            'open_leads' => $counts['open_leads'][$id] ?? 0,
            'open_conversations' => $counts['open_conversations'][$id] ?? 0,
            'open_tasks' => $counts['open_tasks'][$id] ?? 0,
            'created_at' => $harbor->created_at,
            'updated_at' => $harbor->updated_at,
        ];
    }
}
