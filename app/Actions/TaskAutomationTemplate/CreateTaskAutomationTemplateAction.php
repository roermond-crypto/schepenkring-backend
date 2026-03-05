<?php

namespace App\Actions\TaskAutomationTemplate;

use App\Models\User;
use App\Repositories\TaskAutomationTemplateRepository;
use App\Services\LocationAccessService;
use App\Services\PermissionService;
use Illuminate\Auth\Access\AuthorizationException;

class CreateTaskAutomationTemplateAction
{
    public function __construct(
        private TaskAutomationTemplateRepository $templates,
        private LocationAccessService $locationAccess,
        private PermissionService $permissions
    ) {
    }

    public function execute(User $actor, array $data)
    {
        $locationId = $data['location_id'] ?? null;

        if ($actor->isEmployee()) {
            if (! $locationId) {
                $locationId = $actor->locations()->value('locations.id');
            }

            if ($locationId && ! $this->locationAccess->sharesLocation($actor, $locationId)) {
                throw new AuthorizationException('Unauthorized');
            }

            if ($locationId && ! $this->permissions->hasLocationPermission($actor, 'tasks.automation', $locationId)) {
                throw new AuthorizationException('Unauthorized');
            }
        }

        if ($actor->isClient()) {
            throw new AuthorizationException('Unauthorized');
        }

        $data['location_id'] = $locationId;

        return $this->templates->create($data);
    }
}
