<?php

namespace App\Services;

use App\Models\Bid;
use App\Models\Booking;
use App\Models\Task;
use App\Models\TaskActivityLog;
use App\Models\TaskAutomation;
use App\Models\TaskAutomationExecutionLog;
use App\Models\TaskAutomationRule;
use App\Models\TaskAutomationTemplate;
use App\Models\User;
use App\Models\Yacht;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TaskAutomationRuleEngine
{
    public function __construct(private BoatTaskTemplateRenderer $renderer)
    {
    }

    public function handle(string $triggerEvent, Model $entity, ?User $actor = null): array
    {
        $context = $this->buildContext($triggerEvent, $entity, $actor);
        $rules = $this->matchingRules($triggerEvent, $context)->get();
        $createdTaskIds = [];

        foreach ($rules as $rule) {
            if (! $this->matchesRule($rule, $context)) {
                $this->log($rule, $triggerEvent, $entity, 'skipped', 'conditions_not_matched');
                continue;
            }

            $assignee = $this->resolveAssignee($rule, $context);
            if ($this->requiresAssignee($rule) && ! $assignee) {
                $this->log($rule, $triggerEvent, $entity, 'skipped', 'no_assignee_resolved');
                continue;
            }

            try {
                $ids = DB::transaction(fn () => $this->createTasksForRule($rule, $triggerEvent, $context, $assignee));
                $createdTaskIds = array_merge($createdTaskIds, $ids);
                $this->log($rule, $triggerEvent, $entity, 'success', null, $ids, [
                    'target_role' => $rule->target_role,
                    'target_user_type' => $rule->target_user_type,
                    'boat_types' => $rule->boat_types,
                    'location_filter' => $rule->location_filter,
                ]);
            } catch (\Throwable $error) {
                $this->log($rule, $triggerEvent, $entity, 'failed', Str::limit($error->getMessage(), 255));
                throw $error;
            }
        }

        return $createdTaskIds;
    }

    public function simulate(string $triggerEvent, string $entityType, int $entityId, ?User $actor = null): array
    {
        $entity = $this->resolveEntity($entityType, $entityId);
        $context = $this->buildContext($triggerEvent, $entity, $actor);
        $preview = [];

        foreach ($this->matchingRules($triggerEvent, $context)->get() as $rule) {
            if (! $this->matchesRule($rule, $context)) {
                continue;
            }

            $assignee = $this->resolveAssignee($rule, $context);
            $related = $this->resolveRelatedModel($context);
            $tasks = [];

            foreach ($rule->templates as $template) {
                foreach ($this->templateTaskPayloads($template, $context) as $payload) {
                    $tasks[] = [
                        'template_id' => $template->id,
                        'title' => $payload['title'],
                        'description' => $payload['description'],
                        'priority' => $payload['priority'],
                        'assignee_id' => $assignee?->id,
                        'related_type' => $related?->getMorphClass(),
                        'related_id' => $related?->getKey(),
                        'would_skip_duplicate' => $this->automationExists($rule, $template, $related),
                    ];
                }
            }

            $preview[] = [
                'rule_id' => $rule->id,
                'rule_name' => $rule->name,
                'tasks' => $tasks,
            ];
        }

        return [
            'trigger' => $triggerEvent,
            'matched_rules' => count($preview),
            'preview' => $preview,
        ];
    }

    public function logs(int $limit = 50, ?array $locationIds = null)
    {
        return TaskAutomationExecutionLog::query()
            ->with('rule:id,name,location_id')
            ->when(is_array($locationIds), function ($query) use ($locationIds) {
                $query->whereHas('rule', function ($ruleQuery) use ($locationIds) {
                    $ruleQuery->whereNull('location_id')->orWhereIn('location_id', $locationIds);
                });
            })
            ->latest('created_at')
            ->limit($limit)
            ->get();
    }

    public function retryLog(TaskAutomationExecutionLog $log, ?User $actor = null): array
    {
        if (! $log->entity_type || ! $log->entity_id) {
            throw new \InvalidArgumentException('Execution log has no retryable entity.');
        }

        $entity = $this->resolveEntity($log->entity_type, (int) $log->entity_id);

        return $this->handle($log->trigger_event, $entity, $actor);
    }

    private function matchingRules(string $triggerEvent, array $context)
    {
        return TaskAutomationRule::query()
            ->with('templates.items')
            ->where('is_active', true)
            ->where('trigger_event', $triggerEvent)
            ->where(function ($query) use ($context) {
                $query->whereNull('location_id');

                if ($context['location_id']) {
                    $query->orWhere('location_id', $context['location_id']);
                }
            });
    }

    private function createTasksForRule(TaskAutomationRule $rule, string $triggerEvent, array $context, ?User $assignee): array
    {
        $related = $this->resolveRelatedModel($context);
        $createdIds = [];

        foreach ($rule->templates as $template) {
            if ($this->automationExists($rule, $template, $related)) {
                continue;
            }

            foreach ($this->templateTaskPayloads($template, $context) as $payload) {
                $task = Task::create(array_merge($payload, [
                    'status' => 'New',
                    'assignment_status' => $assignee ? 'pending' : 'accepted',
                    'assigned_to' => $assignee?->id,
                    'user_id' => $rule->target_role === 'client' ? $assignee?->id : null,
                    'created_by' => $context['actor']?->id ?? $this->defaultAdmin()?->id,
                    'yacht_id' => $context['boat']?->id,
                    'due_date' => $this->dueDate($rule, $context)->toDateString(),
                    'type' => 'assigned',
                    'client_visible' => $rule->target_role === 'client',
                    'location_id' => $context['location_id'],
                ]));

                TaskAutomation::create([
                    'template_id' => $template->id,
                    'rule_id' => $rule->id,
                    'trigger_event' => $triggerEvent,
                    'related_type' => $related?->getMorphClass(),
                    'related_id' => $related?->getKey(),
                    'assigned_user_id' => $assignee?->id,
                    'due_at' => $this->dueDate($rule, $context),
                    'status' => 'executed',
                    'created_task_id' => $task->id,
                    'location_id' => $context['location_id'],
                ]);

                TaskActivityLog::create([
                    'task_id' => $task->id,
                    'user_id' => $context['actor']?->id ?? $this->defaultAdmin()?->id,
                    'action' => 'automation_created',
                    'description' => sprintf('Task created automatically by rule "%s" (%s).', $rule->name, $triggerEvent),
                ]);

                $createdIds[] = $task->id;
            }
        }

        return $createdIds;
    }

    private function templateTaskPayloads(TaskAutomationTemplate $template, array $context): array
    {
        $items = $template->items;
        $sourceItems = $items->isNotEmpty()
            ? $items
            : collect([(object) [
                'title' => $template->title,
                'description' => $template->description,
                'priority' => $template->priority,
            ]]);

        return $sourceItems->map(fn ($item) => [
            'title' => $this->render($item->title, $context),
            'description' => $this->render($item->description ?? '', $context),
            'priority' => $item->priority ?? $template->priority,
        ])->values()->all();
    }

    private function buildContext(string $triggerEvent, Model $entity, ?User $actor): array
    {
        $boat = null;
        $booking = null;
        $bid = null;
        $seller = null;
        $buyer = null;

        if ($entity instanceof Yacht) {
            $entity->loadMissing('owner');
            $boat = $entity;
            $seller = $entity->owner;
        } elseif ($entity instanceof Booking) {
            $entity->loadMissing('boat.owner');
            $booking = $entity;
            $boat = $entity->boat;
            $seller = $boat?->owner;
        } elseif ($entity instanceof Bid) {
            $entity->loadMissing('yacht.owner');
            $bid = $entity;
            $boat = $entity->yacht;
            $seller = $boat?->owner;
            $buyer = $entity->bidder_id ? User::find($entity->bidder_id) : null;
        }

        if (! $buyer && $actor && $actor->isClient() && $actor->role === 'buyer') {
            $buyer = $actor;
        }

        if (! $seller && $actor && $actor->isClient() && in_array($actor->role, ['seller', 'client'], true)) {
            $seller = $actor;
        }

        $locationId = $boat?->ref_harbor_id ?: $boat?->location_id ?: $booking?->location_id ?: $bid?->location_id ?: null;

        return [
            'trigger_event' => $triggerEvent,
            'entity' => $entity,
            'actor' => $actor,
            'boat' => $boat,
            'booking' => $booking,
            'bid' => $bid,
            'seller_user' => $seller,
            'buyer_user' => $buyer,
            'boat_type' => $this->normalize($boat?->boat_type),
            'boat_year' => $boat?->year ? (int) $boat->year : null,
            'location' => $this->normalize($boat?->location_city ?: $seller?->city ?: $buyer?->city),
            'location_id' => $locationId ? (int) $locationId : null,
        ];
    }

    private function matchesRule(TaskAutomationRule $rule, array $context): bool
    {
        if ($rule->target_role === 'client' && ! $this->targetClient($rule, $context)) {
            return false;
        }

        $boatTypes = collect($rule->boat_types ?? [])->map(fn ($type) => $this->normalize($type))->filter();
        if ($boatTypes->isNotEmpty() && ! $boatTypes->contains($context['boat_type'])) {
            return false;
        }

        if ($rule->boat_year_from !== null && ($context['boat_year'] === null || $context['boat_year'] < $rule->boat_year_from)) {
            return false;
        }

        if ($rule->boat_year_to !== null && ($context['boat_year'] === null || $context['boat_year'] > $rule->boat_year_to)) {
            return false;
        }

        if ($rule->location_filter && ! Str::contains($context['location'] ?? '', $this->normalize($rule->location_filter))) {
            return false;
        }

        if ($rule->visibility_status && $this->normalize($this->statusForSource($rule->visibility_status_source, $context)) !== $this->normalize($rule->visibility_status)) {
            return false;
        }

        if ($rule->actionable_status && $this->normalize($this->statusForSource($rule->actionable_status_source, $context)) !== $this->normalize($rule->actionable_status)) {
            return false;
        }

        if ($rule->actionable_requires_internal_tasks_completed && ! $this->internalTasksCompleted($context)) {
            return false;
        }

        return true;
    }

    private function targetClient(TaskAutomationRule $rule, array $context): ?User
    {
        return match ($rule->target_user_type) {
            'seller' => $context['seller_user'],
            'buyer' => $context['buyer_user'],
            default => null,
        };
    }

    private function resolveAssignee(TaskAutomationRule $rule, array $context): ?User
    {
        if ($rule->assignee_rule === 'specific_user' && $rule->assigned_user_id) {
            return User::find($rule->assigned_user_id);
        }

        if ($rule->target_role === 'client') {
            return $this->targetClient($rule, $context);
        }

        return match ($rule->assignee_rule) {
            'seller' => $context['seller_user'],
            'creator' => $context['actor'],
            'harbor_user' => $this->locationStaff($context['location_id']) ?? $this->defaultAdmin(),
            default => $this->defaultAdmin(),
        };
    }

    private function requiresAssignee(TaskAutomationRule $rule): bool
    {
        return in_array($rule->target_role, ['client', 'admin', 'employee'], true);
    }

    private function automationExists(TaskAutomationRule $rule, TaskAutomationTemplate $template, ?Model $related): bool
    {
        return TaskAutomation::query()
            ->where('rule_id', $rule->id)
            ->where('template_id', $template->id)
            ->where('related_type', $related?->getMorphClass())
            ->where('related_id', $related?->getKey())
            ->exists();
    }

    private function resolveEntity(string $type, int $id): Model
    {
        $model = match ($type) {
            Yacht::class, 'App\\Models\\Yacht', 'Yacht' => Yacht::class,
            Booking::class, 'App\\Models\\Booking', 'Booking' => Booking::class,
            Bid::class, 'App\\Models\\Bid', 'Bid' => Bid::class,
            default => Yacht::class,
        };

        return $model::findOrFail($id);
    }

    private function resolveRelatedModel(array $context): ?Model
    {
        return $context['booking'] ?: $context['bid'] ?: $context['boat'] ?: $context['entity'];
    }

    private function dueDate(TaskAutomationRule $rule, array $context): Carbon
    {
        $base = $context['entity']->created_at ? Carbon::parse($context['entity']->created_at) : now();

        return $base->copy()->addHours((int) ($rule->visibility_delay_hours ?? 0));
    }

    private function statusForSource(?string $source, array $context): ?string
    {
        $model = match ($source) {
            'boat' => $context['boat'],
            'booking' => $context['booking'],
            'bid' => $context['bid'],
            default => $this->resolveRelatedModel($context),
        };

        return $model?->status;
    }

    private function internalTasksCompleted(array $context): bool
    {
        $boat = $context['boat'];
        if (! $boat) {
            return true;
        }

        return ! Task::query()
            ->where('yacht_id', $boat->id)
            ->where('client_visible', false)
            ->whereNotIn('status', ['Completed', 'Done', 'Closed'])
            ->exists();
    }

    private function render(?string $value, array $context): string
    {
        if ($context['boat']) {
            return (string) ($this->renderer->render($value, $context['boat'], $context['seller_user']) ?? '');
        }

        return (string) ($value ?? '');
    }

    private function locationStaff(?int $locationId): ?User
    {
        if (! $locationId) {
            return null;
        }

        return User::query()
            ->whereIn('type', ['ADMIN', 'EMPLOYEE'])
            ->whereHas('locations', fn ($query) => $query->where('locations.id', $locationId))
            ->orderBy('id')
            ->first();
    }

    private function defaultAdmin(): ?User
    {
        return User::query()->where('type', 'ADMIN')->orderBy('id')->first();
    }

    private function log(
        ?TaskAutomationRule $rule,
        string $triggerEvent,
        Model $entity,
        string $result,
        ?string $reason = null,
        array $createdTaskIds = [],
        array $matchedConditions = []
    ): void {
        TaskAutomationExecutionLog::create([
            'rule_id' => $rule?->id,
            'trigger_event' => $triggerEvent,
            'entity_type' => $entity->getMorphClass(),
            'entity_id' => $entity->getKey(),
            'result' => $result,
            'reason' => $reason,
            'created_task_ids' => $createdTaskIds,
            'matched_conditions' => $matchedConditions,
            'created_at' => now(),
        ]);
    }

    private function normalize(?string $value): ?string
    {
        return $value === null ? null : strtolower(trim($value));
    }
}
