<?php

namespace App\Actions\TaskAutomationTemplate;

use App\Enums\RiskLevel;
use App\Models\User;
use App\Repositories\TaskAutomationTemplateRepository;
use App\Services\ActionSecurity;
use App\Services\LocationAccessService;
use App\Services\PermissionService;
use Illuminate\Auth\Access\AuthorizationException;

class CreateTaskAutomationTemplateAction
{
    public function __construct(
        private TaskAutomationTemplateRepository $templates,
        private LocationAccessService $locationAccess,
        private PermissionService $permissions,
        private ActionSecurity $security
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

        $items = $data['items'] ?? [];
        unset($data['location_id'], $data['items']);

        $data['location_id'] = $locationId;

        $template = $this->templates->create($data);

        // Sync template items if provided
        if (! empty($items) && is_array($items)) {
            foreach ($items as $index => $item) {
                $template->items()->create([
                    'title' => $item['title'] ?? "Task item " . ($index + 1),
                    'description' => $item['description'] ?? null,
                    'priority' => $item['priority'] ?? $template->priority ?? 'Medium',
                    'position' => $item['position'] ?? $index,
                ]);
            }
        }

        $template->load('items');

        $this->security->log('task.automation_template.create', RiskLevel::LOW, $actor, $template, [], [
            'location_id' => $locationId,
            'snapshot_after' => $template->toArray(),
        ]);

        return $template;
    }
}
