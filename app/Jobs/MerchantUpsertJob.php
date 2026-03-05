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
use Illuminate\Support\Facades\Log;
use Exception;

class MerchantUpsertJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $backoff = [60, 180, 600]; // Exponential backoff

    protected int $yachtId;

    public function __construct(int $yachtId)
    {
        $this->yachtId = $yachtId;
    }

    public function handle(GoogleMerchantService $merchantService, ProductMapper $mapper): void
    {
        $yacht = Yacht::find($this->yachtId);

        if (!$yacht) {
            return;
        }

        if (!$mapper->isEligible($yacht)) {
            $yacht->update([
                'google_status' => 'not_eligible',
                'google_last_sync_at' => now(),
            ]);
            return;
        }

        $payload = $mapper->mapYachtToGoogleProduct($yacht);

        try {
            $response = $merchantService->upsertProduct($payload);

            $yacht->update([
                'google_status' => 'synced',
                'google_product_id' => $response['id'] ?? null,
                'google_offer_id' => $payload['offerId'],
                'google_last_sync_at' => now(),
                'google_last_error' => null,
            ]);

            MerchantSyncLog::create([
                'yacht_id' => $yacht->id,
                'action' => 'upsert',
                'status' => 'success',
                'request_payload' => $payload,
                'response_payload' => $response,
            ]);

        } catch (Exception $e) {
            $yacht->update([
                'google_status' => 'error',
                'google_last_error' => $e->getMessage(),
            ]);

            MerchantSyncLog::create([
                'yacht_id' => $yacht->id,
                'action' => 'upsert',
                'status' => 'error',
                'request_payload' => $payload,
                'error_message' => $e->getMessage(),
            ]);

            // Rethrow to trigger queue retry
            throw $e;
        }
    }
}
