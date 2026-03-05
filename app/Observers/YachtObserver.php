<?php

namespace App\Observers;

use App\Models\Yacht;

class YachtObserver
{
    /**
     * Handle the Yacht "created" event.
     */
    public function created(Yacht $yacht): void
    {
        \App\Jobs\MerchantUpsertJob::dispatch($yacht->id);
        app(\App\Services\VideoAutomationService::class)->handleYachtCreated($yacht);
    }

    /**
     * Handle the Yacht "updated" event.
     */
    public function updated(Yacht $yacht): void
    {
        // If it got sold, set out of stock immediately
        if ($yacht->wasChanged('status') && $yacht->status === 'Sold') {
            \App\Jobs\MerchantOutOfStockJob::dispatch($yacht->id);
            return;
        }

        // If it was unpublished (in this DB, status = Draft or Withdrawn), remove it
        if ($yacht->wasChanged('status') && in_array($yacht->status, ['Draft', 'Withdrawn'])) {
            \App\Jobs\MerchantDeleteJob::dispatch($yacht->id, $yacht->google_offer_id);
            return;
        }

        // Otherwise, if any Google-relevant field changed, update it
        $relevantFields = ['price', 'status', 'boat_name', 'model', 'year', 'main_image'];
        if ($yacht->wasChanged($relevantFields)) {
            \App\Jobs\MerchantUpsertJob::dispatch($yacht->id);
        }

        if ($yacht->wasChanged('status')) {
            app(\App\Services\VideoAutomationService::class)->handleYachtPublished($yacht);
        }
    }

    /**
     * Handle the Yacht "deleted" event.
     */
    public function deleted(Yacht $yacht): void
    {
        if ($yacht->google_offer_id) {
            \App\Jobs\MerchantDeleteJob::dispatch($yacht->id, $yacht->google_offer_id);
        }
    }

    /**
     * Handle the Yacht "restored" event.
     */
    public function restored(Yacht $yacht): void
    {
        \App\Jobs\MerchantUpsertJob::dispatch($yacht->id);
    }

    /**
     * Handle the Yacht "force deleted" event.
     */
    public function forceDeleted(Yacht $yacht): void
    {
        if ($yacht->google_offer_id) {
            \App\Jobs\MerchantDeleteJob::dispatch($yacht->id, $yacht->google_offer_id);
        }
    }
}
