<?php

namespace App\Services;

use App\Models\Task;
use App\Models\TaskAutomation;
use App\Models\TaskAutomationTemplate;
use App\Models\User;
use App\Models\Yacht;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class BoatTaskAutomationService
{
    public function __construct(private BoatTaskTemplateRenderer $templateRenderer)
    {
    }

    /**
     * Fire task automation for a newly created or updated yacht.
     *
     * @param Yacht $yacht
     * @param User  $actor  The user who created/updated the yacht
     * @param bool  $isUpdate Whether this is a boat_type change (not initial creation)
     * @return array Created tasks
     */
    public function fireForYacht(Yacht $yacht, User $actor, bool $isUpdate = false): array
    {
        $boatType = $yacht->boat_type ?? '';

        // Find matching automation templates
        $templates = TaskAutomationTemplate::query()
            ->where('trigger_event', 'boat_created')
            ->where('is_active', true)
            ->with('items')
            ->get()
            ->filter(function (TaskAutomationTemplate $template) use ($boatType) {
                return $this->templateRenderer->matchesBoatTypeFilter($template->boat_type_filter, $boatType);
            });

        if ($templates->isEmpty()) {
            Log::info('[BoatTaskAutomation] No matching templates', [
                'yacht_id' => $yacht->id,
                'boat_type' => $boatType,
            ]);
            return [];
        }

        // If this is an update, check whether tasks were already generated
        if ($isUpdate) {
            $existingAutomations = TaskAutomation::where('related_type', 'App\\Models\\Yacht')
                ->where('related_id', $yacht->id)
                ->where('trigger_event', 'boat_created')
                ->exists();

            if ($existingAutomations) {
                Log::info('[BoatTaskAutomation] Tasks already exist for this yacht, skipping re-generation', [
                    'yacht_id' => $yacht->id,
                ]);
                return [];
            }
        }

        $createdTasks = [];
        $assigneeId = $this->resolveAssignee($actor);

        DB::beginTransaction();
        try {
            foreach ($templates as $template) {
                $items = $template->items;

                if ($items->isNotEmpty()) {
                    // Multi-task: create one Task per template item
                    foreach ($items as $item) {
                        $title = $this->interpolateTemplate($item->title, $yacht);
                        $description = $this->interpolateTemplate($item->description ?? '', $yacht);
                        $task = $this->createTask($title, $description, $item->priority, $yacht, $actor, $assigneeId);
                        $this->createAutomationRecord($template, $yacht, $task, $assigneeId);
                        $createdTasks[] = $task;
                    }
                } else {
                    // Single-task: use template title/description
                    $title = $this->interpolateTemplate($template->title, $yacht);
                    $description = $this->interpolateTemplate($template->description ?? '', $yacht);
                    $task = $this->createTask($title, $description, $template->priority, $yacht, $actor, $assigneeId);
                    $this->createAutomationRecord($template, $yacht, $task, $assigneeId);
                    $createdTasks[] = $task;
                }
            }

            DB::commit();

            Log::info('[BoatTaskAutomation] Created tasks', [
                'yacht_id' => $yacht->id,
                'boat_type' => $boatType,
                'task_count' => count($createdTasks),
                'tasks' => array_map(fn($t) => ['id' => $t->id, 'title' => $t->title, 'y_id' => $t->yacht_id], $createdTasks)
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('[BoatTaskAutomation] Failed to create tasks', [
                'yacht_id' => $yacht->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }

        return $createdTasks;
    }

    private function createTask(string $title, ?string $description, string $priority, Yacht $yacht, User $actor, ?int $assigneeId): Task
    {
        Log::info('[BoatTaskAutomation] Creating task', ['title' => $title, 'yacht_id' => $yacht->id]);
        return Task::create([
            'title' => $title,
            'description' => $description ?? '',
            'priority' => $priority,
            'status' => 'New',
            'assigned_to' => $assigneeId,
            'user_id' => $actor->id,
            'created_by' => $actor->id,
            'yacht_id' => (int) $yacht->id,
            'due_date' => Carbon::now()->addDays(7),
            'type' => 'assigned',
            'client_visible' => true,
            'location_id' => $yacht->ref_harbor_id ?? $actor->locations()->value('locations.id'),
        ]);
    }

    private function createAutomationRecord(TaskAutomationTemplate $template, Yacht $yacht, Task $task, ?int $assigneeId): TaskAutomation
    {
        return TaskAutomation::create([
            'template_id' => $template->id,
            'trigger_event' => 'boat_created',
            'related_type' => 'App\\Models\\Yacht',
            'related_id' => $yacht->id,
            'assigned_user_id' => $assigneeId,
            'due_at' => $task->due_date ?? Carbon::now()->addDays(7),
            'status' => 'executed',
            'created_task_id' => $task->id,
            'location_id' => $task->location_id,
        ]);
    }

    private function resolveAssignee(User $actor): ?int
    {
        // Default: assign to an active admin user
        $adminId = User::where('type', 'ADMIN')
            ->where('status', 'ACTIVE')
            ->value('id');

        return $adminId ?? $actor->id;
    }

    /**
     * Replace template variables like {boat_id}, {boat_name}, {boat_type}
     */
    private function interpolateTemplate(string $text, Yacht $yacht): string
    {
        return $this->templateRenderer->render($text, $yacht) ?? '';
    }
}
