<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\SignRequest;
use App\Services\SignhostService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class SignhostMonitorController extends Controller
{
    /**
     * Paginated list of all sign requests for the admin monitoring dashboard.
     * Supports ?status=waiting|completed|expired|failed|needs_attention filter.
     */
    public function index(Request $request): \Illuminate\Http\JsonResponse
    {
        $filter = $request->get('status');

        $query = SignRequest::query()
            ->with(['documents'])
            ->orderByDesc('updated_at');

        switch ($filter) {
            case 'waiting':
                $query->whereIn('status', ['DRAFT', 'SENT', 'VIEWED', 'REQUESTED']);
                break;
            case 'completed':
                $query->where('status', 'SIGNED');
                break;
            case 'expired':
                $query->where('status', 'EXPIRED');
                break;
            case 'failed':
                $query->where('status', 'FAILED');
                break;
            case 'needs_attention':
                $query->where(function ($q) {
                    $q->where('webhook_failed', true)
                      ->orWhere(function ($q2) {
                          // Active transactions with no check in the last 24 h
                          $q2->whereIn('status', ['SENT', 'VIEWED'])
                             ->where(function ($q3) {
                                 $q3->whereNull('signhost_last_checked_at')
                                    ->orWhere('signhost_last_checked_at', '<', now()->subDay());
                             });
                      })
                      ->orWhere(function ($q4) {
                          // Transactions past expiry still marked SENT
                          $q4->where('status', 'SENT')
                             ->whereNotNull('signhost_expires_at')
                             ->where('signhost_expires_at', '<', now());
                      });
                });
                break;
        }

        $items = $query->paginate(50);

        $data = $items->getCollection()->map(function (SignRequest $sr) {
            $metadata = $sr->metadata ?? [];
            return [
                'id'                       => $sr->id,
                'entity_type'              => $sr->entity_type,
                'entity_id'                => $sr->entity_id,
                'status'                   => $sr->status,
                'provider'                 => $sr->provider,
                'signhost_transaction_id'  => $sr->signhost_transaction_id,
                'signhost_buyer_link'      => $sr->signhost_buyer_link,
                'signhost_seller_link'     => $sr->signhost_seller_link,
                'signhost_expires_at'      => $sr->signhost_expires_at?->toIso8601String(),
                'signhost_last_checked_at' => $sr->signhost_last_checked_at?->toIso8601String(),
                'signhost_created_at'      => $sr->signhost_created_at?->toIso8601String(),
                'buyer_signed_at'          => $sr->buyer_signed_at?->toIso8601String(),
                'seller_signed_at'         => $sr->seller_signed_at?->toIso8601String(),
                'completed_at'             => $sr->completed_at?->toIso8601String(),
                'signed_pdf_path'          => $sr->signed_pdf_path,
                'signed_pdf_hash'          => $sr->signed_pdf_hash,
                'webhook_failed'           => $sr->webhook_failed,
                'webhook_error'            => $sr->webhook_error,
                'last_webhook_received_at' => $metadata['webhook_received_at'] ?? null,
                'location_id'              => $sr->location_id,
                'created_at'               => $sr->created_at?->toIso8601String(),
                'updated_at'               => $sr->updated_at?->toIso8601String(),
                'document_count'           => $sr->documents->count(),
                'needs_attention'          => $sr->webhook_failed
                    || ($sr->status === 'SENT' && $sr->signhost_expires_at?->isPast())
                    || ($sr->status === 'SENT' && ($sr->signhost_last_checked_at === null || $sr->signhost_last_checked_at->lt(now()->subDay()))),
            ];
        });

        return response()->json([
            'data'         => $data,
            'current_page' => $items->currentPage(),
            'last_page'    => $items->lastPage(),
            'total'        => $items->total(),
            'stats'        => [
                'waiting'         => SignRequest::whereIn('status', ['DRAFT', 'SENT', 'VIEWED'])->count(),
                'completed'       => SignRequest::where('status', 'SIGNED')->count(),
                'expired'         => SignRequest::where('status', 'EXPIRED')->count(),
                'failed'          => SignRequest::where('status', 'FAILED')->count(),
                'needs_attention' => SignRequest::where('webhook_failed', true)
                    ->orWhere(function ($q) {
                        $q->whereIn('status', ['SENT', 'VIEWED'])
                          ->whereNotNull('signhost_expires_at')
                          ->where('signhost_expires_at', '<', now());
                    })->count(),
            ],
        ]);
    }

    /**
     * Resync a specific sign request from the Signhost API (admin).
     */
    public function resync(SignRequest $signRequest, SignhostService $signhost): \Illuminate\Http\JsonResponse
    {
        if (! $signRequest->signhost_transaction_id) {
            return response()->json(['error' => 'No Signhost transaction on this sign request'], 422);
        }

        try {
            $result = $signhost->resyncTransaction($signRequest->signhost_transaction_id);
        } catch (\Throwable $e) {
            Log::warning('SignhostMonitorController: resync failed', ['id' => $signRequest->id, 'error' => $e->getMessage()]);
            return response()->json(['error' => 'Signhost API error: ' . $e->getMessage()], 502);
        }

        $transaction = $result['transaction'];
        $rawStatus   = $result['status'];
        $internalStatus = match (strtoupper((string) $rawStatus)) {
            'WAITING_FOR_ALL', 'SIGNING', 'SENT', 'VIEWED' => 'SENT',
            'SIGNED'   => 'SIGNED',
            'EXPIRED'  => 'EXPIRED',
            'CANCELLED', 'REJECTED', 'DECLINED', 'FAILED' => 'FAILED',
            default    => $signRequest->status,
        };

        $signRequest->update([
            'status'                   => $internalStatus,
            'signhost_buyer_link'      => $result['buyer_url']  ?? $signRequest->signhost_buyer_link,
            'signhost_seller_link'     => $result['seller_url'] ?? $signRequest->signhost_seller_link,
            'signhost_expires_at'      => $result['expires_at'] ? now()->parse($result['expires_at']) : $signRequest->signhost_expires_at,
            'signhost_last_checked_at' => now(),
            'signhost_raw_response'    => $transaction,
            'webhook_failed'           => false,
            'webhook_error'            => null,
        ]);

        return response()->json([
            'sign_request'    => $signRequest->fresh(),
            'signhost_status' => $rawStatus,
        ]);
    }
}
