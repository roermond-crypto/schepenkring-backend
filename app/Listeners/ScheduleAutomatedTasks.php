<?php

namespace App\Listeners;

use App\Events\AutomationEvent;
use App\Models\TaskAutomation;
use App\Models\TaskAutomationTemplate;
use App\Models\User;
use Carbon\Carbon;
use Cron\CronExpression;
use Illuminate\Support\Facades\Log;

class ScheduleAutomatedTasks
{
    public function handle(AutomationEvent $event): void
    {
        $trigger = $event->triggerName();
        $entity = $event->entity;

        $templates = TaskAutomationTemplate::where('trigger_event', $trigger)
            ->where('is_active', true)
            ->get();

        if ($templates->isEmpty()) {
            return;
        }

        foreach ($templates as $template) {
            $dueAt = $this->calculateDueAt($template, $entity);
            if (!$dueAt) {
                Log::warning('Task automation skipped (no due date)', [
                    'template_id' => $template->id,
                    'trigger' => $trigger,
                ]);
                continue;
            }

            $assignedUserId = $this->resolveAssigneeId(
                $template->default_assignee_type,
                $entity,
                $event->actor
            );

            TaskAutomation::create([
                'template_id' => $template->id,
                'trigger_event' => $trigger,
                'related_type' => $entity ? $entity::class : null,
                'related_id' => $entity && isset($entity->id) ? $entity->id : null,
                'assigned_user_id' => $assignedUserId,
                'due_at' => $dueAt,
                'status' => 'pending',
            ]);
        }
    }

    private function calculateDueAt(TaskAutomationTemplate $template, mixed $entity): ?Carbon
    {
        $now = Carbon::now();

        if ($template->schedule_type === 'relative') {
            $base = $now;
            if ($entity && isset($entity->created_at)) {
                $base = Carbon::parse($entity->created_at);
            }
            if (!$template->delay_value || !$template->delay_unit) {
                return null;
            }

            return match ($template->delay_unit) {
                'minutes' => $base->copy()->addMinutes($template->delay_value),
                'hours' => $base->copy()->addHours($template->delay_value),
                'days' => $base->copy()->addDays($template->delay_value),
                'weeks' => $base->copy()->addWeeks($template->delay_value),
                default => null,
            };
        }

        if ($template->schedule_type === 'fixed') {
            return $template->fixed_at ? Carbon::parse($template->fixed_at) : null;
        }

        if ($template->schedule_type === 'recurring') {
            if (empty($template->cron_expression)) {
                return null;
            }
            $cron = CronExpression::factory($template->cron_expression);
            return Carbon::instance($cron->getNextRunDate($now, 0, true));
        }

        return null;
    }

    private function resolveAssigneeId(string $assigneeType, mixed $entity, ?User $actor): ?int
    {
        if ($assigneeType === 'creator' && $actor) {
            return $actor->id;
        }

        if ($assigneeType === 'seller') {
            if ($entity && isset($entity->user_id)) {
                return (int) $entity->user_id;
            }
        }

        if ($assigneeType === 'buyer') {
            if ($entity && isset($entity->user_id)) {
                return (int) $entity->user_id;
            }
        }

        if ($assigneeType === 'related_owner') {
            if ($entity && isset($entity->owner_id)) {
                return (int) $entity->owner_id;
            }
            if ($entity && isset($entity->user_id)) {
                return (int) $entity->user_id;
            }
        }

        if ($assigneeType === 'harbor') {
            if ($entity && isset($entity->partner_id)) {
                return (int) $entity->partner_id;
            }
            if ($entity && isset($entity->user_id)) {
                $user = User::find($entity->user_id);
                if ($user && $user->partner_id) {
                    return (int) $user->partner_id;
                }
            }
        }

        if ($assigneeType === 'admin') {
            return $this->getDefaultAdminId();
        }

        return $this->getDefaultAdminId();
    }

    private function getDefaultAdminId(): ?int
    {
        return User::where('role', 'Admin')
            ->where('status', 'Active')
            ->value('id');
    }
}
