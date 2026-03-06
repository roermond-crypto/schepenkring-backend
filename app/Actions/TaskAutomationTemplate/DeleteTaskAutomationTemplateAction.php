<?php

namespace App\Actions\TaskAutomationTemplate;

use App\Enums\RiskLevel;
use App\Models\TaskAutomationTemplate;
use App\Models\User;
use App\Repositories\TaskAutomationTemplateRepository;
use App\Services\ActionSecurity;
use App\Services\LocationAccessService;
use App\Services\PermissionService;
use Illuminate\Auth\Access\AuthorizationException;

class DeleteTaskAutomationTemplateAction
{
    public function __construct(
        private TaskAutomationTemplateRepository $templates,
        private LocationAccessService $locationAccess,
        private PermissionService $permissions,
        private ActionSecurity $security
    ) {
    }

    public function execute(User $actor, TaskAutomationTemplate $template): void
    {
        if ($actor->isEmployee()) {
            $locationId = $template->location_id;
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

        $before = $template->toArray();
        $locationId = $template->location_id;

        $this->templates->delete($template);

        $this->security->log('task.automation_template.delete', RiskLevel::LOW, $actor, $template, [], [
            'location_id' => $locationId,
            'snapshot_before' => $before,
        ]);
    }
}
