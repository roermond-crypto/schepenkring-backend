<?php

use App\Enums\UserStatus;
use App\Enums\UserType;
use App\Mail\AdminClientOnboardingMail;
use App\Models\BuyerProfile;
use App\Models\BuyerVerification;
use App\Models\Location;
use App\Models\SellerOnboarding;
use App\Models\SellerProfile;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;

test('admin can create buyer who receives onboarding email', function () {
    Mail::fake();

    $admin = User::factory()->create(['type' => UserType::ADMIN, 'status' => UserStatus::ACTIVE]);
    $location = Location::query()->create(['name' => 'Schepenkring Lelystad', 'code' => 'LELYSTAD', 'status' => 'ACTIVE']);

    Sanctum::actingAs($admin);

    $response = $this->postJson('/api/admin/users', [
        'type' => UserType::BUYER->value,
        'name' => 'Buyer Client',
        'email' => 'buyer@example.test',
        'password' => 'SecurePass123!',
        'status' => UserStatus::ACTIVE->value,
        'location_id' => $location->id,
        'needs_onboarding' => true,
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.type', UserType::BUYER->value)
        ->assertJsonPath('data.role', 'buyer');

    $userId = $response->json('data.id');
    expect(User::query()->findOrFail($userId)->email_verified_at)->not->toBeNull();
    expect(BuyerProfile::query()->where('user_id', $userId)->exists())->toBeTrue();
    expect(BuyerVerification::query()->where('user_id', $userId)->value('status'))->toBe('CREATED');

    Mail::assertSent(AdminClientOnboardingMail::class, fn ($mail) => $mail->hasTo('buyer@example.test'));

    Sanctum::actingAs(User::query()->findOrFail($userId));

    $this->getJson('/api/buyer-verification/status')
        ->assertOk()
        ->assertJsonPath('data.next_step', 'profile');
});

test('admin can create seller as directly finished without onboarding email', function () {
    Mail::fake();

    $admin = User::factory()->create(['type' => UserType::ADMIN, 'status' => UserStatus::ACTIVE]);
    $location = Location::query()->create(['name' => 'Schepenkring Kortgene', 'code' => 'KORTGENE', 'status' => 'ACTIVE']);

    Sanctum::actingAs($admin);

    $response = $this->postJson('/api/admin/users', [
        'type' => UserType::SELLER->value,
        'name' => 'Seller Client',
        'email' => 'seller@example.test',
        'password' => 'SecurePass123!',
        'status' => UserStatus::ACTIVE->value,
        'location_id' => $location->id,
        'needs_onboarding' => false,
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.type', UserType::SELLER->value)
        ->assertJsonPath('data.role', 'seller');

    $userId = $response->json('data.id');
    $onboarding = SellerOnboarding::query()->where('user_id', $userId)->firstOrFail();

    expect(SellerProfile::query()->where('user_id', $userId)->exists())->toBeTrue();
    expect($onboarding->status)->toBe('APPROVED');
    expect($onboarding->decision)->toBe('approved');
    expect($onboarding->verified_at)->not->toBeNull();
    expect($onboarding->expires_at->isFuture())->toBeTrue();

    Mail::assertNotSent(AdminClientOnboardingMail::class);
});

test('headquarters is not accepted for client assignment or returned publicly', function () {
    $admin = User::factory()->create(['type' => UserType::ADMIN, 'status' => UserStatus::ACTIVE]);
    $hq = Location::query()->create(['name' => 'Headquarters', 'code' => 'HQ', 'status' => 'ACTIVE']);
    $branch = Location::query()->create(['name' => 'Schepenkring Heeg', 'code' => 'HEEG', 'status' => 'ACTIVE']);

    $this->getJson('/api/public/locations')
        ->assertOk()
        ->assertJsonMissing(['id' => $hq->id])
        ->assertJsonFragment(['id' => $branch->id]);

    Sanctum::actingAs($admin);

    $this->postJson('/api/admin/users', [
        'type' => UserType::BUYER->value,
        'name' => 'HQ Client',
        'email' => 'hq-client@example.test',
        'password' => 'SecurePass123!',
        'status' => UserStatus::ACTIVE->value,
        'location_id' => $hq->id,
        'needs_onboarding' => true,
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['location_id']);
});
