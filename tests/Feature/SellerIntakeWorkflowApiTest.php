<?php

namespace Tests\Feature;

use App\Models\BoatIntake;
use App\Models\BoatIntakePayment;
use App\Models\User;
use App\Services\MollieService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SellerIntakeWorkflowApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_client_can_create_intake_upload_photos_and_start_payment_session(): void
    {
        Storage::fake('public');
        app()->instance(MollieService::class, new class extends MollieService {
            public function __construct()
            {
            }

            public function createPayment(array $payload, ?string $idempotencyKey = null): array
            {
                return [
                    'id' => 'tr_seller_intake_test',
                    'status' => 'open',
                    '_links' => [
                        'checkout' => [
                            'href' => 'https://www.mollie.com/checkout/test',
                        ],
                    ],
                ];
            }
        });

        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $create = $this->postJson('/api/seller-intakes', []);
        $create->assertCreated()
            ->assertJsonPath('data.status', 'draft_intake');

        $intakeId = $create->json('data.id');

        $update = $this->putJson("/api/seller-intakes/{$intakeId}", [
            'brand' => 'Hallberg-Rassy',
            'model' => '342',
            'year' => 2019,
            'length_m' => 10.32,
            'width_m' => 3.42,
            'height_m' => 14.20,
            'fuel_type' => 'Diesel',
            'price' => 185000,
            'description' => 'Well maintained intake test boat.',
            'boat_type' => 'Sailing yacht',
        ]);

        $update->assertOk()
            ->assertJsonPath('data.status', 'awaiting_payment')
            ->assertJsonPath('data.brand', 'Hallberg-Rassy');

        $upload = $this->post("/api/seller-intakes/{$intakeId}/photos", [
            'photos' => [UploadedFile::fake()->image('boat.jpg', 1200, 800)],
        ]);

        $upload->assertOk()
            ->assertJsonPath('data.photo_count', 1);

        $payment = $this->postJson("/api/seller-intakes/{$intakeId}/payment/session", [
            'redirect_url' => 'https://example.test/dashboard/client/yachts/new',
        ]);

        $payment->assertOk()
            ->assertJsonPath('checkout_url', 'https://www.mollie.com/checkout/test')
            ->assertJsonPath('payment.mollie_payment_id', 'tr_seller_intake_test');

        $this->assertDatabaseHas('boat_intake_payments', [
            'boat_intake_id' => $intakeId,
            'mollie_payment_id' => 'tr_seller_intake_test',
            'status' => 'open',
        ]);
    }

    public function test_mollie_paid_webhook_creates_draft_yacht_and_listing_workflow(): void
    {
        app()->instance(MollieService::class, new class extends MollieService {
            public function __construct()
            {
            }

            public function getPayment(string $paymentId): array
            {
                return [
                    'id' => $paymentId,
                    'status' => 'paid',
                    'metadata' => [
                        'payment_type' => 'seller_listing_intake',
                        'boat_intake_id' => 1,
                    ],
                ];
            }
        });

        $user = User::factory()->create();
        $intake = BoatIntake::create([
            'user_id' => $user->id,
            'status' => 'awaiting_payment',
            'brand' => 'Contest',
            'model' => '42CS',
            'year' => 2020,
            'length_m' => 12.85,
            'width_m' => 4.15,
            'height_m' => 18.00,
            'fuel_type' => 'Diesel',
            'price' => 395000,
            'description' => 'Webhook flow test boat.',
            'boat_type' => 'Sailing yacht',
            'photo_manifest_json' => [
                [
                    'path' => 'boat-intakes/1/contest.jpg',
                    'url' => '/storage/boat-intakes/1/contest.jpg',
                    'original_name' => 'contest.jpg',
                ],
            ],
        ]);

        $payment = BoatIntakePayment::create([
            'boat_intake_id' => $intake->id,
            'user_id' => $user->id,
            'mollie_payment_id' => 'tr_paid_seller_intake',
            'status' => 'open',
            'amount_currency' => 'EUR',
            'amount_value' => 395,
        ]);

        $intake->forceFill(['latest_payment_id' => $payment->id])->save();

        $response = $this->postJson('/api/webhooks/mollie', [
            'id' => 'tr_paid_seller_intake',
        ]);

        $response->assertOk()
            ->assertJsonPath('message', 'ok');

        $this->assertDatabaseHas('boat_intake_payments', [
            'id' => $payment->id,
            'status' => 'paid',
        ]);
        $this->assertDatabaseHas('boat_intakes', [
            'id' => $intake->id,
            'status' => 'paid',
        ]);
        $this->assertDatabaseHas('yachts', [
            'user_id' => $user->id,
            'boat_name' => 'Contest 42CS',
            'status' => 'Draft',
        ]);
        $this->assertDatabaseHas('listing_workflows', [
            'boat_intake_id' => $intake->id,
            'user_id' => $user->id,
            'status' => 'paid',
        ]);
        $this->assertDatabaseHas('yacht_images', [
            'url' => 'boat-intakes/1/contest.jpg',
            'status' => 'approved',
        ]);
    }
}
