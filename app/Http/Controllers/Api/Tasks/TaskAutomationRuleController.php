<?php

namespace App\Http\Controllers\Api\Tasks;

use App\Actions\TaskAutomationRule\CreateTaskAutomationRuleAction;
use App\Actions\TaskAutomationRule\DeleteTaskAutomationRuleAction;
use App\Actions\TaskAutomationRule\ListTaskAutomationRulesAction;
use App\Actions\TaskAutomationRule\ShowTaskAutomationRuleAction;
use App\Actions\TaskAutomationRule\UpdateTaskAutomationRuleAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Tasks\TaskAutomationRuleSimulateRequest;
use App\Http\Requests\Api\Tasks\TaskAutomationRuleStoreRequest;
use App\Http\Requests\Api\Tasks\TaskAutomationRuleUpdateRequest;
use App\Models\Bid;
use App\Models\Booking;
use App\Models\TaskAutomationExecutionLog;
use App\Models\TaskAutomationRule;
use App\Models\Yacht;
use App\Services\LocationAccessService;
use App\Services\PermissionService;
use App\Services\TaskAutomationRuleEngine;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;

class TaskAutomationRuleController extends Controller
{
    public function __construct(
        private TaskAutomationRuleEngine $engine,
        private LocationAccessService $locationAccess,
        private PermissionService $permissions
    ) {
    }

    public function index(Request $request, ListTaskAutomationRulesAction $action)
    {
        return response()->json($action->execute($request->user(), $request->all()));
    }

    public function show(int $id, Request $request, ShowTaskAutomationRuleAction $action)
    {
        return response()->json($action->execute($request->user(), $id));
    }

    public function store(TaskAutomationRuleStoreRequest $request, CreateTaskAutomationRuleAction $action)
    {
        return response()->json($action->execute($request->user(), $request->validated()), 201);
    }

    public function update(TaskAutomationRuleUpdateRequest $request, int $id, UpdateTaskAutomationRuleAction $action)
    {
        $rule = TaskAutomationRule::findOrFail($id);

        return response()->json($action->execute($request->user(), $rule, $request->validated()));
    }

    public function destroy(int $id, Request $request, DeleteTaskAutomationRuleAction $action)
    {
        $rule = TaskAutomationRule::findOrFail($id);
        $action->execute($request->user(), $rule);

        return response()->json(['message' => 'Rule deleted']);
    }

    public function simulate(TaskAutomationRuleSimulateRequest $request)
    {
        $data = $request->validated();
        $this->authorizeLocation($request->user(), $this->entityLocationId($data['entity_type'], (int) $data['entity_id']));

        return response()->json($this->engine->simulate(
            $data['trigger_event'],
            $data['entity_type'],
            (int) $data['entity_id'],
            $request->user()
        ));
    }

    public function logs(Request $request)
    {
        return response()->json($this->engine->logs(
            (int) $request->integer('limit', 50),
            $this->permittedLocationIds($request->user())
        ));
    }

    public function retryLog(int $id, Request $request)
    {
        $log = TaskAutomationExecutionLog::with('rule')->findOrFail($id);
        $this->authorizeLocation($request->user(), $log->rule?->location_id);
        $createdTaskIds = $this->engine->retryLog($log, $request->user());

        return response()->json([
            'message' => 'Automation retry completed.',
            'created_task_ids' => $createdTaskIds,
        ]);
    }

    private function permittedLocationIds($user): ?array
    {
        if ($user->isAdmin()) {
            return null;
        }

        $locationIds = $this->permissions->locationIdsForPermission($user, 'tasks.automation');
        if (count($locationIds) === 0) {
            throw new AuthorizationException('Unauthorized');
        }

        return $locationIds;
    }

    private function authorizeLocation($user, ?int $locationId): void
    {
        if ($user->isAdmin()) {
            return;
        }

        if ($locationId) {
            if (! $this->locationAccess->sharesLocation($user, $locationId)) {
                throw new AuthorizationException('Unauthorized');
            }

            if (! $this->permissions->hasLocationPermission($user, 'tasks.automation', $locationId)) {
                throw new AuthorizationException('Unauthorized');
            }

            return;
        }

        if (! $this->permissions->hasLocationPermission($user, 'tasks.automation')) {
            throw new AuthorizationException('Unauthorized');
        }
    }

    private function entityLocationId(string $type, int $id): ?int
    {
        $model = match ($type) {
            Yacht::class, 'App\\Models\\Yacht', 'Yacht' => Yacht::query()->findOrFail($id),
            Booking::class, 'App\\Models\\Booking', 'Booking' => Booking::query()->findOrFail($id),
            Bid::class, 'App\\Models\\Bid', 'Bid' => Bid::query()->findOrFail($id),
            default => Yacht::query()->findOrFail($id),
        };

        if ($model instanceof Yacht) {
            return $model->ref_harbor_id ?: $model->location_id;
        }

        if ($model instanceof Booking) {
            return $model->location_id ?: $model->boat?->ref_harbor_id ?: $model->boat?->location_id;
        }

        if ($model instanceof Bid) {
            return $model->location_id ?: $model->yacht?->ref_harbor_id ?: $model->yacht?->location_id;
        }

        return null;
    }
}
