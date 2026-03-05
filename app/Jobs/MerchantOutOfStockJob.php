<?php

namespace App\Jobs;

use App\Models\MerchantSyncLog;
use App\Models\Yacht;
use App\Services\GoogleMerchantService;
use App\Services\Merchant\ProductMapper;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Exception;

class MerchantOutOfStockJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $backoff = [60, 180, 600];

    protected int $yachtId;

    public function __construct(int $yachtId)
    {
        $this->yachtId = $yachtId;
    }

    public function handle(GoogleMerchantService $merchantService, ProductMapper $mapper): void
    {
        $yacht = Yacht::find($this->yachtId);

        if (!$yacht || !$yacht->google_offer_id) {
            // Cannot set out of stock if it was never synced
            return;
        }

        // The best and most robust way to set strictly Out of Stock via REST API 
        // without patching is to regenerate the payload, force availability, and upsert.
        // ProductMapper already handles this automatically based on the Yacht's status!
        // We just need to make sure the yacht status is actually sold or deleted.
        
        $payload = $mapper->mapYachtToGoogleProduct($yacht);
        // Force it just in case:
        $payload['availability'] = 'out of stock';

        try {
            $response = $merchantService->upsertProduct($payload);

            $yacht->update([
                'google_status' => 'synced',
                'google_last_sync_at' => now(),
                'google_last_error' => null,
            ]);

            MerchantSyncLog::create([
                'yacht_id' => $yacht->id,
                'action' => 'out_of_stock',
                'status' => 'success',
                'request_payload' => $payload,
                'response_payload' => $response,
            ]);

        } catch (Exception $e) {
            $yacht->update([
                'google_status' => 'error',
                'google_last_error' => 'OOS Error: ' . $e->getMessage(),
            ]);

            MerchantSyncLog::create([
                'yacht_id' => $yacht->id,
                'action' => 'out_of_stock',
                'status' => 'error',
                'error_message' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
