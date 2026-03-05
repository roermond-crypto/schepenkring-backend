<?php

namespace App\Actions\TaskAutomationTemplate;

use App\Models\TaskAutomationTemplate;
use App\Models\User;
use App\Repositories\TaskAutomationTemplateRepository;
use App\Services\LocationAccessService;
use App\Services\PermissionService;
use Illuminate\Auth\Access\AuthorizationException;

class UpdateTaskAutomationTemplateAction
{
    public function __construct(
        private TaskAutomationTemplateRepository $templates,
        private LocationAccessService $locationAccess,
        private PermissionService $permissions
    ) {
    }

    public function execute(User $actor, TaskAutomationTemplate $template, array $data)
    {
        $locationId = $data['location_id'] ?? $template->location_id;

        if ($actor->isEmployee()) {
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

        $data['location_id'] = $locationId;

        return $this->templates->update($template, $data);
    }
}
