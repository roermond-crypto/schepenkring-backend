<?php

namespace App\Actions\Leads;

use App\Enums\RiskLevel;
use App\Jobs\EnrichLeadJob;
use App\Models\Lead;
use App\Repositories\LeadRepository;
use App\Services\ActionSecurity;

class UpdateLeadFromWidgetAction
{
    public function __construct(
        private LeadRepository $leads,
        private ActionSecurity $security
    ) {
    }

    public function execute(Lead $lead, array $data): Lead
    {
        $before = $lead->toArray();
        $updated = $this->leads->update($lead, $data);

        $this->security->log('lead.updated', RiskLevel::LOW, null, $updated, [], [
            'location_id' => $updated->location_id,
            'snapshot_before' => $before,
            'snapshot_after' => $updated->toArray(),
        ]);

        if ($this->addressChanged($lead, $data)) {
            EnrichLeadJob::dispatch($lead->id);
        }

        return $updated;
    }

    private function addressChanged(Lead $lead, array $data): bool
    {
        $keys = [
            'address_line1',
            'address_line2',
            'city',
            'state',
            'postal_code',
            'country',
        ];

        foreach ($keys as $key) {
            if (array_key_exists($key, $data) && $lead->wasChanged($key)) {
                return true;
            }
        }

        return false;
    }
}
