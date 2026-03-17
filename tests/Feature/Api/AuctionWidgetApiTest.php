<?php

namespace Tests\Feature\Api;

use App\Enums\UserStatus;
use App\Enums\UserType;
use App\Events\AuctionBidPlaced;
use App\Events\AuctionStateUpdated;
use App\Models\AuctionSession;
use App\Models\Bid;
use App\Models\BidSession;
use App\Models\Bidder;
use App\Models\Location;
use App\Models\User;
use App\Models\Yacht;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AuctionWidgetApiTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_public_bids_mode_exposes_shared_state_and_history(): void
    {
        Event::fake([AuctionBidPlaced::class, AuctionStateUpdated::class]);

        $location = Location::create([
            'name' => 'Harbor Alpha',
            'code' => 'HA',
            'status' => 'ACTIVE',
        ]);

        $owner = User::factory()->create([
            'type' => UserType::CLIENT,
            'status' => UserStatus::ACTIVE,
            'client_location_id' => $location->id,
        ]);

        $yacht = $this->createYacht($owner, $location, [
            'allow_bidding' => true,
            'auction_enabled' => true,
            'auction_mode' => 'bids',
            'min_bid_amount' => 15000,
        ]);

        $token = $this->createBidToken('Alice Adams', 'alice@example.test');

        $this->postJson("/api/public/boats/{$yacht->id}/bid", [
            'amount' => 15000,
            'location_id' => $location->id,
        ], [
            'X-Bid-Token' => $token,
            'Idempotency-Key' => 'auction-bid-1',
        ])
            ->assertStatus(201)
            ->assertJsonPath('bid.amount', 15000)
            ->assertJsonPath('bid.location_id', $location->id)
            ->assertJsonPath('auction.auction_mode', 'bids')
            ->assertJsonPath('auction.auction_status', 'open')
            ->assertJsonPath('auction.current_bid', 15000)
            ->assertJsonPath('auction.minimum_next_bid', 15500);

        $this->getJson("/api/public/boats/{$yacht->id}/auction?location_id={$location->id}")
            ->assertOk()
            ->assertJsonPath('boat_id', $yacht->id)
            ->assertJsonPath('location_id', $location->id)
            ->assertJsonPath('auction_mode', 'bids')
            ->assertJsonPath('bid_count', 1)
            ->assertJsonPath('recent_bids.0.bidder', 'AliceA');

        $this->getJson("/api/public/boats/{$yacht->id}/bids?location_id={$location->id}")
            ->assertOk()
            ->assertJsonCount(1, 'bids')
            ->assertJsonPath('bids.0.amount', 15000)
            ->assertJsonPath('bids.0.bidder', 'AliceA')
            ->assertJsonMissingPath('bids.0.bidder_email')
            ->assertJsonMissingPath('bids.0.bidder_phone');

        Event::assertDispatched(AuctionBidPlaced::class);
    }

    public function test_live_auction_bid_creates_session_and_extends_timer(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-03-13 10:00:00'));
        Event::fake([AuctionBidPlaced::class, AuctionStateUpdated::class]);

        $location = Location::create([
            'name' => 'Harbor Beta',
            'code' => 'HB',
            'status' => 'ACTIVE',
        ]);

        $owner = User::factory()->create([
            'type' => UserType::CLIENT,
            'status' => UserStatus::ACTIVE,
            'client_location_id' => $location->id,
        ]);

        $yacht = $this->createYacht($owner, $location, [
            'allow_bidding' => true,
            'auction_enabled' => true,
            'auction_mode' => 'live',
            'auction_start' => now()->subMinutes(2),
            'auction_end' => now()->addSeconds(30),
            'auction_duration_minutes' => 10,
            'auction_extension_seconds' => 60,
            'min_bid_amount' => 20000,
        ]);

        $token = $this->createBidToken('Bob Brown', 'bob@example.test');

        $this->postJson("/api/public/boats/{$yacht->id}/bid", [
            'amount' => 20000,
            'location_id' => $location->id,
        ], [
            'X-Bid-Token' => $token,
            'Idempotency-Key' => 'auction-live-bid-1',
        ])
            ->assertStatus(201)
            ->assertJsonPath('bid.amount', 20000)
            ->assertJsonPath('auction.auction_mode', 'live')
            ->assertJsonPath('auction.auction_status', 'active')
            ->assertJsonPath('auction.session.extension_count', 1)
            ->assertJsonPath('auction.session.highest_bid', 20000);

        $session = AuctionSession::query()->firstOrFail();

        $this->assertSame('active', $session->status);
        $this->assertSame(20000.0, (float) $session->highest_bid);
        $this->assertSame(now()->addSeconds(90)->toDateTimeString(), $session->end_time?->toDateTimeString());

        Event::assertDispatched(AuctionBidPlaced::class);
    }

    public function test_admin_can_start_and_end_live_auction_and_finalize_winner(): void
    {
        Event::fake([AuctionBidPlaced::class, AuctionStateUpdated::class]);

        $location = Location::create([
            'name' => 'Harbor Gamma',
            'code' => 'HG',
            'status' => 'ACTIVE',
        ]);

        $owner = User::factory()->create([
            'type' => UserType::CLIENT,
            'status' => UserStatus::ACTIVE,
            'client_location_id' => $location->id,
        ]);

        $admin = User::factory()->create([
            'type' => UserType::ADMIN,
            'status' => UserStatus::ACTIVE,
        ]);

        $yacht = $this->createYacht($owner, $location, [
            'allow_bidding' => true,
            'auction_enabled' => true,
            'auction_mode' => 'live',
            'auction_duration_minutes' => 10,
            'min_bid_amount' => 10000,
        ]);

        Sanctum::actingAs($admin);

        $this->postJson("/api/admin/boats/{$yacht->id}/auction/start", [
            'duration_minutes' => 10,
        ])
            ->assertOk()
            ->assertJsonPath('auction.auction_status', 'active')
            ->assertJsonPath('auction.auction_mode', 'live');

        $aliceToken = $this->createBidToken('Alice Adams', 'alice2@example.test');
        $bobToken = $this->createBidToken('Bob Brown', 'bob2@example.test');

        $this->postJson("/api/public/boats/{$yacht->id}/bid", [
            'amount' => 10000,
            'location_id' => $location->id,
        ], [
            'X-Bid-Token' => $aliceToken,
            'Idempotency-Key' => 'auction-admin-bid-1',
        ])->assertCreated();

        $this->postJson("/api/public/boats/{$yacht->id}/bid", [
            'amount' => 10500,
            'location_id' => $location->id,
        ], [
            'X-Bid-Token' => $bobToken,
            'Idempotency-Key' => 'auction-admin-bid-2',
        ])->assertCreated();

        $this->postJson("/api/admin/boats/{$yacht->id}/auction/end")
            ->assertOk()
            ->assertJsonPath('auction.auction_status', 'ended')
            ->assertJsonPath('auction.winner.bidder', 'BobB')
            ->assertJsonPath('auction.winner.amount', 10500);

        $session = AuctionSession::query()->firstOrFail();

        $this->assertSame('ended', $session->status);
        $this->assertSame(1, Bid::query()->where('auction_session_id', $session->id)->where('status', 'won')->count());
        $this->assertSame(1, Bid::query()->where('auction_session_id', $session->id)->where('status', 'lost')->count());
        $this->assertSame(10500.0, (float) Bid::query()->where('auction_session_id', $session->id)->where('status', 'won')->firstOrFail()->amount);

        Event::assertDispatched(AuctionStateUpdated::class);
    }

    private function createYacht(User $owner, Location $location, array $overrides = []): Yacht
    {
        return Yacht::create(array_merge([
            'user_id' => $owner->id,
            'location_id' => $location->id,
            'ref_harbor_id' => $location->id,
            'vessel_id' => 'SK-' . Str::upper(Str::random(8)),
            'boat_name' => 'Auction Yacht',
            'status' => 'For Bid',
            'price' => 25000,
            'allow_bidding' => true,
            'auction_enabled' => false,
            'auction_mode' => null,
            'min_bid_amount' => 5000,
        ], $overrides));
    }

    private function createBidToken(string $name, string $email): string
    {
        $bidder = Bidder::create([
            'full_name' => $name,
            'address' => 'Dock 1',
            'postal_code' => '1000AA',
            'city' => 'Amsterdam',
            'phone' => '+3100000000',
            'email' => $email,
            'verified_at' => now(),
        ]);

        $token = Str::random(40);

        BidSession::create([
            'bidder_id' => $bidder->id,
            'token_hash' => hash('sha256', $token),
            'expires_at' => now()->addDay(),
            'last_used_at' => now(),
            'ip_address' => '127.0.0.1',
            'user_agent' => 'PHPUnit',
        ]);

        return $token;
    }
}
