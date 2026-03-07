<?php

namespace App\Jobs;

use App\Enums\RiskLevel;
use App\Models\Lead;
use App\Services\ActionSecurity;
use App\Services\LeadGeocodingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class EnrichLeadJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 60;

    public function __construct(private int $leadId)
    {
    }

    public function handle(LeadGeocodingService $geocoder, ActionSecurity $security): void
    {
        $lead = Lead::find($this->leadId);
        if (! $lead) {
            return;
        }

        $result = $geocoder->geocode($lead);
        if (isset($result['error'])) {
            return;
        }

        $before = $lead->toArray();
        $geocoder->applyToLead($lead, $result);

        $security->log('lead.address.enriched', RiskLevel::LOW, null, $lead, [], [
            'location_id' => $lead->location_id,
            'snapshot_before' => $before,
            'snapshot_after' => $lead->toArray(),
        ]);
    }
}
