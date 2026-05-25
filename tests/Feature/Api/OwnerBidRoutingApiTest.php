<?php

namespace Tests\Feature\Api;

use App\Enums\UserStatus;
use App\Enums\UserType;
use App\Models\Conversation;
use App\Models\Deal;
use App\Models\Location;
use App\Models\Message;
use App\Models\OwnerBid;
use App\Models\User;
use App\Models\Yacht;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OwnerBidRoutingApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_broker_routing_assigns_bid_notifies_staff_and_respects_seller_notification_setting(): void
    {
        $location = Location::create([
            'name' => 'Broker Harbor',
            'code' => 'BH',
            'status' => 'ACTIVE',
            'bids_page_enabled' => true,
            'seller_bid_notifications_enabled' => false,
            'direct_buyer_seller_chat_enabled' => false,
            'bid_routing_mode' => 'broker',
        ]);

        $seller = $this->user(UserType::SELLER, ['client_location_id' => $location->id]);
        $buyer = $this->user(UserType::BUYER, ['client_location_id' => $location->id]);
        $broker = $this->user(UserType::EMPLOYEE);
        $admin = $this->user(UserType::ADMIN);
        $broker->locations()->attach($location->id, ['role' => 'broker', 'active' => true]);

        $yacht = $this->createYacht($seller, $location, [
            'boat_name' => 'Broker Routed Yacht',
            'min_bid_amount' => 50000,
            'price' => 75000,
        ]);

        Sanctum::actingAs($buyer);

        $this->postJson('/api/owner-bids', [
            'yacht_id' => $yacht->id,
            'amount' => 40000,
            'message' => 'Can we talk?',
        ])
            ->assertCreated()
            ->assertJsonPath('bid.status', 'broker_review')
            ->assertJsonPath('bid.routing_mode', 'broker')
            ->assertJsonPath('bid.assigned_broker_id', $broker->id)
            ->assertJsonPath('warning', 'Bid is below the configured minimum bid amount.');

        $bid = OwnerBid::firstOrFail();

        $this->assertSame($location->id, $bid->location_id);
        $this->assertSame($broker->id, $bid->assigned_broker_id);
        $this->assertNotNull($bid->expires_at);
        $this->assertStringContainsString('below the configured minimum', $bid->ai_summary);

        $this->assertDatabaseMissing('app_notifications', [
            'user_id' => $seller->id,
            'type' => 'new_bid',
        ]);
        $this->assertDatabaseHas('app_notifications', [
            'user_id' => $admin->id,
            'type' => 'new_owner_bid',
            'severity' => 'warning',
        ]);
        $this->assertDatabaseHas('app_notifications', [
            'user_id' => $broker->id,
            'type' => 'broker_owner_bid_assigned',
        ]);

        $conversation = Conversation::firstOrFail();
        $this->assertSame('owner_bid', $conversation->channel_origin);
        $this->assertSame($broker->id, $conversation->assigned_employee_id);

        $message = Message::firstOrFail();
        $this->assertSame('system', $message->sender_type);
        $this->assertSame('owner_bid.created', $message->metadata['event'] ?? null);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'owner_bid.created',
            'target_id' => $bid->id,
        ]);
    }

    public function test_seller_counter_can_be_accepted_by_buyer_and_creates_deal(): void
    {
        $location = Location::create([
            'name' => 'Direct Harbor',
            'code' => 'DH',
            'status' => 'ACTIVE',
            'bids_page_enabled' => true,
            'seller_bid_notifications_enabled' => true,
            'direct_buyer_seller_chat_enabled' => true,
            'bid_routing_mode' => 'direct',
        ]);

        $seller = $this->user(UserType::SELLER, ['client_location_id' => $location->id]);
        $buyer = $this->user(UserType::BUYER, ['client_location_id' => $location->id]);
        $yacht = $this->createYacht($seller, $location, [
            'boat_name' => 'Counter Yacht',
            'min_bid_amount' => 30000,
        ]);

        Sanctum::actingAs($buyer);
        $this->postJson('/api/owner-bids', [
            'yacht_id' => $yacht->id,
            'amount' => 32000,
        ])->assertCreated();

        $offer = OwnerBid::firstOrFail();
        $this->assertDatabaseHas('app_notifications', [
            'user_id' => $seller->id,
            'type' => 'new_bid',
        ]);

        Sanctum::actingAs($seller);
        $this->postJson("/api/owner-bids/{$offer->id}/counter", [
            'amount' => 35000,
            'message' => 'Meet me here.',
        ])
            ->assertOk()
            ->assertJsonPath('bid.type', 'counter')
            ->assertJsonPath('bid.status', 'pending');

        $counter = OwnerBid::query()->where('type', 'counter')->firstOrFail();

        Sanctum::actingAs($buyer);
        $this->postJson("/api/owner-bids/{$counter->id}/accept-counter")
            ->assertOk()
            ->assertJsonPath('deal_id', 1)
            ->assertJsonPath('bid.status', 'accepted');

        $this->assertDatabaseHas('deals', [
            'owner_bid_id' => $counter->id,
            'buyer_id' => $buyer->id,
            'seller_id' => $seller->id,
            'status' => 'active',
        ]);

        $this->assertFalse((bool) $yacht->fresh()->allow_bidding);
        $this->assertFalse((bool) $yacht->fresh()->auction_enabled);
        $this->assertSame(35000.0, (float) $yacht->fresh()->current_bid);
        $this->assertSame('accepted', $offer->fresh()->status);
        $this->assertSame('accepted', $counter->fresh()->status);
        $this->assertSame(1, Deal::query()->count());
        $this->assertGreaterThanOrEqual(3, Message::query()->where('sender_type', 'system')->count());
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'owner_bid.counter_accepted',
            'target_id' => $counter->id,
        ]);
    }

    public function test_admin_can_list_assign_and_pause_owner_bids(): void
    {
        $location = Location::create([
            'name' => 'Admin Harbor',
            'code' => 'AH',
            'status' => 'ACTIVE',
            'bids_page_enabled' => true,
            'seller_bid_notifications_enabled' => true,
            'direct_buyer_seller_chat_enabled' => false,
            'bid_routing_mode' => 'admin_review',
        ]);

        $seller = $this->user(UserType::SELLER, ['client_location_id' => $location->id]);
        $buyer = $this->user(UserType::BUYER, ['client_location_id' => $location->id]);
        $admin = $this->user(UserType::ADMIN);
        $broker = $this->user(UserType::EMPLOYEE);
        $broker->locations()->attach($location->id, ['role' => 'broker', 'active' => true]);

        $yacht = $this->createYacht($seller, $location, ['boat_name' => 'Admin Review Yacht']);

        Sanctum::actingAs($buyer);
        $this->postJson('/api/owner-bids', [
            'yacht_id' => $yacht->id,
            'amount' => 20000,
        ])->assertCreated();

        $bid = OwnerBid::firstOrFail();
        $this->assertSame('admin_review', $bid->status);

        Sanctum::actingAs($admin);
        $this->getJson('/api/admin/owner-bids?status=admin_review')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $bid->id);

        $this->patchJson("/api/admin/owner-bids/{$bid->id}", [
            'assigned_broker_id' => $broker->id,
            'status' => 'broker_review',
            'admin_notes' => 'Broker should review the offer.',
        ])
            ->assertOk()
            ->assertJsonPath('bid.assigned_broker_id', $broker->id)
            ->assertJsonPath('bid.status', 'broker_review');

        $this->postJson("/api/admin/owner-bids/{$bid->id}/pause", [
            'admin_notes' => 'Waiting for identity check.',
        ])
            ->assertOk()
            ->assertJsonPath('bid.status', 'paused')
            ->assertJsonPath('bid.admin_notes', 'Waiting for identity check.');

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'owner_bid.paused',
            'target_id' => $bid->id,
        ]);
    }

    private function user(UserType $type, array $overrides = []): User
    {
        return User::factory()->create(array_merge([
            'type' => $type,
            'status' => UserStatus::ACTIVE,
        ], $overrides));
    }

    private function createYacht(User $seller, Location $location, array $overrides = []): Yacht
    {
        return Yacht::create(array_merge([
            'user_id' => $seller->id,
            'location_id' => $location->id,
            'ref_harbor_id' => $location->id,
            'vessel_id' => 'OB-' . Str::upper(Str::random(8)),
            'boat_name' => 'Owner Bid Yacht',
            'status' => 'For Sale',
            'price' => 50000,
            'allow_bidding' => true,
            'auction_enabled' => true,
            'auction_mode' => 'bids',
            'min_bid_amount' => 10000,
        ], $overrides));
    }
}
