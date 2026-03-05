<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class ReconcileGoogleMerchant extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'merchant:reconcile';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Reconcile yachts with Google Merchant Center (Nightly Sync)';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting Google Merchant Center reconciliation...');

        // 1. Find yachts that should be synced but have a pending or error status
        $pendingOrErrorYachts = \App\Models\Yacht::where('is_published', true)
            ->whereIn('status', ['For Sale', 'For Bid'])
            ->whereIn('google_status', ['pending', 'error'])
            ->get();

        foreach ($pendingOrErrorYachts as $yacht) {
            \App\Jobs\MerchantUpsertJob::dispatch($yacht->id);
            $this->info("Dispatched Upsert for Yacht ID {$yacht->id} (Status: {$yacht->google_status})");
        }

        // 2. Find yachts that haven't been synced in > 30 days (Google expires products after 30 days)
        $staleYachts = \App\Models\Yacht::where('is_published', true)
            ->whereIn('status', ['For Sale', 'For Bid'])
            ->where('google_status', 'synced')
            ->where('google_last_sync_at', '<', now()->subDays(28)) // 28 days to be safe
            ->get();

        foreach ($staleYachts as $yacht) {
            \App\Jobs\MerchantUpsertJob::dispatch($yacht->id);
            $this->info("Dispatched Refresh for Yacht ID {$yacht->id} (Stale since {$yacht->google_last_sync_at})");
        }

        $this->info('Reconciliation complete. Jobs dispatched to queue.');
    }
}
