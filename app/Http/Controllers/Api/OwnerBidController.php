<?php

namespace App\Http\Controllers\Api;

use App\Actions\Kyc\CreateKycCaseAction;
use App\Enums\UserStatus;
use App\Enums\UserType;
use App\Http\Controllers\Controller;
use App\Models\AppNotification;
use App\Models\AuditLog;
use App\Models\Conversation;
use App\Models\Deal;
use App\Models\Location;
use App\Models\Message;
use App\Models\OwnerBid;
use App\Models\User;
use App\Models\Yacht;
use App\Services\LocationAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class OwnerBidController extends Controller
{
    private const ROUTING_MODES = ['direct', 'admin_review', 'broker'];
    private const SELLER_OPEN_STATUSES = ['pending', 'admin_review', 'broker_review'];
    private const ADMIN_STATUSES = [
        'pending',
        'admin_review',
        'broker_review',
        'countered',
        'accepted',
        'rejected',
        'counter_rejected',
        'expired',
        'paused',
    ];

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $query = OwnerBid::query()
            ->with($this->bidRelations())
            ->where(function ($builder) use ($user) {
                $builder->where('user_id', $user->id)
                    ->orWhere('seller_id', $user->id)
                    ->orWhere('assigned_broker_id', $user->id);
            })
            ->latest();

        $this->applyBidFilters($query, $request);

        return response()->json([
            'data' => $query->limit((int) $request->integer('limit', 100))->get(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'yacht_id' => 'required|integer|exists:yachts,id',
            'amount' => 'required|numeric|min:1',
            'message' => 'nullable|string|max:2000',
            'expires_at' => 'nullable|date|after:now',
        ]);

        $yacht = Yacht::with(['location', 'owner'])->findOrFail($validated['yacht_id']);
        $location = $this->resolveBidLocation($yacht);

        abort_if(! $yacht->allow_bidding, 422, 'Bidding is disabled for this boat.');
        abort_if($location && ! (bool) $location->bids_page_enabled, 422, 'Bids are disabled for this location.');
        abort_if(! $yacht->user_id, 422, 'This boat has no seller assigned.');

        $routingMode = $this->normalizeRoutingMode($location?->bid_routing_mode);
        $assignedBrokerId = $routingMode === 'broker' ? $this->firstActiveBrokerId($location) : null;
        $status = $this->initialStatusForRouting($routingMode, $assignedBrokerId);
        $expiresAt = isset($validated['expires_at']) ? Carbon::parse($validated['expires_at']) : now()->addDays(7);
        $lowBidWarning = $this->lowBidWarning($yacht, (float) $validated['amount']);

        [$bid, $conversation] = DB::transaction(function () use (
            $request,
            $validated,
            $yacht,
            $location,
            $routingMode,
            $assignedBrokerId,
            $status,
            $expiresAt,
            $lowBidWarning
        ) {
            $bid = OwnerBid::create([
                'yacht_id' => $yacht->id,
                'location_id' => $location?->id,
                'user_id' => $request->user()->id,
                'seller_id' => $yacht->user_id,
                'assigned_broker_id' => $assignedBrokerId,
                'parent_bid_id' => null,
                'type' => 'offer',
                'routing_mode' => $routingMode,
                'amount' => $validated['amount'],
                'message' => $validated['message'] ?? null,
                'status' => $status,
                'expires_at' => $expiresAt,
                'ai_summary' => $this->buildBidSummary($yacht, (float) $validated['amount'], $routingMode, $lowBidWarning),
            ]);

            $conversation = $this->shouldOpenBidConversation($location, $routingMode)
                ? $this->openBidConversation($bid)
                : null;

            if ($conversation) {
                $this->addBidSystemMessage(
                    $conversation,
                    $bid,
                    "New {$routingMode} owner bid placed for {$this->boatLabel($yacht)} at {$this->formatAmount($bid->amount)}.",
                    'owner_bid.created'
                );
            }

            $this->auditBid($request, 'owner_bid.created', $bid, [
                'routing_mode' => $routingMode,
                'low_bid_warning' => $lowBidWarning,
            ]);

            return [$bid, $conversation];
        });

        $this->notifySeller($bid, 'new_bid');
        $this->notifyAdministrators($bid, 'new_owner_bid', $lowBidWarning ? 'warning' : 'info');
        $this->notifyAssignedBroker($bid);

        return response()->json([
            'bid' => $bid->load($this->bidRelations()),
            'conversation_id' => $conversation?->id,
            'warning' => $lowBidWarning,
        ], 201);
    }

    public function counter(Request $request, int $id): JsonResponse
    {
        $bid = OwnerBid::with($this->bidRelations())->findOrFail($id);

        $this->assertCanManageBid($request->user(), $bid);
        $this->abortIfExpired($request, $bid);

        abort_if($bid->type === 'counter', 422, 'Counter offers cannot be countered again.');
        abort_if(! in_array($bid->status, self::SELLER_OPEN_STATUSES, true), 422, 'Bid is no longer open for counter offers.');

        $validated = $request->validate([
            'amount' => 'required|numeric|min:1',
            'message' => 'nullable|string|max:2000',
            'expires_at' => 'nullable|date|after:now',
        ]);

        $counter = DB::transaction(function () use ($request, $bid, $validated) {
            $bid->update(['status' => 'countered']);

            $counter = OwnerBid::create([
                'yacht_id' => $bid->yacht_id,
                'location_id' => $bid->location_id,
                'user_id' => $bid->user_id,
                'seller_id' => $bid->seller_id,
                'assigned_broker_id' => $bid->assigned_broker_id,
                'parent_bid_id' => $bid->id,
                'type' => 'counter',
                'routing_mode' => $bid->routing_mode,
                'amount' => $validated['amount'],
                'message' => $validated['message'] ?? null,
                'status' => 'pending',
                'expires_at' => isset($validated['expires_at']) ? Carbon::parse($validated['expires_at']) : now()->addDays(7),
                'ai_summary' => $this->buildBidSummary($bid->yacht, (float) $validated['amount'], $bid->routing_mode, null),
            ]);

            $conversation = $this->openBidConversation($counter);
            if ($conversation) {
                $this->addBidSystemMessage(
                    $conversation,
                    $counter,
                    "Counter offer sent at {$this->formatAmount($counter->amount)} for {$this->boatLabel($counter->yacht)}.",
                    'owner_bid.countered'
                );
            }

            $this->auditBid($request, 'owner_bid.countered', $counter, [
                'parent_bid_id' => $bid->id,
            ]);

            return $counter;
        });

        $this->notifyBuyer($counter, 'counter_offer');

        return response()->json([
            'bid' => $counter->load($this->bidRelations()),
        ]);
    }

    public function accept(Request $request, int $id): JsonResponse
    {
        $bid = OwnerBid::with($this->bidRelations())->findOrFail($id);

        $this->assertCanManageBid($request->user(), $bid);
        $this->abortIfExpired($request, $bid);

        abort_if($bid->type === 'counter', 422, 'Use the buyer counter endpoint for counter offers.');
        abort_if(! in_array($bid->status, self::SELLER_OPEN_STATUSES, true), 422, 'Bid is no longer open.');

        return $this->completeAcceptedBid($request, $bid, 'owner_bid.accepted');
    }

    public function reject(Request $request, int $id): JsonResponse
    {
        $bid = OwnerBid::with($this->bidRelations())->findOrFail($id);

        $this->assertCanManageBid($request->user(), $bid);
        $this->abortIfExpired($request, $bid);

        abort_if($bid->type === 'counter', 422, 'Use the buyer counter endpoint for counter offers.');
        abort_if(! in_array($bid->status, self::SELLER_OPEN_STATUSES, true), 422, 'Bid is no longer open.');

        $bid->update(['status' => 'rejected']);

        $this->notifyBuyer($bid, 'bid_rejected');
        $this->auditBid($request, 'owner_bid.rejected', $bid);

        return response()->json(['message' => 'Bid rejected.']);
    }

    public function acceptCounter(Request $request, int $id): JsonResponse
    {
        $bid = OwnerBid::with($this->bidRelations())->findOrFail($id);

        abort_if($bid->user_id !== $request->user()->id, 403, 'Not your counter offer.');
        $this->abortIfExpired($request, $bid);

        abort_if($bid->type !== 'counter', 422, 'Only counter offers can be accepted here.');
        abort_if($bid->status !== 'pending', 422, 'Counter offer is no longer open.');

        return $this->completeAcceptedBid($request, $bid, 'owner_bid.counter_accepted');
    }

    public function rejectCounter(Request $request, int $id): JsonResponse
    {
        $bid = OwnerBid::with($this->bidRelations())->findOrFail($id);

        abort_if($bid->user_id !== $request->user()->id, 403, 'Not your counter offer.');
        $this->abortIfExpired($request, $bid);

        abort_if($bid->type !== 'counter', 422, 'Only counter offers can be rejected here.');
        abort_if($bid->status !== 'pending', 422, 'Counter offer is no longer open.');

        $bid->update(['status' => 'rejected']);
        $bid->parent?->update(['status' => 'counter_rejected']);

        $this->notifySellerOfBuyerDecision($bid, 'counter_rejected');
        $this->auditBid($request, 'owner_bid.counter_rejected', $bid);

        return response()->json(['message' => 'Counter offer rejected.']);
    }

    public function adminIndex(Request $request): JsonResponse
    {
        $query = OwnerBid::query()
            ->with($this->bidRelations())
            ->latest();

        $this->applyBidFilters($query, $request);

        if ($request->filled('location_id')) {
            $query->where('location_id', $request->integer('location_id'));
        }

        if ($request->filled('assigned_broker_id')) {
            $query->where('assigned_broker_id', $request->integer('assigned_broker_id'));
        }

        return response()->json([
            'data' => $query->limit((int) $request->integer('limit', 100))->get(),
        ]);
    }

    public function adminUpdate(Request $request, int $id): JsonResponse
    {
        $bid = OwnerBid::with($this->bidRelations())->findOrFail($id);

        $validated = $request->validate([
            'status' => ['sometimes', Rule::in(self::ADMIN_STATUSES)],
            'assigned_broker_id' => 'sometimes|nullable|integer|exists:users,id',
            'admin_notes' => 'sometimes|nullable|string|max:5000',
            'expires_at' => 'sometimes|nullable|date|after:now',
        ]);

        if (($validated['status'] ?? null) === 'accepted') {
            $bid->fill(collect($validated)->except('status')->all());
            $bid->save();

            return $this->completeAcceptedBid($request, $bid->fresh($this->bidRelations()), 'owner_bid.admin_accepted');
        }

        $before = $bid->toArray();
        $bid->fill($validated);

        if (($validated['status'] ?? null) === 'paused') {
            $bid->paused_at = now();
        }

        $bid->save();

        $this->auditBid($request, 'owner_bid.admin_updated', $bid, [
            'before' => $before,
            'after' => $bid->fresh()->toArray(),
        ]);

        return response()->json([
            'bid' => $bid->fresh($this->bidRelations()),
        ]);
    }

    public function pause(Request $request, int $id): JsonResponse
    {
        $bid = OwnerBid::with($this->bidRelations())->findOrFail($id);

        $validated = $request->validate([
            'admin_notes' => 'nullable|string|max:5000',
        ]);

        $bid->update([
            'status' => 'paused',
            'paused_at' => now(),
            'admin_notes' => $validated['admin_notes'] ?? $bid->admin_notes,
        ]);

        $this->auditBid($request, 'owner_bid.paused', $bid);

        return response()->json([
            'bid' => $bid->fresh($this->bidRelations()),
        ]);
    }

    private function completeAcceptedBid(Request $request, OwnerBid $bid, string $auditAction): JsonResponse
    {
        [$acceptedBid, $conversation, $deal] = DB::transaction(function () use ($request, $bid, $auditAction) {
            $bid->update(['status' => 'accepted']);

            if ($bid->parent_bid_id) {
                $bid->parent?->update(['status' => 'accepted']);
            }

            OwnerBid::query()
                ->where('yacht_id', $bid->yacht_id)
                ->whereNotIn('id', array_filter([$bid->id, $bid->parent_bid_id]))
                ->whereIn('status', ['pending', 'admin_review', 'broker_review', 'countered'])
                ->update(['status' => 'rejected']);

            $bid->yacht?->update([
                'allow_bidding' => false,
                'auction_enabled' => false,
                'current_bid' => $bid->amount,
            ]);

            $conversation = $this->openBidConversation($bid);

            if ($conversation) {
                $this->addBidSystemMessage(
                    $conversation,
                    $bid,
                    "Owner bid accepted at {$this->formatAmount($bid->amount)}. A deal was created and further bidding was disabled.",
                    'owner_bid.accepted'
                );
            }

            $deal = Deal::firstOrCreate(
                ['owner_bid_id' => $bid->id],
                [
                    'yacht_id' => $bid->yacht_id,
                    'buyer_id' => $bid->user_id,
                    'seller_id' => $bid->seller_id,
                    'conversation_id' => $conversation?->id,
                    'agreed_amount' => $bid->amount,
                    'status' => 'active',
                ]
            );

            $this->auditBid($request, $auditAction, $bid, [
                'deal_id' => $deal->id,
                'conversation_id' => $conversation?->id,
            ]);

            return [$bid->fresh($this->bidRelations()), $conversation, $deal];
        });

        // Auto-create KYC case for the new deal (outside transaction so KYC failure never rolls back the deal)
        try {
            app(CreateKycCaseAction::class)->fromDeal($deal);
        } catch (\Throwable $e) {
            Log::warning('[OwnerBidController] KYC auto-create failed for deal ' . $deal->id . ': ' . $e->getMessage());
        }

        $this->notifyBuyer($acceptedBid, 'bid_accepted');
        $this->notifySellerOfBuyerDecision($acceptedBid, 'bid_accepted');

        return response()->json([
            'deal_id' => $deal->id,
            'message' => 'Bid accepted. A deal has been created and further bidding was disabled.',
            'conversation_id' => $conversation?->id,
            'bid' => $acceptedBid,
        ]);
    }

    private function applyBidFilters($query, Request $request): void
    {
        if ($request->filled('status')) {
            $query->where('status', (string) $request->string('status'));
        }

        if ($request->filled('yacht_id')) {
            $query->where('yacht_id', $request->integer('yacht_id'));
        }

        if ($request->filled('routing_mode')) {
            $query->where('routing_mode', (string) $request->string('routing_mode'));
        }
    }

    private function bidRelations(): array
    {
        return [
            'buyer:id,name,email',
            'seller:id,name,email',
            'assignedBroker:id,name,email',
            'location:id,name,code,bid_routing_mode,bids_page_enabled,seller_bid_notifications_enabled,direct_buyer_seller_chat_enabled',
            'yacht:id,user_id,location_id,boat_name,price,min_bid_amount,allow_bidding,auction_enabled,current_bid',
        ];
    }

    private function resolveBidLocation(Yacht $yacht): ?Location
    {
        if ($yacht->location) {
            return $yacht->location;
        }

        if ($yacht->ref_harbor_id) {
            return Location::find($yacht->ref_harbor_id);
        }

        return null;
    }

    private function normalizeRoutingMode(?string $mode): string
    {
        return in_array($mode, self::ROUTING_MODES, true) ? $mode : 'direct';
    }

    private function initialStatusForRouting(string $routingMode, ?int $assignedBrokerId): string
    {
        return match ($routingMode) {
            'admin_review' => 'admin_review',
            'broker' => $assignedBrokerId ? 'broker_review' : 'admin_review',
            default => 'pending',
        };
    }

    private function shouldOpenBidConversation(?Location $location, string $routingMode): bool
    {
        return $routingMode !== 'direct' || (bool) ($location?->direct_buyer_seller_chat_enabled ?? false);
    }

    private function firstActiveBrokerId(?Location $location): ?int
    {
        return $location?->activeEmployees()->orderBy('users.id')->value('users.id');
    }

    private function lowBidWarning(Yacht $yacht, float $amount): ?string
    {
        if ($yacht->min_bid_amount && $amount < (float) $yacht->min_bid_amount) {
            return 'Bid is below the configured minimum bid amount.';
        }

        return null;
    }

    private function buildBidSummary(Yacht $yacht, float $amount, string $routingMode, ?string $warning): string
    {
        $price = $yacht->price ? $this->formatAmount($yacht->price) : 'no list price';
        $summary = sprintf(
            '%s bid for %s at %s against %s.',
            str_replace('_', ' ', ucfirst($routingMode)),
            $this->boatLabel($yacht),
            $this->formatAmount($amount),
            $price
        );

        return $warning ? "{$summary} {$warning}" : $summary;
    }

    private function abortIfExpired(Request $request, OwnerBid $bid): void
    {
        if ($bid->expires_at && $bid->expires_at->isPast() && ! in_array($bid->status, ['accepted', 'rejected', 'expired'], true)) {
            $bid->update(['status' => 'expired']);
            $this->auditBid($request, 'owner_bid.expired', $bid);

            abort(422, 'Bid has expired.');
        }
    }

    private function assertCanManageBid(User $user, OwnerBid $bid): void
    {
        if ($user->isAdmin() || $bid->seller_id === $user->id) {
            return;
        }

        if ($user->isEmployee() && app(LocationAccessService::class)->sharesLocation($user, $bid->location_id)) {
            return;
        }

        abort(403, 'Not your bid to manage.');
    }

    private function openBidConversation(OwnerBid $bid): ?Conversation
    {
        $bid->loadMissing(['yacht', 'location', 'assignedBroker']);

        $locationId = $bid->location_id
            ?? $bid->yacht?->location_id
            ?? Location::query()->value('id');

        if (! $locationId) {
            return null;
        }

        $threadKey = 'owner-bid-' . ($bid->parent_bid_id ?: $bid->id);
        $conversation = Conversation::query()
            ->where('visitor_id', $threadKey)
            ->where('channel_origin', 'owner_bid')
            ->first();

        if ($conversation) {
            return $conversation;
        }

        $assignedUserId = $bid->assigned_broker_id ?: ($bid->routing_mode === 'direct' ? $bid->seller_id : $this->firstAdminId());
        $assignedEmployeeId = $this->employeeIdOrNull($assignedUserId);

        return Conversation::create([
            'user_id' => $bid->user_id,
            'location_id' => $locationId,
            'boat_id' => $bid->yacht_id,
            'visitor_id' => $threadKey,
            'status' => 'open',
            'priority' => 'high',
            'channel' => 'owner_bid',
            'channel_origin' => 'owner_bid',
            'ai_mode' => 'manual',
            'assigned_to' => $assignedUserId,
            'assigned_employee_id' => $assignedEmployeeId,
            'last_message_at' => now(),
        ]);
    }

    private function addBidSystemMessage(Conversation $conversation, OwnerBid $bid, string $text, string $event): void
    {
        Message::create([
            'conversation_id' => $conversation->id,
            'sender_type' => 'system',
            'text' => $text,
            'body' => $text,
            'channel' => 'owner_bid',
            'message_type' => 'system',
            'metadata' => [
                'event' => $event,
                'owner_bid_id' => $bid->id,
                'parent_bid_id' => $bid->parent_bid_id,
                'yacht_id' => $bid->yacht_id,
                'amount' => (float) $bid->amount,
                'routing_mode' => $bid->routing_mode,
            ],
            'delivery_state' => 'sent',
            'role_visibility' => ['buyer', 'seller', 'admin', 'broker'],
        ]);

        $conversation->forceFill(['last_message_at' => now()])->save();
    }

    private function firstAdminId(): ?int
    {
        return User::query()
            ->where('type', UserType::ADMIN->value)
            ->where('status', UserStatus::ACTIVE->value)
            ->orderBy('id')
            ->value('id');
    }

    private function employeeIdOrNull(?int $userId): ?int
    {
        if (! $userId) {
            return null;
        }

        return User::query()
            ->whereKey($userId)
            ->where('type', UserType::EMPLOYEE->value)
            ->value('id');
    }

    private function notifySeller(OwnerBid $bid, string $type): void
    {
        $bid->loadMissing(['location', 'yacht']);

        if (! (bool) ($bid->location?->seller_bid_notifications_enabled ?? true)) {
            return;
        }

        AppNotification::notify(
            $bid->seller_id,
            $type,
            'New bid received',
            "You received a bid of {$this->formatAmount($bid->amount)} on {$this->boatLabel($bid->yacht)}.",
            ['owner_bid_id' => $bid->id, 'yacht_id' => $bid->yacht_id, 'routing_mode' => $bid->routing_mode],
        );
    }

    private function notifyBuyer(OwnerBid $bid, string $type): void
    {
        $bid->loadMissing('yacht');

        $messages = [
            'counter_offer' => "The seller sent a counter offer of {$this->formatAmount($bid->amount)}.",
            'bid_accepted' => "Your bid of {$this->formatAmount($bid->amount)} on {$this->boatLabel($bid->yacht)} was accepted.",
            'bid_rejected' => "Your bid on {$this->boatLabel($bid->yacht)} was declined.",
        ];

        AppNotification::notify(
            $bid->user_id,
            $type,
            ucwords(str_replace('_', ' ', $type)),
            $messages[$type] ?? '',
            ['owner_bid_id' => $bid->id, 'yacht_id' => $bid->yacht_id],
        );
    }

    private function notifyAdministrators(OwnerBid $bid, string $type, string $severity = 'info'): void
    {
        $bid->loadMissing(['yacht', 'location']);

        User::query()
            ->where('type', UserType::ADMIN->value)
            ->where('status', UserStatus::ACTIVE->value)
            ->each(function (User $admin) use ($bid, $type, $severity) {
                AppNotification::notify(
                    $admin->id,
                    $type,
                    'Owner bid requires attention',
                    "{$this->boatLabel($bid->yacht)} received a {$bid->routing_mode} bid for {$this->formatAmount($bid->amount)}.",
                    ['owner_bid_id' => $bid->id, 'location_id' => $bid->location_id],
                    $severity,
                );
            });
    }

    private function notifyAssignedBroker(OwnerBid $bid): void
    {
        if (! $bid->assigned_broker_id) {
            return;
        }

        $bid->loadMissing('yacht');

        AppNotification::notify(
            $bid->assigned_broker_id,
            'broker_owner_bid_assigned',
            'Owner bid assigned',
            "{$this->boatLabel($bid->yacht)} received a bid for {$this->formatAmount($bid->amount)}.",
            ['owner_bid_id' => $bid->id, 'yacht_id' => $bid->yacht_id],
        );
    }

    private function notifySellerOfBuyerDecision(OwnerBid $bid, string $type): void
    {
        $bid->loadMissing('yacht');

        $messages = [
            'bid_accepted' => "A bid on {$this->boatLabel($bid->yacht)} was accepted at {$this->formatAmount($bid->amount)}.",
            'counter_rejected' => "The buyer rejected the counter offer on {$this->boatLabel($bid->yacht)}.",
        ];

        AppNotification::notify(
            $bid->seller_id,
            $type,
            ucwords(str_replace('_', ' ', $type)),
            $messages[$type] ?? '',
            ['owner_bid_id' => $bid->id, 'yacht_id' => $bid->yacht_id],
        );
    }

    private function auditBid(Request $request, string $action, OwnerBid $bid, array $meta = []): void
    {
        AuditLog::create([
            'action' => $action,
            'risk_level' => 'LOW',
            'result' => 'SUCCESS',
            'actor_id' => $request->user()?->id,
            'location_id' => $bid->location_id,
            'target_type' => OwnerBid::class,
            'target_id' => $bid->id,
            'entity_type' => OwnerBid::class,
            'entity_id' => $bid->id,
            'meta' => array_merge([
                'yacht_id' => $bid->yacht_id,
                'buyer_id' => $bid->user_id,
                'seller_id' => $bid->seller_id,
                'status' => $bid->status,
                'routing_mode' => $bid->routing_mode,
            ], $meta),
            'ip_address' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 1000),
        ]);
    }

    private function boatLabel(?Yacht $yacht): string
    {
        return $yacht?->boat_name ?: 'this boat';
    }

    private function formatAmount(mixed $amount): string
    {
        return '€' . number_format((float) $amount, 2, '.', ',');
    }
}
