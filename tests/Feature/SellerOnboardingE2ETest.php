<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SellerOnboardingE2ETest extends TestCase
{
    use RefreshDatabase;

    private User $seller;
    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seller = User::factory()->create([
            'name' => 'Bayliner Test Seller',
            'email' => 'bayliner@test.schepenkring.nl',
            'role' => 'seller',
        ]);

        $this->token = $this->seller->createToken('test')->plainTextToken;
    }

    public function test_full_seller_onboarding_bayliner_2855(): void
    {
        // Step 1: Start onboarding
        $this->withToken($this->token)
            ->postJson('/api/seller-onboarding/start')
            ->assertOk()
            ->assertJsonStructure(['message', 'data' => ['onboarding_id', 'next_step']]);

        // Step 2: Fill seller profile
        $this->withToken($this->token)
            ->putJson('/api/seller-onboarding/profile', [
                'seller_type' => 'private',
                'full_name' => 'Jan de Vries',
                'email' => 'jan@test.nl',
                'phone' => '+31612345678',
                'address_line_1' => 'Havenstraat 1',
                'city' => 'Roermond',
                'postal_code' => '6041 AA',
                'country' => 'NL',
                'birth_date' => '1975-06-15',
            ])
            ->assertOk()
            ->assertJsonPath('data.steps.profile', 'complete');

        // Step 3: Submit KYC answers
        $this->withToken($this->token)
            ->postJson('/api/seller-onboarding/kyc/answers', [
                'answers' => [
                    ['question_key' => 'boat_ownership', 'answer' => 'yes'],
                    ['question_key' => 'previous_sales', 'answer' => 'no'],
                ],
            ])
            ->assertOk();

        // Step 4: Submit onboarding (no payment/contract gate)
        $this->withToken($this->token)
            ->postJson('/api/seller-onboarding/submit')
            ->assertOk()
            ->assertJsonStructure(['message', 'data']);

        // Step 5: Create AI draft from description
        $this->withToken($this->token)
            ->postJson('/api/onboarding/ai-draft', [
                'description' => '1997 Bayliner 2855, white hull, twin Mercruiser engines, GPS, VHF, full canopy, well maintained.',
            ])
            ->assertStatus(201)
            ->assertJsonStructure(['draft_id', 'yacht', 'confidence']);
    }

    public function test_quick_register_creates_seller_account(): void
    {
        $this->postJson('/api/onboarding/quick-register', [
            'name' => 'Pieter Boat',
            'email' => 'pieter@boat.nl',
        ])
            ->assertStatus(201)
            ->assertJsonStructure(['user_id', 'token', 'message']);
    }

    public function test_onboarding_status_returns_simplified_steps(): void
    {
        $this->withToken($this->token)
            ->postJson('/api/seller-onboarding/start')
            ->assertOk();

        $this->withToken($this->token)
            ->getJson('/api/seller-onboarding/status')
            ->assertOk()
            ->assertJsonStructure(['data' => ['steps' => ['profile', 'kyc']]])
            ->assertJsonMissing(['payment_status'])
            ->assertJsonMissing(['contract_status']);
    }
}
