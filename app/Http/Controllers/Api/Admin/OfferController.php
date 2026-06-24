<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Mail\SellerOfferNotificationMail;
use App\Models\AuditLog;
use App\Models\Offer;
use App\Models\OfferToken;
use App\Models\User;
use App\Models\Yacht;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class OfferController extends Controller
{
    // ── GET /api/admin/offers ────────────────────────────────

    public function index(Request $request): JsonResponse
    {
        $query = Offer::with(['yacht:id,boat_name,manufacturer,model,main_image', 'location:id,name', 'seller:id,name,email'])
            ->orderByDesc('created_at');

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }
        if ($locationId = $request->integer('location_id')) {
            $query->where('location_id', $locationId);
        }
        if ($yachtId = $request->integer('yacht_id')) {
            $query->where('yacht_id', $yachtId);
        }
        if ($sellerId = $request->integer('seller_id')) {
            $query->where('seller_id', $sellerId);
        }
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('buyer_name', 'like', "%{$search}%")
                  ->orWhere('buyer_email', 'like', "%{$search}%")
                  ->orWhere('buyer_phone', 'like', "%{$search}%");
            });
        }

        $perPage = (int) $request->input('per_page', 30);
        $offers  = $query->paginate($perPage);

        return response()->json([
            'data'  => $offers->items(),
            'total' => $offers->total(),
            'page'  => $offers->currentPage(),
            'last_page' => $offers->lastPage(),
        ]);
    }

    // ── GET /api/admin/offers/{offer} ────────────────────────

    public function show(Request $request, Offer $offer): JsonResponse
    {
        $offer->load([
            'yacht:id,boat_name,manufacturer,model,main_image,price,minimum_offer_amount',
            'location:id,name',
            'seller:id,name,email',
            'buyer:id,name,email',
            'lead:id,status',
            'conversation:id,status',
            'children',
            'tokens',
        ]);

        return response()->json(['offer' => $this->serializeOffer($offer)]);
    }

    // ── POST /api/admin/offers ───────────────────────────────
    // Create offer manually (e.g. phone call)

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'yacht_id'    => 'required|integer|exists:yachts,id',
            'location_id' => 'nullable|integer|exists:locations,id',
            'amount'      => 'required|numeric|min:1',
            'buyer_name'  => 'required|string|max:255',
            'buyer_email' => 'nullable|email|max:255',
            'buyer_phone' => 'nullable|string|max:50',
            'message'     => 'nullable|string|max:2000',
            'source'      => 'nullable|string|in:widget,admin,phone,email',
        ]);

        return DB::transaction(function () use ($data, $request) {
            $yacht = Yacht::findOrFail($data['yacht_id']);

            $offer = Offer::create([
                'yacht_id'    => $yacht->id,
                'location_id' => $data['location_id'] ?? $yacht->location_id,
                'seller_id'   => $yacht->seller_id ?? $yacht->user_id,
                'amount'      => $data['amount'],
                'asking_price' => $yacht->price,
                'minimum_amount' => $yacht->minimum_offer_amount,
                'buyer_name'  => $data['buyer_name'],
                'buyer_email' => $data['buyer_email'] ?? null,
                'buyer_phone' => $data['buyer_phone'] ?? null,
                'message'     => $data['message'] ?? null,
                'status'      => 'new',
                'source'      => $data['source'] ?? 'admin',
                'below_minimum' => $yacht->minimum_offer_amount && $data['amount'] < $yacht->minimum_offer_amount,
                'ip_address'  => $request->ip(),
            ]);

            $this->audit('offer_created_admin', $offer, $request);

            return response()->json(['offer' => $this->serializeOffer($offer)], 201);
        });
    }

    // ── PATCH /api/admin/offers/{offer}/status ───────────────

    public function updateStatus(Request $request, Offer $offer): JsonResponse
    {
        $data = $request->validate([
            'status' => 'required|string|in:new,sent_to_seller,seller_accepted,seller_rejected,seller_countered,withdrawn,completed',
        ]);

        $old = $offer->status;
        $offer->update(['status' => $data['status']]);

        if ($data['status'] === 'seller_responded_at' && ! $offer->seller_responded_at) {
            $offer->update(['seller_responded_at' => now()]);
        }

        $this->audit("offer_status_changed_{$old}_to_{$data['status']}", $offer, $request);

        return response()->json(['offer' => $this->serializeOffer($offer->fresh())]);
    }

    // ── POST /api/admin/offers/{offer}/notify-seller ─────────

    public function notifySeller(Request $request, Offer $offer): JsonResponse
    {
        $offer->load('yacht.seller', 'yacht', 'location');

        $seller = $offer->seller ?? $offer->yacht?->seller;

        if (! $seller || ! $seller->email) {
            return response()->json(['message' => 'Geen verkoper of e-mail gevonden.'], 422);
        }

        try {
            [$acceptToken, $rejectToken] = $this->generateResponseTokens($offer);

            Mail::to($seller->email)->send(new SellerOfferNotificationMail($offer, $seller, $acceptToken, $rejectToken));

            $offer->update([
                'status'             => 'sent_to_seller',
                'seller_notified'    => true,
                'seller_notified_at' => now(),
            ]);

            $this->audit('offer_seller_notified', $offer, $request);

            return response()->json(['message' => "E-mail verstuurd naar {$seller->email}."]);
        } catch (\Throwable $e) {
            Log::error('[OfferController] Seller notify failed: ' . $e->getMessage());
            return response()->json(['message' => 'Verzenden mislukt: ' . $e->getMessage()], 500);
        }
    }

    // ── DELETE /api/admin/offers/{offer} ─────────────────────

    public function destroy(Request $request, Offer $offer): JsonResponse
    {
        $this->audit('offer_deleted', $offer, $request);
        $offer->delete();
        return response()->json(['message' => 'Bod verwijderd.']);
    }

    // ── GET /api/admin/yachts/{yacht}/offers ─────────────────

    public function byYacht(Request $request, Yacht $yacht): JsonResponse
    {
        $offers = Offer::with(['seller:id,name,email'])
            ->where('yacht_id', $yacht->id)
            ->orderByDesc('created_at')
            ->get();

        return response()->json(['offers' => $offers->map(fn ($o) => $this->serializeOffer($o))]);
    }

    // ── GET /api/admin/sellers/{seller}/offers ────────────────

    public function bySeller(Request $request, User $seller): JsonResponse
    {
        $offers = Offer::with(['yacht:id,boat_name,manufacturer,model,main_image'])
            ->where('seller_id', $seller->id)
            ->orderByDesc('created_at')
            ->get();

        return response()->json(['offers' => $offers->map(fn ($o) => $this->serializeOffer($o))]);
    }

    // ── Helpers ─────────────────────────────────────────────

    private function generateResponseTokens(Offer $offer): array
    {
        $expiresAt = now()->addDays(7);

        $accept = OfferToken::create([
            'offer_id'   => $offer->id,
            'token'      => Str::random(64),
            'action'     => 'accept',
            'expires_at' => $expiresAt,
        ]);

        $reject = OfferToken::create([
            'offer_id'   => $offer->id,
            'token'      => Str::random(64),
            'action'     => 'reject',
            'expires_at' => $expiresAt,
        ]);

        // Also generate a counter token (seller fills in amount via form)
        OfferToken::create([
            'offer_id'   => $offer->id,
            'token'      => Str::random(64),
            'action'     => 'counter',
            'expires_at' => $expiresAt,
        ]);

        return [$accept, $reject];
    }

    private function serializeOffer(Offer $offer): array
    {
        return [
            'id'                  => $offer->id,
            'yacht_id'            => $offer->yacht_id,
            'location_id'         => $offer->location_id,
            'seller_id'           => $offer->seller_id,
            'buyer_id'            => $offer->buyer_id,
            'lead_id'             => $offer->lead_id,
            'conversation_id'     => $offer->conversation_id,
            'buyer_name'          => $offer->buyer_name,
            'buyer_email'         => $offer->buyer_email,
            'buyer_phone'         => $offer->buyer_phone,
            'amount'              => $offer->amount,
            'asking_price'        => $offer->asking_price,
            'minimum_amount'      => $offer->minimum_amount,
            'status'              => $offer->status,
            'counter_amount'      => $offer->counter_amount,
            'counter_message'     => $offer->counter_message,
            'message'             => $offer->message,
            'below_minimum'       => $offer->below_minimum,
            'seller_notified'     => $offer->seller_notified,
            'seller_notified_at'  => $offer->seller_notified_at?->toIso8601String(),
            'seller_responded_at' => $offer->seller_responded_at?->toIso8601String(),
            'source'              => $offer->source,
            'created_at'          => $offer->created_at?->toIso8601String(),
            'yacht'               => $offer->relationLoaded('yacht') ? $offer->yacht : null,
            'location'            => $offer->relationLoaded('location') ? $offer->location : null,
            'seller'              => $offer->relationLoaded('seller') ? $offer->seller : null,
        ];
    }

    private function audit(string $action, Offer $offer, Request $request): void
    {
        try {
            AuditLog::create([
                'action'      => $action,
                'risk_level'  => 'low',
                'result'      => 'success',
                'actor_id'    => $request->user()?->id,
                'entity_type' => 'offer',
                'entity_id'   => $offer->id,
                'location_id' => $offer->location_id,
                'meta'        => [
                    'yacht_id'   => $offer->yacht_id,
                    'amount'     => $offer->amount,
                    'buyer_name' => $offer->buyer_name,
                    'status'     => $offer->status,
                ],
                'ip_address' => $request->ip(),
                'user_agent' => substr($request->userAgent() ?? '', 0, 500),
            ]);
        } catch (\Throwable $e) {
            Log::warning('[OfferController] Audit failed: ' . $e->getMessage());
        }
    }
}
