<?php

namespace App\Http\Controllers\Api\RetellTool;

use App\Http\Controllers\Controller;
use App\Models\Contract;
use App\Models\Deal;
use App\Models\Location;
use App\Models\OwnerBid;
use App\Models\SellerOnboardingPayment;
use App\Models\User;
use App\Models\Yacht;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Read-only context/status lookups Retell agents call mid-conversation.
 * Every response is a small, curated field set — never a raw model dump —
 * so a prompt-injected or misbehaving agent can't fish for data beyond
 * what the conversation actually needs.
 */
class RetellToolContextController extends Controller
{
    use RetellToolHelpers;

    public function userContext(Request $request): JsonResponse
    {
        $validated = $request->validate(['user_id' => 'required|integer']);

        return $this->safe(function () use ($validated, $request) {
            $user = User::find($validated['user_id']);
            if (! $user) {
                return response()->json(['found' => false]);
            }

            $this->assertLocationScope($user->client_location_id, $this->resolveCallSession($request));

            return response()->json([
                'found' => true,
                'name' => $user->name,
                'type' => $user->type?->value,
                'email' => $user->email,
                'phone' => $user->phone,
                'locale' => $user->locale,
            ]);
        });
    }

    public function sellerContext(Request $request): JsonResponse
    {
        $validated = $request->validate(['seller_id' => 'required|integer']);

        return $this->safe(function () use ($validated, $request) {
            $seller = User::with('sellerProfile')->find($validated['seller_id']);
            if (! $seller) {
                return response()->json(['found' => false]);
            }

            $this->assertLocationScope($seller->client_location_id, $this->resolveCallSession($request));

            $yachts = Yacht::where('user_id', $seller->id)
                ->orWhere('seller_id', $seller->id)
                ->get(['id', 'boat_name', 'manufacturer', 'model', 'status', 'completeness_score']);

            return response()->json([
                'found' => true,
                'name' => $seller->sellerProfile?->full_name ?? $seller->name,
                'email' => $seller->email,
                'phone' => $seller->phone,
                'locale' => $seller->locale,
                'yachts' => $yachts->map(fn ($y) => [
                    'yacht_id' => $y->id,
                    'boat_name' => $y->boat_name,
                    'brand' => $y->manufacturer,
                    'model' => $y->model,
                    'status' => $y->status,
                    'completeness_score' => $y->completeness_score,
                ]),
            ]);
        });
    }

    public function buyerContext(Request $request): JsonResponse
    {
        $validated = $request->validate(['buyer_id' => 'required|integer']);

        return $this->safe(function () use ($validated, $request) {
            $buyer = User::with('buyerProfile')->find($validated['buyer_id']);
            if (! $buyer) {
                return response()->json(['found' => false]);
            }

            $this->assertLocationScope($buyer->client_location_id, $this->resolveCallSession($request));

            return response()->json([
                'found' => true,
                'name' => $buyer->buyerProfile?->full_name ?? $buyer->name,
                'email' => $buyer->email,
                'phone' => $buyer->phone,
                'locale' => $buyer->locale,
                'verification_status' => $buyer->buyerVerification?->status,
            ]);
        });
    }

    public function yachtContext(Request $request): JsonResponse
    {
        $validated = $request->validate(['yacht_id' => 'required|integer']);

        return $this->safe(function () use ($validated, $request) {
            $yacht = Yacht::find($validated['yacht_id']);
            if (! $yacht) {
                return response()->json(['found' => false]);
            }

            $this->assertLocationScope($yacht->ref_harbor_id ?? $yacht->location_id, $this->resolveCallSession($request));

            return response()->json([
                'found' => true,
                'boat_name' => $yacht->boat_name,
                'brand' => $yacht->manufacturer,
                'model' => $yacht->model,
                'year' => $yacht->year,
                'price' => $yacht->price,
                'status' => $yacht->status,
                // Never let the agent invent specs it doesn't have — this is
                // the entire admin-approved field set it's allowed to quote.
                'short_description' => $yacht->short_description_nl,
            ]);
        });
    }

    public function locationContext(Request $request): JsonResponse
    {
        $validated = $request->validate(['location_id' => 'required|integer']);

        $location = Location::find($validated['location_id']);
        if (! $location) {
            return response()->json(['found' => false]);
        }

        $address = implode(', ', array_filter([
            trim($location->address_line1.' '.$location->street_number),
            $location->postal_code,
            $location->city,
        ]));

        return response()->json([
            'found' => true,
            'name' => $location->name,
            'phone' => $location->phone,
            'email' => $location->email,
            'address' => $address !== '' ? $address : null,
        ]);
    }

    public function dealStatus(Request $request): JsonResponse
    {
        $validated = $request->validate(['deal_id' => 'required|integer']);

        return $this->safe(function () use ($validated, $request) {
            $deal = Deal::find($validated['deal_id']);
            if (! $deal) {
                return response()->json(['found' => false]);
            }

            $this->assertLocationScope($deal->yacht?->ref_harbor_id, $this->resolveCallSession($request));

            return response()->json([
                'found' => true,
                'status' => $deal->status,
                'agreed_amount' => $deal->agreed_amount,
            ]);
        });
    }

    public function bidStatus(Request $request): JsonResponse
    {
        $validated = $request->validate(['bid_id' => 'required|integer']);

        return $this->safe(function () use ($validated, $request) {
            $bid = OwnerBid::find($validated['bid_id']);
            if (! $bid) {
                return response()->json(['found' => false]);
            }

            $this->assertLocationScope($bid->location_id, $this->resolveCallSession($request));

            return response()->json([
                'found' => true,
                'status' => $bid->status,
                'amount' => $bid->amount,
                'type' => $bid->type,
            ]);
        });
    }

    public function contractStatus(Request $request): JsonResponse
    {
        $validated = $request->validate(['contract_id' => 'nullable|integer', 'yacht_id' => 'nullable|integer']);
        if (empty($validated['contract_id']) && empty($validated['yacht_id'])) {
            return response()->json(['error' => 'contract_id or yacht_id is required'], 422);
        }

        return $this->safe(function () use ($validated) {
            $contract = ! empty($validated['contract_id'])
                ? Contract::find($validated['contract_id'])
                : Contract::where('boat_id', $validated['yacht_id'])->latest('id')->first();

            if (! $contract) {
                return response()->json(['found' => false]);
            }

            return response()->json([
                'found' => true,
                'status' => $contract->status,
                'signed_by_buyer' => $contract->signed_by_buyer,
                'signed_by_seller' => $contract->signed_by_seller,
            ]);
        });
    }

    public function paymentStatus(Request $request): JsonResponse
    {
        $validated = $request->validate(['user_id' => 'required|integer']);

        return $this->safe(function () use ($validated) {
            $payment = SellerOnboardingPayment::where('user_id', $validated['user_id'])->latest('id')->first();
            if (! $payment) {
                return response()->json(['found' => false]);
            }

            return response()->json([
                'found' => true,
                'status' => $payment->status,
                'type' => $payment->type,
                'amount' => $payment->amount_value,
                'currency' => $payment->amount_currency,
                'paid_at' => $payment->paid_at?->toIso8601String(),
            ]);
        });
    }
}
