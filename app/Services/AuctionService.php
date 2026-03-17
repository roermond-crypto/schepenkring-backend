<?php

namespace App\Services;

use App\Events\AuctionBidPlaced;
use App\Events\AuctionStateUpdated;
use App\Models\AuctionSession;
use App\Models\Bid;
use App\Models\Bidder;
use App\Models\User;
use App\Models\Yacht;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AuctionService
{
    public const MODE_OFF = 'off';
    public const MODE_BIDS = 'bids';
    public const MODE_LIVE = 'live';

    public const SESSION_SCHEDULED = 'scheduled';
    public const SESSION_ACTIVE = 'active';
    public const SESSION_ENDED = 'ended';
    public const SESSION_CANCELLED = 'cancelled';

    public function __construct(private BidRulesService $rules)
    {
    }

    public function resolveMode(Yacht $yacht): string
    {
        $mode = strtolower((string) $yacht->auction_mode);

        if ((bool) $yacht->auction_enabled && in_array($mode, [self::MODE_BIDS, self::MODE_LIVE], true)) {
            return $mode;
        }

        if ((bool) $yacht->allow_bidding) {
            return self::MODE_BIDS;
        }

        return self::MODE_OFF;
    }

    public function publicState(
        Yacht $yacht,
        ?int $locationId = null,
        bool $includeBids = true,
        int $bidLimit = 10,
        bool $sync = true
    ): array
    {
        if ($sync) {
            $this->synchronizeLiveAuction($yacht);
        }

        $freshYacht = Yacht::with('owner')->find($yacht->id) ?? $yacht;
        $this->assertLocationMatches($freshYacht, $locationId);

        $mode = $this->resolveMode($freshYacht);
        $session = $this->latestSession($freshYacht);
        $status = $this->determinePublicStatus($freshYacht, $session, $mode);
        $currentBid = $freshYacht->current_bid !== null ? (float) $freshYacht->current_bid : null;
        $highestBid = $session?->highest_bid !== null ? (float) $session->highest_bid : $currentBid;
        $minimumNextBid = $mode === self::MODE_OFF ? null : $this->rules->minimumNextBid($freshYacht);
        $recentBids = $includeBids ? $this->publicBids($freshYacht, $locationId, $bidLimit, false) : [];

        return [
            'boat_id' => $freshYacht->id,
            'yacht_id' => $freshYacht->id,
            'location_id' => $this->resolveLocationId($freshYacht),
            'auction_enabled' => $mode !== self::MODE_OFF,
            'allow_bidding' => (bool) $freshYacht->allow_bidding,
            'auction_mode' => $mode,
            'auction_status' => $status,
            'current_bid' => $currentBid,
            'highest_bid' => $highestBid,
            'starting_bid' => $freshYacht->min_bid_amount !== null ? (float) $freshYacht->min_bid_amount : null,
            'minimum_next_bid' => $minimumNextBid,
            'min_increment' => $this->rules->minIncrement(),
            'auction_start' => $session?->start_time?->toISOString() ?? $freshYacht->auction_start?->toISOString(),
            'auction_end' => $session?->end_time?->toISOString() ?? $freshYacht->auction_end?->toISOString(),
            'starts_in_seconds' => $this->secondsUntil($session?->start_time ?? $freshYacht->auction_start),
            'countdown_seconds' => $status === self::SESSION_ACTIVE ? $this->secondsUntil($session?->end_time ?? $freshYacht->auction_end) : 0,
            'bid_count' => $session?->total_bids ?? Bid::query()->where('yacht_id', $freshYacht->id)->count(),
            'bidder_count' => $session?->unique_bidders ?? Bid::query()->where('yacht_id', $freshYacht->id)->distinct('bidder_id')->count('bidder_id'),
            'viewer_can_bid' => in_array($status, ['open', self::SESSION_ACTIVE], true),
            'winner' => $this->publicWinner($session),
            'session' => $session ? $this->publicSession($session) : null,
            'recent_bids' => $recentBids,
        ];
    }

    public function publicBids(Yacht $yacht, ?int $locationId = null, int $limit = 20, bool $sync = true): array
    {
        if ($sync) {
            $this->synchronizeLiveAuction($yacht);
        }
        $freshYacht = Yacht::with('owner')->find($yacht->id) ?? $yacht;
        $this->assertLocationMatches($freshYacht, $locationId);

        return Bid::query()
            ->where('yacht_id', $freshYacht->id)
            ->latest('id')
            ->limit(max(1, min($limit, 50)))
            ->get()
            ->map(fn (Bid $bid) => $this->publicBid($bid))
            ->all();
    }

    public function publicBid(Bid $bid): array
    {
        return [
            'id' => $bid->id,
            'boat_id' => $bid->yacht_id,
            'yacht_id' => $bid->yacht_id,
            'auction_session_id' => $bid->auction_session_id,
            'location_id' => $bid->location_id,
            'amount' => (float) $bid->amount,
            'status' => $bid->status,
            'bidder' => $this->publicBidderAlias($bid->bidder_name),
            'placed_at' => $bid->created_at?->toISOString(),
        ];
    }

    public function placeBid(Bidder $bidder, Yacht $yacht, float $amount, Request $request, ?int $locationId = null): Bid
    {
        $bid = DB::transaction(function () use ($bidder, $yacht, $amount, $request, $locationId) {
            $lockedYacht = Yacht::with('owner')->whereKey($yacht->id)->lockForUpdate()->first();
            if (! $lockedYacht) {
                throw ValidationException::withMessages([
                    'yacht_id' => 'Listing not found.',
                ]);
            }

            $this->assertLocationMatches($lockedYacht, $locationId);
            $mode = $this->resolveMode($lockedYacht);

            if ($mode === self::MODE_OFF) {
                throw ValidationException::withMessages([
                    'yacht_id' => 'Auction or bidding is not enabled for this listing.',
                ]);
            }

            $session = null;

            if ($mode === self::MODE_LIVE) {
                $session = $this->synchronizeLockedLiveSession($lockedYacht);

                if (! $session || $session->status !== self::SESSION_ACTIVE || ! $session->end_time || $session->end_time->lte(now())) {
                    throw ValidationException::withMessages([
                        'yacht_id' => 'Live auction is not active for this listing.',
                    ]);
                }
            }

            $minimum = $this->rules->minimumNextBid($lockedYacht);
            if ($amount < $minimum) {
                throw ValidationException::withMessages([
                    'amount' => 'Bid must be at least ' . number_format($minimum, 2, '.', ''),
                ]);
            }

            $leadingBids = Bid::query()
                ->where('yacht_id', $lockedYacht->id)
                ->where('status', 'leading');

            if ($session) {
                $leadingBids->where('auction_session_id', $session->id);
            } else {
                $leadingBids->whereNull('auction_session_id');
            }

            $leadingBids->update(['status' => 'outbid']);

            $bid = Bid::create([
                'yacht_id' => $lockedYacht->id,
                'auction_session_id' => $session?->id,
                'bidder_id' => $bidder->id,
                'location_id' => $this->resolveLocationId($lockedYacht),
                'amount' => $amount,
                'status' => 'leading',
                'bidder_name' => $bidder->full_name,
                'bidder_email' => $bidder->email,
                'bidder_phone' => $bidder->phone,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            $lockedYacht->current_bid = $amount;

            if ($session) {
                $this->applyBidToSession($session, $lockedYacht, $bid);
            }

            $lockedYacht->save();

            return $bid;
        });

        $freshYacht = Yacht::find($yacht->id) ?? $yacht;
        event(new AuctionBidPlaced($freshYacht->id, [
            'bid' => $this->publicBid($bid->fresh()),
            'auction' => $this->publicState($freshYacht, null, true, 10, false),
        ]));

        return $bid->fresh();
    }

    public function startLiveAuction(Yacht $yacht, User $actor, ?int $durationMinutes = null): AuctionSession
    {
        $session = DB::transaction(function () use ($yacht, $actor, $durationMinutes) {
            $lockedYacht = Yacht::with('owner')->whereKey($yacht->id)->lockForUpdate()->firstOrFail();
            $now = now();
            $duration = $this->durationMinutes($lockedYacht, $durationMinutes);
            $endTime = $now->copy()->addMinutes($duration);
            $activeSession = AuctionSession::query()
                ->where('yacht_id', $lockedYacht->id)
                ->where('status', self::SESSION_ACTIVE)
                ->latest('id')
                ->lockForUpdate()
                ->first();

            $leadingBid = Bid::query()
                ->where('yacht_id', $lockedYacht->id)
                ->where('status', 'leading')
                ->latest('id')
                ->first();

            if ($activeSession) {
                $activeSession->start_time = $now;
                $activeSession->end_time = $endTime;
                $activeSession->status = self::SESSION_ACTIVE;
                $activeSession->location_id = $this->resolveLocationId($lockedYacht);
                $activeSession->started_by = $actor->id;
                $activeSession->highest_bid = $leadingBid?->amount ?? $lockedYacht->current_bid;
                $activeSession->highest_bidder_id = $leadingBid?->bidder_id;
                $activeSession->save();
                $session = $activeSession;
            } else {
                $session = AuctionSession::create([
                    'yacht_id' => $lockedYacht->id,
                    'location_id' => $this->resolveLocationId($lockedYacht),
                    'highest_bid' => $leadingBid?->amount ?? $lockedYacht->current_bid,
                    'highest_bidder_id' => $leadingBid?->bidder_id,
                    'started_by' => $actor->id,
                    'start_time' => $now,
                    'end_time' => $endTime,
                    'status' => self::SESSION_ACTIVE,
                ]);
            }

            $lockedYacht->auction_enabled = true;
            $lockedYacht->auction_mode = self::MODE_LIVE;
            $lockedYacht->allow_bidding = true;
            $lockedYacht->auction_start = $now;
            $lockedYacht->auction_end = $endTime;
            $lockedYacht->auction_duration_minutes = $duration;
            $lockedYacht->save();

            return $session;
        });

        $freshYacht = Yacht::find($yacht->id) ?? $yacht;
        event(new AuctionStateUpdated($freshYacht->id, [
            'auction' => $this->publicState($freshYacht, null, true, 10, false),
        ]));

        return $session->fresh();
    }

    public function endLiveAuction(Yacht $yacht, ?User $actor = null): ?AuctionSession
    {
        $session = DB::transaction(function () use ($yacht, $actor) {
            $lockedYacht = Yacht::with('owner')->whereKey($yacht->id)->lockForUpdate()->firstOrFail();
            $session = AuctionSession::query()
                ->where('yacht_id', $lockedYacht->id)
                ->whereIn('status', [self::SESSION_ACTIVE, self::SESSION_SCHEDULED])
                ->latest('id')
                ->lockForUpdate()
                ->first();

            if (! $session) {
                $lockedYacht->auction_end = now();
                $lockedYacht->save();

                return null;
            }

            return $this->finalizeSession($session, $lockedYacht, $actor);
        });

        $freshYacht = Yacht::find($yacht->id) ?? $yacht;
        event(new AuctionStateUpdated($freshYacht->id, [
            'auction' => $this->publicState($freshYacht, null, true, 10, false),
        ]));

        return $session?->fresh();
    }

    public function assertLocationMatches(Yacht $yacht, ?int $locationId): void
    {
        if ($locationId === null) {
            return;
        }

        if ($this->resolveLocationId($yacht) !== $locationId) {
            throw ValidationException::withMessages([
                'location_id' => 'Boat does not belong to the provided location.',
            ]);
        }
    }

    public function resolveLocationId(Yacht $yacht): ?int
    {
        if ($yacht->location_id) {
            return (int) $yacht->location_id;
        }

        if ($yacht->ref_harbor_id) {
            return (int) $yacht->ref_harbor_id;
        }

        if ($yacht->relationLoaded('owner') && $yacht->owner?->client_location_id) {
            return (int) $yacht->owner->client_location_id;
        }

        if (! $yacht->user_id) {
            return null;
        }

        return (int) User::query()->whereKey($yacht->user_id)->value('client_location_id');
    }

    private function latestSession(Yacht $yacht): ?AuctionSession
    {
        return AuctionSession::query()
            ->with('highestBidder')
            ->where('yacht_id', $yacht->id)
            ->latest('id')
            ->first();
    }

    private function synchronizeLiveAuction(Yacht $yacht): void
    {
        if ($this->resolveMode($yacht) !== self::MODE_LIVE) {
            return;
        }

        DB::transaction(function () use ($yacht) {
            $lockedYacht = Yacht::with('owner')->whereKey($yacht->id)->lockForUpdate()->first();
            if (! $lockedYacht) {
                return;
            }

            $this->synchronizeLockedLiveSession($lockedYacht);
        });
    }

    private function synchronizeLockedLiveSession(Yacht $lockedYacht): ?AuctionSession
    {
        $now = now();
        $session = AuctionSession::query()
            ->where('yacht_id', $lockedYacht->id)
            ->latest('id')
            ->lockForUpdate()
            ->first();

        if ($session && $session->status === self::SESSION_ACTIVE && $session->end_time && $session->end_time->lte($now)) {
            $session = $this->finalizeSession($session, $lockedYacht, null);
        }

        if ($session && $session->status === self::SESSION_ACTIVE) {
            return $session;
        }

        if (! $lockedYacht->auction_enabled || $this->resolveMode($lockedYacht) !== self::MODE_LIVE) {
            return $session;
        }

        if ($lockedYacht->auction_start && $lockedYacht->auction_start->gt($now)) {
            return $session;
        }

        $scheduledEnd = $lockedYacht->auction_end;
        if ($scheduledEnd && $scheduledEnd->lte($now)) {
            return $session;
        }

        if ($session && $session->status === self::SESSION_ENDED) {
            $sameWindow = $lockedYacht->auction_start && $session->start_time && $lockedYacht->auction_start->equalTo($session->start_time);
            if ($sameWindow) {
                return $session;
            }
        }

        $leadingBid = Bid::query()
            ->where('yacht_id', $lockedYacht->id)
            ->where('status', 'leading')
            ->latest('id')
            ->first();

        $startTime = $lockedYacht->auction_start ?? $now;
        $endTime = $scheduledEnd ?: $startTime->copy()->addMinutes($this->durationMinutes($lockedYacht));

        $session = AuctionSession::create([
            'yacht_id' => $lockedYacht->id,
            'location_id' => $this->resolveLocationId($lockedYacht),
            'highest_bid' => $leadingBid?->amount ?? $lockedYacht->current_bid,
            'highest_bidder_id' => $leadingBid?->bidder_id,
            'start_time' => $startTime,
            'end_time' => $endTime,
            'status' => self::SESSION_ACTIVE,
        ]);

        $lockedYacht->allow_bidding = true;
        $lockedYacht->auction_end = $endTime;
        $lockedYacht->save();

        return $session;
    }

    private function applyBidToSession(AuctionSession $session, Yacht $lockedYacht, Bid $bid): void
    {
        $session->status = self::SESSION_ACTIVE;
        $session->highest_bid = $bid->amount;
        $session->highest_bidder_id = $bid->bidder_id;
        $session->last_bid_at = $bid->created_at ?? now();
        $session->total_bids = Bid::query()->where('auction_session_id', $session->id)->count();
        $session->unique_bidders = Bid::query()->where('auction_session_id', $session->id)->distinct('bidder_id')->count('bidder_id');

        $extensionWindow = 60;
        $extensionSeconds = max(1, (int) ($lockedYacht->auction_extension_seconds ?: 60));

        if ($session->end_time && now()->addSeconds($extensionWindow)->gte($session->end_time)) {
            $session->end_time = $session->end_time->copy()->addSeconds($extensionSeconds);
            $session->extension_count = (int) $session->extension_count + 1;
            $lockedYacht->auction_end = $session->end_time;
        }

        $session->save();
    }

    private function finalizeSession(AuctionSession $session, Yacht $lockedYacht, ?User $actor): AuctionSession
    {
        $winningBid = Bid::query()
            ->where('auction_session_id', $session->id)
            ->orderByDesc('amount')
            ->orderByDesc('id')
            ->first();

        if ($winningBid) {
            Bid::query()
                ->where('auction_session_id', $session->id)
                ->where('id', '!=', $winningBid->id)
                ->update(['status' => 'lost']);

            $winningBid->status = 'won';
            $winningBid->save();

            $session->highest_bid = $winningBid->amount;
            $session->highest_bidder_id = $winningBid->bidder_id;
        }

        $session->status = self::SESSION_ENDED;
        $session->ended_by = $actor?->id;
        $session->end_time = $session->end_time && $session->end_time->lte(now())
            ? $session->end_time
            : now();
        $session->save();

        $lockedYacht->auction_end = $session->end_time;
        $lockedYacht->current_bid = $session->highest_bid ?? $lockedYacht->current_bid;
        $lockedYacht->save();

        return $session;
    }

    private function determinePublicStatus(Yacht $yacht, ?AuctionSession $session, string $mode): string
    {
        if ($mode === self::MODE_OFF) {
            return 'disabled';
        }

        if ($mode === self::MODE_BIDS) {
            return 'open';
        }

        if ($session) {
            return match ($session->status) {
                self::SESSION_ACTIVE => 'active',
                self::SESSION_ENDED => 'ended',
                self::SESSION_CANCELLED => 'cancelled',
                default => 'scheduled',
            };
        }

        if ($yacht->auction_start && $yacht->auction_start->isFuture()) {
            return 'scheduled';
        }

        if ($yacht->auction_end && $yacht->auction_end->lte(now())) {
            return 'ended';
        }

        return 'scheduled';
    }

    private function publicSession(AuctionSession $session): array
    {
        return [
            'id' => $session->id,
            'status' => $session->status,
            'start_time' => $session->start_time?->toISOString(),
            'end_time' => $session->end_time?->toISOString(),
            'highest_bid' => $session->highest_bid !== null ? (float) $session->highest_bid : null,
            'highest_bidder' => $session->highestBidder ? $this->publicBidderAlias($session->highestBidder->full_name) : null,
            'last_bid_at' => $session->last_bid_at?->toISOString(),
            'extension_count' => (int) $session->extension_count,
            'total_bids' => (int) $session->total_bids,
            'unique_bidders' => (int) $session->unique_bidders,
        ];
    }

    private function publicWinner(?AuctionSession $session): ?array
    {
        if (! $session || $session->status !== self::SESSION_ENDED || ! $session->highestBidder) {
            return null;
        }

        return [
            'bidder' => $this->publicBidderAlias($session->highestBidder->full_name),
            'amount' => $session->highest_bid !== null ? (float) $session->highest_bid : null,
        ];
    }

    private function publicBidderAlias(?string $name): ?string
    {
        $clean = trim((string) $name);
        if ($clean === '') {
            return null;
        }

        $parts = preg_split('/\s+/', $clean) ?: [];
        $first = $parts[0] ?? '';
        $suffix = isset($parts[1]) ? strtoupper(substr($parts[1], 0, 1)) : '';

        return trim($first . $suffix);
    }

    private function durationMinutes(Yacht $yacht, ?int $override = null): int
    {
        $duration = $override ?? $yacht->auction_duration_minutes ?? 10;

        return max(1, min((int) $duration, 1440));
    }

    private function secondsUntil($dateTime): int
    {
        if (! $dateTime) {
            return 0;
        }

        return max(0, now()->diffInSeconds($dateTime, false));
    }
}
