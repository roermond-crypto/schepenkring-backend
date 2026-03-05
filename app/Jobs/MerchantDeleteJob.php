<?php

namespace App\Jobs;

use App\Models\MerchantSyncLog;
use App\Models\Yacht;
use App\Services\GoogleMerchantService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Exception;

class MerchantDeleteJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $backoff = [60, 180, 600];

    protected int $yachtId;
    protected ?string $offerId;

    public function __construct(int $yachtId, ?string $offerId = null)
    {
        $this->yachtId = $yachtId;
        $this->offerId = $offerId;
    }

    public function handle(GoogleMerchantService $merchantService): void
    {
        $yacht = Yacht::find($this->yachtId);
        
        // Use provided offerId or the yacht's current one
        $offerId = $this->offerId ?? ($yacht ? $yacht->google_offer_id : null);

        if (!$offerId) {
            return;
        }

        try {
            $merchantService->deleteProduct($offerId);

            if ($yacht) {
                $yacht->update([
                    'google_product_id' => null,
                    'google_status' => 'pending',
                ]);
            }

            MerchantSyncLog::create([
                'yacht_id' => $this->yachtId,
                'action' => 'delete',
                'status' => 'success',
            ]);

        } catch (Exception $e) {
            if ($yacht) {
                $yacht->update([
                    'google_status' => 'error',
                    'google_last_error' => 'Delete Error: ' . $e->getMessage(),
                ]);
            }

            MerchantSyncLog::create([
                'yacht_id' => $this->yachtId,
                'action' => 'delete',
                'status' => 'error',
                'error_message' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
