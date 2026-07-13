<?php

namespace App\Http\Controllers\Api;

use App\Enums\UserType;
use App\Http\Controllers\Controller;
use App\Mail\SellerOfferNotificationMail;
use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Lead;
use App\Models\Location;
use App\Models\Message;
use App\Models\Offer;
use App\Models\OfferToken;
use App\Models\User;
use App\Models\Yacht;
use App\Services\ChatContactService;
use App\Services\ChatConversationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class WidgetLeadController extends Controller
{
    public function __construct(
        private ChatConversationService $chatService,
        private ChatContactService $contactService,
    ) {}

    // ── GET /api/widget/context ─────────────────────────────

    public function context(Request $request): JsonResponse
    {
        $request->validate([
            'boat_id' => 'nullable|integer',
            'location_id' => 'nullable|integer',
        ]);

        $boat = null;
        $location = null;
        $locationId = $request->integer('location_id') ?: null;

        if ($boatId = $request->integer('boat_id')) {
            $boat = Yacht::with('location')
                ->where('id', $boatId)
                ->orWhere('yachtshift_id', $boatId)
                ->first();
            if ($boat && $boat->location_id) {
                $locationId = $boat->location_id;
            }
        }

        if ($locationId) {
            $location = Location::find($locationId);
        }

        $this->trackEvent('widget_loaded', null, $locationId, $boat?->id, $request);

        return response()->json([
            'boat' => $boat ? [
                'id'                   => $boat->id,
                'name'                 => trim(($boat->manufacturer ?? $boat->make ?? '') . ' ' . ($boat->model ?? '')),
                'brand'                => $boat->manufacturer ?? $boat->make ?? null,
                'model'                => $boat->model ?? null,
                'url'                  => $request->input('source_url'),
                'seller_id'            => $boat->user_id ?? null,
                'minimum_offer_amount' => $boat->minimum_offer_amount ?? null,
            ] : null,
            'location' => $location ? [
                'id'   => $location->id,
                'name' => $location->name,
            ] : null,
        ]);
    }

    // ── POST /api/widget/plan-viewing ───────────────────────

    public function planViewing(Request $request): JsonResponse
    {
        $data = $request->validate([
            'boat_id'        => 'required|integer|exists:yachts,id',
            'location_id'    => 'nullable|integer|exists:locations,id',
            'preferred_date' => 'required|date',
            'preferred_time' => 'required|string|max:10',
            'name'           => 'required|string|max:255',
            'phone'          => 'required|string|max:50',
            'email'          => 'nullable|email|max:255',
            'message'        => 'nullable|string|max:2000',
            'source_url'     => 'nullable|string',
        ]);

        return DB::transaction(function () use ($data, $request) {
            [$boat, $locationId] = $this->resolveBoatAndLocation($data);

            $contact = $this->resolveContact($data, $request);

            $body = $this->buildViewingMessage($data, $boat);
            $conversation = $this->createConversation($contact, $boat, $locationId, $data, 'plan_viewing', $body, $request);
            $lead = $this->ensureLead($conversation, $contact, $boat, $locationId, $data, 'plan_viewing');

            $booking = Booking::create([
                'location_id'    => $locationId,
                'boat_id'        => $boat->id,
                'type'           => 'viewing',
                'status'         => 'requested',
                'date'           => $data['preferred_date'],
                'time'           => $data['preferred_time'] . ':00',
                'preferred_time' => $data['preferred_time'],
                'name'           => $data['name'],
                'email'          => $data['email'] ?? null,
                'phone'          => $data['phone'],
                'source'         => 'widget',
                'notes'          => $data['message'] ?? null,
                'chat_id'        => $conversation->id,
                'lead_id'        => $lead->id,
            ]);

            $this->logTimeline('viewing_requested', $conversation, $boat, $locationId, $contact, [
                'booking_id'     => $booking->id,
                'preferred_date' => $data['preferred_date'],
                'preferred_time' => $data['preferred_time'],
            ], $request);

            $this->trackEvent('widget_plan_viewing_submitted', 'plan_viewing', $locationId, $boat->id, $request);
            $this->notifyLocation($locationId, 'Bezichtiging aangevraagd', $data['name'], $boat, $conversation);

            return response()->json([
                'success'         => true,
                'flow'            => 'plan_viewing',
                'conversation_id' => $conversation->id,
                'lead_id'         => $lead->id,
                'booking_id'      => $booking->id,
            ], 201);
        });
    }

    // ── POST /api/widget/offer ──────────────────────────────

    public function offer(Request $request): JsonResponse
    {
        $data = $request->validate([
            'boat_id'      => 'required|integer|exists:yachts,id',
            'location_id'  => 'nullable|integer|exists:locations,id',
            'offer_amount' => 'required|numeric|min:1',
            'name'         => 'required|string|max:255',
            'phone'        => 'required|string|max:50',
            'email'        => 'required|email|max:255',
            'message'      => 'nullable|string|max:2000',
            'source_url'   => 'nullable|string',
        ]);

        return DB::transaction(function () use ($data, $request) {
            [$boat, $locationId] = $this->resolveBoatAndLocation($data);

            $contact = $this->resolveContact($data, $request);

            $offerAmount = (float) $data['offer_amount'];
            $belowMin = $boat->minimum_offer_amount && $offerAmount < $boat->minimum_offer_amount;

            $body = $this->buildOfferMessage($data, $boat);
            $conversation = $this->createConversation($contact, $boat, $locationId, $data, 'offer', $body, $request);
            $conversation->widget_offer_amount = $offerAmount;
            $conversation->save();

            $lead = $this->ensureLead($conversation, $contact, $boat, $locationId, $data, 'offer');

            // Create the formal Offer record
            $offer = Offer::create([
                'yacht_id'       => $boat->id,
                'location_id'    => $locationId,
                'seller_id'      => $this->resolveSellerId($boat, $locationId),
                'amount'         => $offerAmount,
                'asking_price'   => $boat->price,
                'minimum_amount' => $boat->minimum_offer_amount,
                'buyer_name'     => $data['name'],
                'buyer_email'    => $data['email'] ?? null,
                'buyer_phone'    => $data['phone'],
                'message'        => $data['message'] ?? null,
                'status'         => 'new',
                'source'         => 'widget',
                'source_url'     => $data['source_url'] ?? null,
                'below_minimum'  => $belowMin,
                'lead_id'        => $lead->id,
                'conversation_id' => $conversation->id,
                'ip_address'     => $request->ip(),
                'user_agent'     => substr($request->userAgent() ?? '', 0, 250),
            ]);

            // Auto-notify seller if email notifications enabled
            if ($boat->seller_email_notifications ?? true) {
                $seller = $offer->seller_id
                    ? User::find($offer->seller_id)
                    : null;

                if ($seller && $seller->email) {
                    try {
                        $expiresAt = now()->addDays(7);
                        $acceptToken = OfferToken::create([
                            'offer_id' => $offer->id, 'token' => \Illuminate\Support\Str::random(64),
                            'action' => 'accept', 'expires_at' => $expiresAt,
                        ]);
                        $rejectToken = OfferToken::create([
                            'offer_id' => $offer->id, 'token' => \Illuminate\Support\Str::random(64),
                            'action' => 'reject', 'expires_at' => $expiresAt,
                        ]);
                        OfferToken::create([
                            'offer_id' => $offer->id, 'token' => \Illuminate\Support\Str::random(64),
                            'action' => 'counter', 'expires_at' => $expiresAt,
                        ]);

                        Mail::to($seller->email)->send(new SellerOfferNotificationMail($offer, $seller, $acceptToken, $rejectToken));

                        $offer->update([
                            'status' => 'sent_to_seller',
                            'seller_notified' => true,
                            'seller_notified_at' => now(),
                        ]);
                    } catch (\Throwable $e) {
                        Log::warning('[WidgetLeadController] Seller offer notification failed: ' . $e->getMessage());
                    }
                }
            }

            $this->logTimeline('offer_submitted', $conversation, $boat, $locationId, $contact, [
                'offer_id'     => $offer->id,
                'offer_amount' => $offerAmount,
                'below_minimum' => $belowMin,
            ], $request);

            $this->trackEvent('widget_offer_submitted', 'offer', $locationId, $boat->id, $request);
            $this->notifyLocation($locationId, 'Bod uitgebracht', $data['name'], $boat, $conversation);

            return response()->json([
                'success'         => true,
                'flow'            => 'offer',
                'offer_id'        => $offer->id,
                'below_minimum'   => $belowMin,
                'conversation_id' => $conversation->id,
                'lead_id'         => $lead->id,
            ], 201);
        });
    }

    // ── POST /api/widget/brochure ───────────────────────────

    public function brochure(Request $request): JsonResponse
    {
        $data = $request->validate([
            'boat_id'    => 'required|integer|exists:yachts,id',
            'location_id' => 'nullable|integer|exists:locations,id',
            'name'       => 'required|string|max:255',
            'email'      => 'required|email|max:255',
            'phone'      => 'nullable|string|max:50',
            'source_url' => 'nullable|string',
        ]);

        return DB::transaction(function () use ($data, $request) {
            [$boat, $locationId] = $this->resolveBoatAndLocation($data);

            $contact = $this->resolveContact($data, $request);

            $body = $this->buildBrochureMessage($data, $boat);
            $conversation = $this->createConversation($contact, $boat, $locationId, $data, 'brochure', $body, $request);
            $lead = $this->ensureLead($conversation, $contact, $boat, $locationId, $data, 'brochure');

            $this->logTimeline('brochure_requested', $conversation, $boat, $locationId, $contact, [], $request);
            $this->trackEvent('widget_brochure_requested', 'brochure', $locationId, $boat->id, $request);
            $this->notifyLocation($locationId, 'Brochure aangevraagd', $data['name'], $boat, $conversation);

            return response()->json([
                'success'         => true,
                'flow'            => 'brochure',
                'conversation_id' => $conversation->id,
                'lead_id'         => $lead->id,
            ], 201);
        });
    }

    // ── POST /api/widget/callback ───────────────────────────

    public function callback(Request $request): JsonResponse
    {
        $data = $request->validate([
            'boat_id'       => 'required|integer|exists:yachts,id',
            'location_id'   => 'nullable|integer|exists:locations,id',
            'name'          => 'required|string|max:255',
            'phone'         => 'required|string|max:50',
            'email'         => 'nullable|email|max:255',
            'preferred_call_time' => 'nullable|string|max:100',
            'message'       => 'nullable|string|max:2000',
            'source_url'    => 'nullable|string',
        ]);

        return DB::transaction(function () use ($data, $request) {
            [$boat, $locationId] = $this->resolveBoatAndLocation($data);

            $contact = $this->resolveContact($data, $request);

            $body = $this->buildCallbackMessage($data, $boat);
            $conversation = $this->createConversation($contact, $boat, $locationId, $data, 'callback', $body, $request);
            $lead = $this->ensureLead($conversation, $contact, $boat, $locationId, $data, 'callback');

            $this->logTimeline('callback_requested', $conversation, $boat, $locationId, $contact, [
                'preferred_call_time' => $data['preferred_call_time'] ?? null,
            ], $request);

            $this->trackEvent('widget_callback_requested', 'callback', $locationId, $boat->id, $request);
            $this->notifyLocation($locationId, 'Terugbellen aangevraagd', $data['name'], $boat, $conversation);

            return response()->json([
                'success'         => true,
                'flow'            => 'callback',
                'conversation_id' => $conversation->id,
                'lead_id'         => $lead->id,
            ], 201);
        });
    }

    // ── POST /api/widget/question ───────────────────────────

    public function question(Request $request): JsonResponse
    {
        $data = $request->validate([
            'boat_id'    => 'required|integer|exists:yachts,id',
            'location_id' => 'nullable|integer|exists:locations,id',
            'question'   => 'required|string|max:2000',
            'name'       => 'required|string|max:255',
            'phone'      => 'required|string|max:50',
            'email'      => 'nullable|email|max:255',
            'source_url' => 'nullable|string',
        ]);

        return DB::transaction(function () use ($data, $request) {
            [$boat, $locationId] = $this->resolveBoatAndLocation($data);

            $contact = $this->resolveContact($data, $request);

            $body = $this->buildQuestionMessage($data, $boat);
            $conversation = $this->createConversation($contact, $boat, $locationId, $data, 'question', $body, $request);
            $lead = $this->ensureLead($conversation, $contact, $boat, $locationId, $data, 'question');

            $this->logTimeline('question_submitted', $conversation, $boat, $locationId, $contact, [], $request);
            $this->trackEvent('widget_question_submitted', 'question', $locationId, $boat->id, $request);
            $this->notifyLocation($locationId, 'Vraag gesteld', $data['name'], $boat, $conversation);

            return response()->json([
                'success'         => true,
                'flow'            => 'question',
                'conversation_id' => $conversation->id,
                'lead_id'         => $lead->id,
            ], 201);
        });
    }

    // ── GET /api/admin/widget/performance ───────────────────

    public function performance(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $from = $request->date('from') ?? now()->subDays(30);
        $to   = $request->date('to') ?? now();

        $eventCounts = DB::table('widget_events')
            ->whereBetween('created_at', [$from->startOfDay(), $to->endOfDay()])
            ->selectRaw('event_name, COUNT(*) as count')
            ->groupBy('event_name')
            ->pluck('count', 'event_name');

        $byLocation = DB::table('widget_events')
            ->join('locations', 'widget_events.location_id', '=', 'locations.id')
            ->whereBetween('widget_events.created_at', [$from->startOfDay(), $to->endOfDay()])
            ->selectRaw('locations.id, locations.name, COUNT(*) as events, SUM(CASE WHEN event_name LIKE \'widget_%submitted\' OR event_name LIKE \'widget_%requested\' THEN 1 ELSE 0 END) as conversions')
            ->groupBy('locations.id', 'locations.name')
            ->get();

        $widgetLeads = Lead::whereBetween('created_at', [$from->startOfDay(), $to->endOfDay()])
            ->where('source', 'schepenkring_widget')
            ->count();

        $widgetBookings = Booking::whereBetween('created_at', [$from->startOfDay(), $to->endOfDay()])
            ->where('source', 'widget')
            ->count();

        $totalViews    = $eventCounts->get('widget_loaded', 0);
        $totalOpens    = $eventCounts->get('widget_opened', 0);
        $totalSubmits  = collect($eventCounts)->filter(fn ($v, $k) => str_contains($k, 'submitted') || str_contains($k, 'requested'))->sum();
        $conversionRate = $totalViews > 0 ? round(($totalSubmits / $totalViews) * 100, 1) : 0;

        return response()->json([
            'period'          => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
            'widget_views'    => $totalViews,
            'widget_opens'    => $totalOpens,
            'plan_viewing'    => $eventCounts->get('widget_plan_viewing_submitted', 0),
            'offer'           => $eventCounts->get('widget_offer_submitted', 0),
            'brochure'        => $eventCounts->get('widget_brochure_requested', 0),
            'callback'        => $eventCounts->get('widget_callback_requested', 0),
            'question'        => $eventCounts->get('widget_question_submitted', 0),
            'leads_created'   => $widgetLeads,
            'bookings_created' => $widgetBookings,
            'conversion_rate' => $conversionRate,
            'by_location'     => $byLocation,
        ]);
    }

    // ── Private helpers ─────────────────────────────────────

    private function resolveBoatAndLocation(array $data): array
    {
        $boat = Yacht::findOrFail($data['boat_id']);
        $locationId = $data['location_id'] ?? $boat->location_id;

        if (!$locationId) {
            $locationId = Location::query()->value('id');
        }

        return [$boat, (int) $locationId];
    }

    /**
     * Resolve who a new lead/chat/offer should be assigned to: the boat's own
     * seller/owner first, then the location's configured assignment rule
     * (a specific default seller, round-robin across active staff, or always
     * unassigned), else null (falls into that location's general inbox).
     */
    private function resolveSellerId(Yacht $boat, int $locationId): ?int
    {
        if ($boat->seller_id) {
            return (int) $boat->seller_id;
        }

        if ($boat->user_id) {
            return (int) $boat->user_id;
        }

        $location = Location::query()->find($locationId, ['id', 'default_seller_id', 'lead_assignment_mode']);
        if (! $location) {
            return null;
        }

        return match ($location->lead_assignment_mode) {
            'unassigned'  => null,
            'round_robin' => $this->resolveRoundRobinSellerId($location),
            default       => $location->default_seller_id ? (int) $location->default_seller_id : null,
        };
    }

    /**
     * Rotates assignment across the location's active staff, using the count
     * of conversations already assigned at this location as the cursor —
     * avoids needing a separate "last assigned" state column.
     */
    private function resolveRoundRobinSellerId(Location $location): ?int
    {
        $staffIds = $location->users()
            ->whereIn('users.type', [UserType::EMPLOYEE->value, UserType::PARTNER->value])
            ->orderBy('users.id')
            ->pluck('users.id')
            ->all();

        if (empty($staffIds)) {
            return $location->default_seller_id ? (int) $location->default_seller_id : null;
        }

        $assignedCount = Conversation::where('location_id', $location->id)
            ->whereNotNull('assigned_employee_id')
            ->count();

        return (int) $staffIds[$assignedCount % count($staffIds)];
    }

    private function resolveContact(array $data, Request $request): ?Contact
    {
        return $this->contactService->resolveContact([
            'name'  => $data['name'],
            'email' => $data['email'] ?? null,
            'phone' => $data['phone'] ?? null,
        ], null);
    }

    private function createConversation(
        ?Contact $contact,
        Yacht $boat,
        int $locationId,
        array $data,
        string $flowType,
        string $body,
        Request $request
    ): Conversation {
        $conversation = Conversation::create([
            'location_id'      => $locationId,
            'boat_id'          => $boat->id,
            'contact_id'       => $contact?->id,
            'status'           => 'open',
            'priority'         => 'normal',
            'channel'          => 'web_widget',
            'channel_origin'   => 'web_widget',
            // chat_type uses the app-wide taxonomy (general/offer/viewing/
            // callback/question/brochure/seller/buyer) that staff-facing
            // filters, badges, and the composer's own type picker all use —
            // widget_flow_type keeps the finer-grained "which structured
            // widget form was this" detail (plan_viewing/offer/brochure/
            // callback/question) for filters that need that precision.
            'chat_type'        => $this->chatTypeForFlow($flowType),
            'widget_flow_type' => $flowType,
            'ai_mode'          => 'off',
            'page_url'         => $data['source_url'] ?? null,
        ]);

        // Assign to the boat's own seller, falling back to the location's default seller.
        // Leaving both null (no default seller either) means it lands in that location's
        // general inbox rather than a specific person's queue.
        $sellerId = $this->resolveSellerId($boat, $locationId);
        if ($sellerId) {
            $conversation->assigned_employee_id = $sellerId;
            $conversation->assigned_to = $sellerId;
            $conversation->save();
        }

        // Add structured message
        Message::create([
            'conversation_id' => $conversation->id,
            'sender_type'     => 'visitor',
            'body'            => $body,
            'delivery_state'  => 'sent',
        ]);

        return $conversation;
    }

    /**
     * Maps a widget flow type onto the app-wide chat_type taxonomy
     * (general/offer/viewing/callback/question/brochure/seller/buyer).
     * Only "plan_viewing" doesn't match its own name in that taxonomy —
     * everything else already lines up 1:1.
     */
    private function chatTypeForFlow(string $flowType): string
    {
        return match ($flowType) {
            'plan_viewing' => 'viewing',
            default => $flowType,
        };
    }

    private function ensureLead(
        Conversation $conversation,
        ?Contact $contact,
        Yacht $boat,
        int $locationId,
        array $data,
        string $flowType
    ): Lead {
        $lead = Lead::create([
            'location_id'          => $locationId,
            'yacht_id'             => $boat->id,
            'conversation_id'      => $conversation->id,
            'status'               => 'new',
            'source'               => 'schepenkring_widget',
            'source_url'           => $data['source_url'] ?? null,
            'name'                 => $data['name'],
            'email'                => $data['email'] ?? null,
            'phone'                => $data['phone'] ?? null,
            'notes'                => "Flow: {$flowType}",
            'assigned_employee_id' => $this->resolveSellerId($boat, $locationId),
        ]);

        $conversation->lead_id = $lead->id;
        $conversation->save();

        $lead->conversation_id = $conversation->id;
        $lead->save();

        return $lead;
    }

    private function logTimeline(
        string $action,
        Conversation $conversation,
        Yacht $boat,
        int $locationId,
        ?Contact $contact,
        array $extra,
        Request $request
    ): void {
        try {
            AuditLog::create([
                'action'      => $action,
                'risk_level'  => 'low',
                'result'      => 'success',
                'location_id' => $locationId,
                'entity_type' => 'conversation',
                'entity_id'   => $conversation->id,
                'meta'        => array_merge([
                    'flow'            => $conversation->widget_flow_type,
                    'boat_id'         => $boat->id,
                    'boat_name'       => trim(($boat->manufacturer ?? $boat->make ?? '') . ' ' . ($boat->model ?? '')),
                    'contact_id'      => $contact?->id,
                    'conversation_id' => $conversation->id,
                ], $extra),
                'ip_address' => $request->ip(),
                'user_agent' => substr($request->userAgent() ?? '', 0, 500),
            ]);
        } catch (\Throwable $e) {
            Log::warning('[WidgetLeadController] Timeline log failed: ' . $e->getMessage());
        }
    }

    private function trackEvent(
        string $eventName,
        ?string $flowType,
        ?int $locationId,
        ?int $boatId,
        Request $request
    ): void {
        try {
            DB::table('widget_events')->insert([
                'event_name'  => $eventName,
                'flow_type'   => $flowType,
                'location_id' => $locationId,
                'boat_id'     => $boatId,
                'visitor_id'  => $request->header('X-Visitor-Id') ?? $request->input('visitor_id'),
                'source_url'  => $request->input('source_url'),
                'ip_address'  => $request->ip(),
                'user_agent'  => substr($request->userAgent() ?? '', 0, 500),
                'created_at'  => now(),
                'updated_at'  => now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('[WidgetLeadController] Event tracking failed: ' . $e->getMessage());
        }
    }

    private function notifyLocation(
        int $locationId,
        string $subject,
        string $visitorName,
        Yacht $boat,
        Conversation $conversation
    ): void {
        try {
            $location = Location::find($locationId);
            if (!$location) {
                return;
            }

            // Fire notification to location users - uses existing notification infrastructure
            $staffIds = \App\Models\User::where('client_location_id', $locationId)
                ->whereIn('role', ['admin', 'employee', 'manager'])
                ->pluck('id');

            foreach ($staffIds as $staffId) {
                \App\Models\AppNotification::notify(
                    $staffId,
                    'widget_lead',
                    $subject,
                    "{$visitorName} - " . trim(($boat->manufacturer ?? '') . ' ' . ($boat->model ?? '')),
                    ['conversation_id' => $conversation->id],
                    'info',
                );
            }
        } catch (\Throwable $e) {
            Log::warning('[WidgetLeadController] Location notification failed: ' . $e->getMessage());
        }
    }

    // ── Message builders ────────────────────────────────────

    private function buildViewingMessage(array $data, Yacht $boat): string
    {
        $boatName = trim(($boat->manufacturer ?? $boat->make ?? '') . ' ' . ($boat->model ?? ''));
        return "**Bezichtiging aangevraagd**\n\n" .
            "**Boot:** {$boatName}\n" .
            "**Gewenste datum:** {$data['preferred_date']}\n" .
            "**Gewenste tijd:** {$data['preferred_time']}\n\n" .
            "**Naam:** {$data['name']}\n" .
            "**Telefoon:** {$data['phone']}\n" .
            ($data['email'] ? "**E-mail:** {$data['email']}\n" : '') .
            ($data['message'] ? "\n**Bericht:** {$data['message']}" : '');
    }

    private function buildOfferMessage(array $data, Yacht $boat): string
    {
        $boatName = trim(($boat->manufacturer ?? $boat->make ?? '') . ' ' . ($boat->model ?? ''));
        $amount   = number_format((float) $data['offer_amount'], 0, ',', '.');
        return "**Bod uitgebracht**\n\n" .
            "**Boot:** {$boatName}\n" .
            "**Bod:** € {$amount}\n\n" .
            "**Naam:** {$data['name']}\n" .
            "**Telefoon:** {$data['phone']}\n" .
            ($data['email'] ? "**E-mail:** {$data['email']}\n" : '') .
            ($data['message'] ? "\n**Bericht:** {$data['message']}" : '');
    }

    private function buildBrochureMessage(array $data, Yacht $boat): string
    {
        $boatName = trim(($boat->manufacturer ?? $boat->make ?? '') . ' ' . ($boat->model ?? ''));
        return "**Brochure aangevraagd**\n\n" .
            "**Boot:** {$boatName}\n\n" .
            "**Naam:** {$data['name']}\n" .
            "**E-mail:** {$data['email']}\n" .
            ($data['phone'] ? "**Telefoon:** {$data['phone']}" : '');
    }

    private function buildCallbackMessage(array $data, Yacht $boat): string
    {
        $boatName = trim(($boat->manufacturer ?? $boat->make ?? '') . ' ' . ($boat->model ?? ''));
        return "**Terugbellen aangevraagd**\n\n" .
            "**Boot:** {$boatName}\n" .
            ($data['preferred_call_time'] ? "**Gewenste beltijd:** {$data['preferred_call_time']}\n" : '') .
            "\n**Naam:** {$data['name']}\n" .
            "**Telefoon:** {$data['phone']}\n" .
            ($data['email'] ? "**E-mail:** {$data['email']}\n" : '') .
            ($data['message'] ? "\n**Bericht:** {$data['message']}" : '');
    }

    private function buildQuestionMessage(array $data, Yacht $boat): string
    {
        $boatName = trim(($boat->manufacturer ?? $boat->make ?? '') . ' ' . ($boat->model ?? ''));
        return "**Vraag gesteld**\n\n" .
            "**Boot:** {$boatName}\n\n" .
            "**Vraag:** {$data['question']}\n\n" .
            "**Naam:** {$data['name']}\n" .
            "**Telefoon:** {$data['phone']}\n" .
            ($data['email'] ? "**E-mail:** {$data['email']}" : '');
    }
}
