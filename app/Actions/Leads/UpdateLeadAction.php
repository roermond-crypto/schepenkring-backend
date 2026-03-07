<?php

namespace App\Actions\Leads;

use App\Enums\RiskLevel;
use App\Jobs\EnrichLeadJob;
use App\Models\Lead;
use App\Models\User;
use App\Services\ActionSecurity;
use App\Services\NotificationDispatchService;
use Illuminate\Auth\Access\AuthorizationException;

class UpdateLeadAction
{
    public function __construct(
        private ActionSecurity $security,
        private NotificationDispatchService $notifications
    ) {
    }

    public function execute(User $actor, Lead $lead, array $data): Lead
    {
        if ($actor->isClient()) {
            throw new AuthorizationException('Unauthorized');
        }

        $before = $lead->toArray();
        $originalStatus = $lead->status;
        $originalAssignee = $lead->assigned_employee_id;

        $lead->fill($data);
        $lead->save();

        $assignmentChanged = $lead->wasChanged('assigned_employee_id');
        $statusChanged = $lead->wasChanged('status');
        $addressChanged = $this->addressChanged($lead);

        if ($assignmentChanged && $lead->assigned_employee_id && $lead->status === 'new') {
            $lead->status = 'contacted';
            $lead->save();
            $statusChanged = true;
        }

        if ($lead->wasChanged()) {
            $this->security->log('lead.updated', RiskLevel::LOW, $actor, $lead, [], [
                'location_id' => $lead->location_id,
                'snapshot_before' => $before,
                'snapshot_after' => $lead->toArray(),
            ]);
        }

        if ($statusChanged && $originalStatus !== $lead->status) {
            $this->security->log('lead.status.changed', RiskLevel::LOW, $actor, $lead, [
                'from' => $originalStatus,
                'to' => $lead->status,
            ], [
                'location_id' => $lead->location_id,
            ]);
        }

        if ($assignmentChanged && $originalAssignee !== $lead->assigned_employee_id) {
            $this->security->log('lead.assigned', RiskLevel::LOW, $actor, $lead, [
                'from' => $originalAssignee,
                'to' => $lead->assigned_employee_id,
            ], [
                'location_id' => $lead->location_id,
            ]);

            if ($lead->assignedEmployee) {
                $this->notifications->notifyUser(
                    $lead->assignedEmployee,
                    'info',
                    'Lead assigned',
                    'A lead has been assigned to you.',
                    [
                        'lead_id' => $lead->id,
                        'conversation_id' => $lead->conversation_id,
                    ],
                    null,
                    true,
                    true,
                    $lead->location_id
                );
            }
        }

        if ($addressChanged) {
            EnrichLeadJob::dispatch($lead->id);
        }

        return $lead->refresh();
    }

    private function addressChanged(Lead $lead): bool
    {
        $fields = [
            'address_line1',
            'address_line2',
            'city',
            'state',
            'postal_code',
            'country',
        ];

        foreach ($fields as $field) {
            if ($lead->wasChanged($field)) {
                return true;
            }
        }

        return false;
    }
}
