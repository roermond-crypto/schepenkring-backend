<?php

namespace App\Actions\TaskAutomationTemplate;

use App\Models\TaskAutomationTemplate;
use App\Models\User;
use App\Repositories\TaskAutomationTemplateRepository;
use App\Services\LocationAccessService;
use App\Services\PermissionService;
use Illuminate\Auth\Access\AuthorizationException;

class ShowTaskAutomationTemplateAction
{
    public function __construct(
        private TaskAutomationTemplateRepository $templates,
        private LocationAccessService $locationAccess,
        private PermissionService $permissions
    ) {
    }

    public function execute(User $actor, int $id): TaskAutomationTemplate
    {
        $template = $this->templates->findOrFail($id);

        if ($actor->isAdmin()) {
            return $template;
        }

        $locationId = $template->location_id;
        if (! $locationId) {
            throw new AuthorizationException('Unauthorized');
        }

        if (! $this->permissions->hasLocationPermission($actor, 'tasks.automation', $locationId)) {
            throw new AuthorizationException('Unauthorized');
        }

        if (! $this->locationAccess->sharesLocation($actor, $locationId)) {
            throw new AuthorizationException('Unauthorized');
        }

        return $template;
    }
}
