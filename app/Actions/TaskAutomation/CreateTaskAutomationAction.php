<?php

namespace App\Actions\TaskAutomation;

use App\Enums\RiskLevel;
use App\Models\TaskAutomationTemplate;
use App\Models\User;
use App\Repositories\TaskAutomationRepository;
use App\Repositories\TaskAutomationTemplateRepository;
use App\Services\ActionSecurity;
use App\Services\LocationAccessService;
use App\Services\PermissionService;
use Carbon\Carbon;
use Cron\CronExpression;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

class CreateTaskAutomationAction
{
    public function __construct(
        private TaskAutomationRepository $automations,
        private TaskAutomationTemplateRepository $templates,
        private LocationAccessService $locationAccess,
        private PermissionService $permissions,
        private ActionSecurity $security
    ) {
    }

    public function execute(User $actor, array $data)
    {
        $template = $this->templates->findOrFail($data['template_id']);

        $locationId = $data['location_id'] ?? $template->location_id;

        if ($actor->isEmployee()) {
            if (! $locationId) {
                $locationId = $actor->locations()->value('locations.id');
            }

            if (! $locationId || ! $this->locationAccess->sharesLocation($actor, $locationId)) {
                throw new AuthorizationException('Unauthorized');
            }
            if (! $this->permissions->hasLocationPermission($actor, 'tasks.automation', $locationId)) {
                throw new AuthorizationException('Unauthorized');
            }
        }

        if ($actor->isClient()) {
            throw new AuthorizationException('Unauthorized');
        }

        $dueAt = $data['due_at'] ?? $this->calculateDueAt($template, $data['base_at'] ?? null);
        if (! $dueAt) {
            throw ValidationException::withMessages([
                'due_at' => ['Unable to resolve due_at from template.'],
            ]);
        }

        $assignedUserId = $data['assigned_user_id'] ?? $this->getDefaultAdminId();

        $automation = $this->automations->create([
            'template_id' => $template->id,
            'trigger_event' => $data['trigger_event'] ?? $template->trigger_event,
            'related_type' => $data['related_type'] ?? null,
            'related_id' => $data['related_id'] ?? null,
            'assigned_user_id' => $assignedUserId,
            'due_at' => $dueAt,
            'status' => 'pending',
            'location_id' => $locationId,
        ])->load('template');

        $this->security->log('task.automation.create', RiskLevel::LOW, $actor, $automation, [
            'template_id' => $template->id,
            'trigger_event' => $automation->trigger_event,
        ], [
            'location_id' => $locationId,
            'snapshot_after' => $automation->toArray(),
        ]);

        return $automation;
    }

    private function calculateDueAt(TaskAutomationTemplate $template, ?string $baseAt = null): ?Carbon
    {
        $now = $baseAt ? Carbon::parse($baseAt) : Carbon::now();

        if ($template->schedule_type === 'relative') {
            if (! $template->delay_value || ! $template->delay_unit) {
                return null;
            }

            return match ($template->delay_unit) {
                'minutes' => $now->copy()->addMinutes($template->delay_value),
                'hours' => $now->copy()->addHours($template->delay_value),
                'days' => $now->copy()->addDays($template->delay_value),
                'weeks' => $now->copy()->addWeeks($template->delay_value),
                default => null,
            };
        }

        if ($template->schedule_type === 'fixed') {
            return $template->fixed_at ? Carbon::parse($template->fixed_at) : null;
        }

        if ($template->schedule_type === 'recurring') {
            if (! $template->cron_expression) {
                return null;
            }
            $cron = CronExpression::factory($template->cron_expression);
            return Carbon::instance($cron->getNextRunDate($now, 0, true));
        }

        return null;
    }

    private function getDefaultAdminId(): ?int
    {
        return User::where('type', 'ADMIN')
            ->where('status', 'ACTIVE')
            ->value('id');
    }
}
