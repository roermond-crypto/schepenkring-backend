<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\BuyerCounterOfferMail;
use App\Models\AuditLog;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Offer;
use App\Models\OfferToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class OfferReplyController extends Controller
{
    // ── GET /api/offers/reply?token=xxx ─────────────────────
    // Returns the offer context so the reply page can render it

    public function show(Request $request): JsonResponse
    {
        $tokenRecord = $this->resolveToken($request->input('token'));

        if (! $tokenRecord) {
            return response()->json(['message' => 'Link ongeldig of verlopen.'], 404);
        }

        $offer = $tokenRecord->offer()->with([
            'yacht:id,boat_name,manufacturer,model,main_image,price',
            'location:id,name',
        ])->first();

        $boat = $offer->yacht;
        $boatName = trim(($boat->manufacturer ?? '') . ' ' . ($boat->model ?? '')) ?: $boat->boat_name;

        return response()->json([
            'offer' => [
                'id'          => $offer->id,
                'amount'      => $offer->amount,
                'asking_price' => $offer->asking_price,
                'buyer_name'  => $offer->buyer_name,
                'message'     => $offer->message,
                'status'      => $offer->status,
                'created_at'  => $offer->created_at?->toIso8601String(),
            ],
            'boat' => [
                'id'        => $boat->id,
                'name'      => $boatName,
                'main_image' => $boat->main_image,
            ],
            'location'    => $offer->location ? ['id' => $offer->location->id, 'name' => $offer->location->name] : null,
            'token_action' => $tokenRecord->action,
        ]);
    }

    // ── POST /api/offers/reply ───────────────────────────────
    // Seller responds via email link (token-gated, no auth required)

    public function reply(Request $request): JsonResponse
    {
        $data = $request->validate([
            'token'           => 'required|string|size:64',
            'action'          => 'required|string|in:accept,reject,counter',
            'counter_amount'  => 'nullable|numeric|min:1',
            'counter_message' => 'nullable|string|max:2000',
        ]);

        $tokenRecord = $this->resolveToken($data['token']);

        if (! $tokenRecord) {
            return response()->json(['message' => 'Link ongeldig of verlopen.'], 404);
        }

        // Verify the action matches the token
        if ($tokenRecord->action !== $data['action']) {
            // For counter, allow a generic token or a counter token
            if ($data['action'] !== 'counter') {
                return response()->json(['message' => 'Ongeldige actie voor deze link.'], 422);
            }
        }

        if ($data['action'] === 'counter' && ! $data['counter_amount']) {
            return response()->json(['message' => 'Tegenbod bedrag is vereist.'], 422);
        }

        return DB::transaction(function () use ($data, $tokenRecord, $request) {
            $offer = $tokenRecord->offer()->with(['yacht', 'location'])->firstOrFail();

            // Mark token as used
            $tokenRecord->update([
                'used'       => true,
                'used_at'    => now(),
                'ip_address' => $request->ip(),
            ]);

            // Also mark all tokens for this offer as used (one response allowed)
            OfferToken::where('offer_id', $offer->id)
                ->where('used', false)
                ->update(['used' => true, 'used_at' => now()]);

            match ($data['action']) {
                'accept'  => $this->handleAccept($offer, $request),
                'reject'  => $this->handleReject($offer, $request),
                'counter' => $this->handleCounter($offer, $data, $request),
            };

            $offer->update(['seller_responded_at' => now()]);

            return response()->json([
                'message' => match ($data['action']) {
                    'accept'  => 'Bod geaccepteerd. De koper is op de hoogte gesteld.',
                    'reject'  => 'Bod afgewezen. De koper is op de hoogte gesteld.',
                    'counter' => 'Tegenbod verstuurd naar de koper.',
                },
                'action' => $data['action'],
            ]);
        });
    }

    // ── Private handlers ─────────────────────────────────────

    private function handleAccept(Offer $offer, Request $request): void
    {
        $offer->update(['status' => 'seller_accepted']);

        $this->appendChatMessage(
            $offer,
            "**Bod geaccepteerd** door de makelaar.\n\nOrigineelBod: " . $offer->formattedAmount()
        );

        $this->audit('offer_seller_accepted', $offer, $request);

        // Email buyer if they have an email
        if ($offer->buyer_email) {
            try {
                // Simple acceptance — no dedicated mail yet, notify via chat
            } catch (\Throwable $e) {
                Log::warning('[OfferReplyController] Buyer accept email failed: ' . $e->getMessage());
            }
        }
    }

    private function handleReject(Offer $offer, Request $request): void
    {
        $offer->update(['status' => 'seller_rejected']);

        $this->appendChatMessage(
            $offer,
            "**Bod afgewezen** door de makelaar.\n\nBod: " . $offer->formattedAmount()
        );

        $this->audit('offer_seller_rejected', $offer, $request);
    }

    private function handleCounter(Offer $offer, array $data, Request $request): void
    {
        $counterAmount  = (float) $data['counter_amount'];
        $counterMessage = $data['counter_message'] ?? null;

        $offer->update([
            'status'          => 'seller_countered',
            'counter_amount'  => $counterAmount,
            'counter_message' => $counterMessage,
        ]);

        $formatted = '€ ' . number_format($counterAmount, 0, ',', '.');
        $msg = "**Tegenbod uitgebracht**\n\nTegenbod: {$formatted}";
        if ($counterMessage) {
            $msg .= "\n\nBericht: {$counterMessage}";
        }

        $this->appendChatMessage($offer, $msg);
        $this->audit('offer_seller_countered', $offer, $request, ['counter_amount' => $counterAmount]);

        // Send counter offer email to buyer
        if ($offer->buyer_email) {
            try {
                Mail::to($offer->buyer_email)->send(new BuyerCounterOfferMail($offer, $counterAmount, $counterMessage));
            } catch (\Throwable $e) {
                Log::warning('[OfferReplyController] Buyer counter email failed: ' . $e->getMessage());
            }
        }
    }

    private function appendChatMessage(Offer $offer, string $body): void
    {
        if (! $offer->conversation_id) {
            return;
        }

        try {
            Message::create([
                'conversation_id' => $offer->conversation_id,
                'sender_type'     => 'agent',
                'body'            => $body,
                'delivery_state'  => 'sent',
            ]);
        } catch (\Throwable $e) {
            Log::warning('[OfferReplyController] Chat message failed: ' . $e->getMessage());
        }
    }

    private function resolveToken(mixed $token): ?OfferToken
    {
        if (! $token || strlen((string) $token) !== 64) {
            return null;
        }

        $record = OfferToken::where('token', $token)->first();

        if (! $record || ! $record->isValid()) {
            return null;
        }

        return $record;
    }

    private function audit(string $action, Offer $offer, Request $request, array $extra = []): void
    {
        try {
            AuditLog::create([
                'action'      => $action,
                'risk_level'  => 'low',
                'result'      => 'success',
                'entity_type' => 'offer',
                'entity_id'   => $offer->id,
                'location_id' => $offer->location_id,
                'meta'        => array_merge([
                    'yacht_id'   => $offer->yacht_id,
                    'amount'     => $offer->amount,
                    'buyer_name' => $offer->buyer_name,
                ], $extra),
                'ip_address' => $request->ip(),
                'user_agent' => substr($request->userAgent() ?? '', 0, 500),
            ]);
        } catch (\Throwable $e) {
            Log::warning('[OfferReplyController] Audit failed: ' . $e->getMessage());
        }
    }
}
