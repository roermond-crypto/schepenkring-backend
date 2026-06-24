<?php

namespace App\Observers;

use App\Models\Location;
use App\Services\EmailTemplateAutoCreateService;
use Illuminate\Support\Facades\Log;

class LocationObserver
{
    /**
     * Handle the Location "created" event.
     * Auto-create all default email templates for the new location.
     */
    public function created(Location $location): void
    {
        try {
            app(EmailTemplateAutoCreateService::class)->createForLocation($location);
        } catch (\Throwable $e) {
            Log::error('[LocationObserver] Failed to auto-create email templates for location', [
                'location_id' => $location->id,
                'error'       => $e->getMessage(),
                'trace'       => $e->getTraceAsString(),
            ]);
        }
    }
}
