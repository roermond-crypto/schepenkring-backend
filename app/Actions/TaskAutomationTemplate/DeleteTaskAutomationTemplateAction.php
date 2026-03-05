<?php

namespace App\Actions\TaskAutomationTemplate;

use App\Models\TaskAutomationTemplate;
use App\Models\User;
use App\Repositories\TaskAutomationTemplateRepository;
use App\Services\LocationAccessService;
use App\Services\PermissionService;
use Illuminate\Auth\Access\AuthorizationException;

class DeleteTaskAutomationTemplateAction
{
    public function __construct(
        private TaskAutomationTemplateRepository $templates,
        private LocationAccessService $locationAccess,
        private PermissionService $permissions
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

        $this->templates->delete($template);
    }
}
