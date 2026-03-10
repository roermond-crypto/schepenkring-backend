<?php

namespace App\Services;

use App\Models\Task;
use App\Models\TaskAutomation;
use App\Models\TaskAutomationTemplate;
use App\Models\User;
use App\Models\Yacht;
use Carbon\Carbon;
use Cron\CronExpression;
use Illuminate\Support\Facades\Log;

class SyncYachtTasksService
{
    public function syncForYacht(Yacht $yacht, ?User $actor = null): void
    {
        $recipient = $yacht->user_id ? User::find($yacht->user_id) : null;

        if (! $recipient) {
            return;
        }

        $locationId = $this->resolveLocationId($yacht, $recipient);

        $templates = TaskAutomationTemplate::query()
            ->where('is_active', true)
            ->where('trigger_event', 'boat_created')
            ->where(function ($query) use ($locationId) {
                $query->whereNull('location_id');

                if ($locationId) {
                    $query->orWhere('location_id', $locationId);
                }
            })
            ->orderBy('id')
            ->get();

        foreach ($templates as $template) {
            $this->syncTemplate($template, $yacht, $recipient, $actor, $locationId);
        }
    }

    private function syncTemplate(
        TaskAutomationTemplate $template,
        Yacht $yacht,
        User $recipient,
        ?User $actor,
        ?int $locationId
    ): void {
        $resolvedLocationId = $template->location_id ?: $locationId;
        $assigneeId = $this->resolveAssigneeId($template, $recipient, $resolvedLocationId);

        $automation = TaskAutomation::query()->firstOrNew([
            'template_id' => $template->id,
            'related_type' => Yacht::class,
            'related_id' => $yacht->id,
        ]);

        if (! $automation->due_at) {
            $automation->due_at = $this->calculateDueAt($template);
        }

        $automation->trigger_event = 'boat_created';
        $automation->assigned_user_id = $assigneeId;
        $automation->location_id = $resolvedLocationId;
        $automation->status = 'synced';
        $automation->save();

        $task = $automation->created_task_id ? Task::find($automation->created_task_id) : null;

        $basePayload = [
            'title' => $this->renderText($template->title, $yacht, $recipient),
            'description' => $this->renderText($template->description, $yacht, $recipient),
            'priority' => $template->priority,
            'type' => 'assigned',
            'assigned_to' => $assigneeId,
            'user_id' => $recipient->id,
            'location_id' => $resolvedLocationId,
            'client_visible' => $recipient->isClient(),
            'due_date' => $automation->due_at?->toDateString(),
        ];

        if (! $task) {
            $task = Task::create(array_merge($basePayload, [
                'status' => 'New',
                'assignment_status' => $assigneeId === $recipient->id ? 'accepted' : 'pending',
                'created_by' => $actor?->id ?? $recipient->id,
            ]));
        } else {
            $task->fill($basePayload);
            $task->save();
        }

        if ($automation->created_task_id !== $task->id) {
            $automation->created_task_id = $task->id;
            $automation->save();
        }
    }

    private function resolveLocationId(Yacht $yacht, User $recipient): ?int
    {
        if ($recipient->client_location_id) {
            return $recipient->client_location_id;
        }

        if ($yacht->ref_harbor_id) {
            return (int) $yacht->ref_harbor_id;
        }

        if ($recipient->isEmployee()) {
            return $recipient->locations()->value('locations.id');
        }

        return null;
    }

    private function resolveAssigneeId(TaskAutomationTemplate $template, User $recipient, ?int $locationId): ?int
    {
        return match ($template->default_assignee_type) {
            'seller', 'buyer', 'creator', 'related_owner' => $recipient->id,
            'harbor', 'admin' => $this->resolveLocationStaffId($locationId) ?? $this->resolveDefaultAdminId() ?? $recipient->id,
            default => $recipient->id,
        };
    }

    private function resolveLocationStaffId(?int $locationId): ?int
    {
        if (! $locationId) {
            return null;
        }

        return User::query()
            ->whereIn('type', ['ADMIN', 'EMPLOYEE'])
            ->whereHas('locations', fn ($query) => $query->where('locations.id', $locationId))
            ->orderBy('id')
            ->value('id');
    }

    private function resolveDefaultAdminId(): ?int
    {
        return User::query()
            ->where('type', 'ADMIN')
            ->orderBy('id')
            ->value('id');
    }

    private function calculateDueAt(TaskAutomationTemplate $template): ?Carbon
    {
        $now = Carbon::now();

        return match ($template->schedule_type) {
            'relative' => $this->calculateRelativeDueAt($template, $now),
            'fixed' => $template->fixed_at ? Carbon::parse($template->fixed_at) : null,
            'recurring' => $this->calculateRecurringDueAt($template, $now),
            default => null,
        };
    }

    private function calculateRelativeDueAt(TaskAutomationTemplate $template, Carbon $baseAt): ?Carbon
    {
        if (! $template->delay_value || ! $template->delay_unit) {
            return null;
        }

        return match ($template->delay_unit) {
            'minutes' => $baseAt->copy()->addMinutes($template->delay_value),
            'hours' => $baseAt->copy()->addHours($template->delay_value),
            'days' => $baseAt->copy()->addDays($template->delay_value),
            'weeks' => $baseAt->copy()->addWeeks($template->delay_value),
            default => null,
        };
    }

    private function calculateRecurringDueAt(TaskAutomationTemplate $template, Carbon $baseAt): ?Carbon
    {
        if (! $template->cron_expression) {
            return null;
        }

        try {
            $cron = CronExpression::factory($template->cron_expression);

            return Carbon::instance($cron->getNextRunDate($baseAt, 0, true));
        } catch (\Throwable $error) {
            Log::warning('Unable to calculate recurring boat task automation', [
                'template_id' => $template->id,
                'error' => $error->getMessage(),
            ]);

            return null;
        }
    }

    private function renderText(?string $value, Yacht $yacht, User $recipient): ?string
    {
        if ($value === null || $value === '') {
            return $value;
        }

        return strtr($value, [
            '{{boat_name}}' => $yacht->boat_name ?? '',
            '{{yacht_name}}' => $yacht->boat_name ?? '',
            '{{vessel_id}}' => $yacht->vessel_id ?? '',
            '{{client_name}}' => $recipient->name ?? '',
        ]);
    }
}
