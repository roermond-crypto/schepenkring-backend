<?php

namespace Tests\Feature;

use App\Models\BoatChannelListing;
use App\Models\User;
use App\Models\Yacht;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BoatChannelListingApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_configure_and_run_marktplaats_channel_listing(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        Sanctum::actingAs($admin);

        $boat = Yacht::create([
            'user_id' => $admin->id,
            'boat_name' => 'Channel Boat',
            'status' => 'Draft',
        ]);

        $update = $this->putJson("/api/yachts/{$boat->id}/channel-listings/marktplaats", [
            'is_enabled' => true,
            'auto_publish' => true,
            'settings_json' => [
                'marktplaats_promoted' => true,
                'marktplaats_budget_type' => 'cpc',
                'marktplaats_cpc_bid' => 0.45,
                'marktplaats_target_views' => 500,
            ],
        ]);

        $update->assertOk()
            ->assertJsonPath('channel_name', 'marktplaats')
            ->assertJsonPath('is_enabled', true)
            ->assertJsonPath('auto_publish', true)
            ->assertJsonPath('status', 'queued')
            ->assertJsonPath('settings_json.marktplaats_cpc_bid', 0.45);

        $listing = BoatChannelListing::query()
            ->where('boat_id', $boat->id)
            ->where('channel_name', 'marktplaats')
            ->firstOrFail();

        $this->assertNotEmpty($listing->external_id);

        $this->postJson("/api/yachts/{$boat->id}/channel-listings/marktplaats/pause")
            ->assertOk()
            ->assertJsonPath('status', 'paused');

        $this->postJson("/api/yachts/{$boat->id}/channel-listings/marktplaats/sync")
            ->assertOk()
            ->assertJsonPath('status', 'ready');

        $index = $this->getJson("/api/yachts/{$boat->id}/channel-listings");

        $index->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.channel_name', 'marktplaats')
            ->assertJsonPath('0.capabilities.supports_cpc', true);

        $this->assertDatabaseHas('boat_channel_logs', [
            'boat_id' => $boat->id,
            'channel_name' => 'marktplaats',
            'action' => 'settings_update',
            'status' => 'success',
        ]);
    }
}
