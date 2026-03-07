<?php

namespace App\Http\Controllers\Api;

use App\Actions\Bids\GetBidStateAction;
use App\Actions\Bids\PlaceBidAction;
use App\Actions\Bids\RegisterBidderAction;
use App\Actions\Bids\VerifyBidderAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Bids\BidderRegisterRequest;
use App\Http\Requests\Api\Bids\BidderVerifyRequest;
use App\Http\Requests\Api\Bids\BidPlaceRequest;
use App\Models\Yacht;
use App\Services\BidRulesService;
use App\Services\IdempotencyService;
use Illuminate\Support\Facades\RateLimiter;

class BidWidgetController extends Controller
{
    public function register(
        BidderRegisterRequest $request,
        RegisterBidderAction $action,
        IdempotencyService $idempotency
    ) {
        $request->attributes->set('action_key', 'bids.register');

        $idempotencyResult = $idempotency->begin($request);
        if ($idempotencyResult['status'] === 'missing') {
            $idempotencyResult = null;
        } else {
            if ($idempotencyResult['status'] === 'conflict') {
                return response()->json(['message' => 'Idempotency-Key reuse with different request.'], 409);
            }
            if ($idempotencyResult['status'] === 'processing') {
                return response()->json(['message' => 'Request already in progress.'], 409);
            }
            if ($idempotencyResult['status'] === 'replay') {
                return $idempotencyResult['response'];
            }
        }

        $result = $action->execute($request->validated(), $request);
        $bidder = $result['bidder'];

        if (! empty($result['session'])) {
            $session = $result['session']['session'];
            $response = response()->json([
                'status' => 'verified',
                'bidder' => $bidder,
                'session_token' => $result['session']['token'],
                'session_expires_at' => $session->expires_at?->toISOString(),
            ]);
        } else {
            $response = response()->json([
                'status' => 'verification_sent',
                'bidder' => $bidder,
                'verification_sent_at' => $bidder->verification_sent_at?->toISOString(),
            ], 202);
        }

        if ($idempotencyResult && ! empty($idempotencyResult['record'])) {
            $idempotency->storeResponse($idempotencyResult['record'], $response);
        }

        return $response;
    }

    public function verify(BidderVerifyRequest $request, VerifyBidderAction $action)
    {
        $result = $action->execute($request->validated()['token'], $request);
        $bidder = $result['bidder'];
        $session = $result['session']['session'];

        return response()->json([
            'status' => 'verified',
            'bidder' => $bidder,
            'session_token' => $result['session']['token'],
            'session_expires_at' => $session->expires_at?->toISOString(),
        ]);
    }

    public function state(int $yachtId, GetBidStateAction $action)
    {
        $yacht = Yacht::find($yachtId);
        if (! $yacht) {
            return response()->json(['message' => 'Listing not found.'], 404);
        }

        return response()->json($action->execute($yacht));
    }

    public function place(
        int $yachtId,
        BidPlaceRequest $request,
        PlaceBidAction $action,
        BidRulesService $rules,
        IdempotencyService $idempotency
    ) {
        $bidder = $request->attributes->get('bidder');
        if (! $bidder) {
            return response()->json(['message' => 'Bid session token required.'], 401);
        }

        $request->attributes->set('action_key', 'bids.place');
        $request->merge(['visitor_id' => 'bidder:' . $bidder->id]);

        $idempotencyResult = $idempotency->begin($request);
        if ($idempotencyResult['status'] === 'missing') {
            $idempotencyResult = null;
        } else {
            if ($idempotencyResult['status'] === 'conflict') {
                return response()->json(['message' => 'Idempotency-Key reuse with different request.'], 409);
            }
            if ($idempotencyResult['status'] === 'processing') {
                return response()->json(['message' => 'Request already in progress.'], 409);
            }
            if ($idempotencyResult['status'] === 'replay') {
                return $idempotencyResult['response'];
            }
        }

        $limit = (int) config('bidding.rate_limit_per_minute', 5);
        $rateKey = 'bids:'.$bidder->id;
        if (RateLimiter::tooManyAttempts($rateKey, $limit)) {
            return response()->json(['message' => 'Rate limit exceeded.'], 429);
        }
        RateLimiter::hit($rateKey, 60);

        $yacht = Yacht::find($yachtId);
        if (! $yacht) {
            return response()->json(['message' => 'Listing not found.'], 404);
        }

        $amount = (float) $request->validated()['amount'];
        $bid = $action->execute($bidder, $yacht, $amount, $request);

        $yacht->refresh();
        $response = response()->json([
            'bid' => $bid,
            'current_bid' => $yacht->current_bid !== null ? (float) $yacht->current_bid : null,
            'minimum_next_bid' => $rules->minimumNextBid($yacht),
        ], 201);

        if ($idempotencyResult && ! empty($idempotencyResult['record'])) {
            $idempotency->storeResponse($idempotencyResult['record'], $response);
        }

        return $response;
    }
}
