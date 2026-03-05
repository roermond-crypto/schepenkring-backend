<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Yacht;
use App\Models\WalletLedger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SecurityAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_cannot_update_another_users_yacht(): void
    {
        $owner = User::factory()->create([
            'role' => 'Partner',
            'status' => 'Active',
        ]);
        $other = User::factory()->create([
            'role' => 'Partner',
            'status' => 'Active',
        ]);

        Yacht::unguard();
        $yacht = Yacht::create([
            'user_id' => $owner->id,
            'ref_harbor_id' => $owner->id,
            'name' => 'Test Yacht',
            'boat_name' => 'Test Yacht',
            'vessel_id' => 'TEST-123',
            'status' => 'Draft',
            'price' => 100000,
            'year' => '2024',
            'length' => '10m',
        ]);
        Yacht::reguard();

        Sanctum::actingAs($other);

        $response = $this->putJson('/api/yachts/' . $yacht->id, [
            'boat_name' => 'Hacked Yacht',
        ]);

        $response->assertStatus(403);
    }

    public function test_user_cannot_view_other_users_wallet_ledger(): void
    {
        $owner = User::factory()->create([
            'role' => 'Partner',
            'status' => 'Active',
        ]);
        $other = User::factory()->create([
            'role' => 'Customer',
            'status' => 'Active',
        ]);

        WalletLedger::create([
            'user_id' => $owner->id,
            'type' => WalletLedger::TYPE_LISTING_FEE,
            'amount' => 99.99,
            'currency' => 'EUR',
            'reference_type' => 'test',
            'reference_id' => 1,
            'reference_key' => 'seed',
        ]);

        Sanctum::actingAs($other);

        $response = $this->getJson('/api/wallets/' . $owner->id . '/ledger');

        $response->assertStatus(403);
    }
}
