<?php

use App\Enums\UserStatus;
use App\Enums\UserType;
use App\Models\BoatFieldChange;
use App\Models\Location;
use App\Models\User;
use App\Models\Yacht;
use App\Models\YachtAiExtraction;
use App\Services\BoatTaskAutomationService;
use App\Services\SyncYachtTasksService;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    app()->instance(SyncYachtTasksService::class, new class {
        public function syncForYacht(Yacht $yacht, ?User $actor = null): void
        {
        }
    });

    app()->instance(BoatTaskAutomationService::class, new class {
        public function fireForYacht(Yacht $yacht, User $actor, bool $isUpdate = false): array
        {
            return [];
        }
    });
});

function createClientWithLocation(): array
{
    $location = Location::create([
        'name' => 'Amsterdam Marina',
        'code' => 'AMS',
        'status' => 'ACTIVE',
    ]);

    $user = User::factory()->create([
        'type' => UserType::CLIENT,
        'status' => UserStatus::ACTIVE,
        'client_location_id' => $location->id,
        'first_name' => 'Jan',
        'last_name' => 'De Vries',
        'phone' => '+31612345678',
        'date_of_birth' => '1988-05-15',
        'address_line1' => 'Keizersgracht 1',
        'city' => 'Amsterdam',
        'postal_code' => '1015CJ',
        'country' => 'NL',
        'email_verified_at' => now(),
    ]);

    return [$user, $location];
}

test('it logs AI-assisted field corrections on yacht create', function () {
    [$user] = createClientWithLocation();
    Sanctum::actingAs($user);

    $sessionId = (string) Str::uuid();

    YachtAiExtraction::create([
        'session_id' => $sessionId,
        'user_id' => $user->id,
        'status' => 'completed',
        'model_name' => 'gemini-2.5-flash',
        'model_version' => 'gemini-2.5-flash',
        'normalized_fields_json' => [
            'boat_name' => 'AI Suggested Boat',
            'manufacturer' => 'Bayliner',
            'life_jackets' => 'yes',
        ],
        'field_confidence_json' => [
            'boat_name' => 0.88,
            'manufacturer' => 0.90,
            'life_jackets' => 0.61,
        ],
        'field_sources_json' => [
            'boat_name' => 'text',
            'manufacturer' => 'text',
            'life_jackets' => 'image',
        ],
        'extracted_at' => now(),
    ]);

    $response = $this->postJson('/api/yachts', [
        'boat_name' => 'Human Approved Boat',
        'manufacturer' => 'Bayliner',
        'life_jackets' => 'no',
        'status' => 'draft',
        'changed_by_type' => 'user',
        'source_type' => 'manual',
        'correction_label' => 'guessed_too_much',
        'field_correction_labels' => [
            'life_jackets' => 'wrong_image_detection',
        ],
        'change_reason' => 'No life jackets visible in reviewed images.',
        'ai_session_id' => $sessionId,
        'model_name' => 'gemini-2.5-flash',
        'field_confidence' => [
            'life_jackets' => 0.61,
        ],
    ]);

    $response->assertCreated();

    $yachtId = $response->json('id');

    $change = BoatFieldChange::query()
        ->where('yacht_id', $yachtId)
        ->where('field_name', 'life_jackets')
        ->first();

    expect($change)->not->toBeNull();
    expect($change->old_value)->toBeNull();
    expect($change->new_value)->toBe('"no"');
    expect($change->changed_by_type)->toBe('user');
    expect($change->changed_by_id)->toBe($user->id);
    expect($change->source_type)->toBe('manual');
    expect($change->ai_session_id)->toBe($sessionId);
    expect($change->model_name)->toBe('gemini-2.5-flash');
    expect($change->confidence_before)->toBe(0.61);
    expect($change->reason)->toBe('No life jackets visible in reviewed images.');
    expect($change->correction_label)->toBe('wrong_image_detection');
    expect($change->meta['scope'])->toBe('yacht_create');
    expect($change->meta['ai_proposed_value'])->toBe('yes');
    expect($change->meta['ai_field_source'])->toBe('image');
    expect($change->meta['model_version'])->toBe('gemini-2.5-flash');
});

test('it logs manual field diffs on yacht update without AI context', function () {
    [$user, $location] = createClientWithLocation();
    Sanctum::actingAs($user);

    $yacht = Yacht::create([
        'user_id' => $user->id,
        'ref_harbor_id' => $location->id,
        'vessel_id' => 'SK-UPDATE-001',
        'boat_name' => 'Update Test Yacht',
        'status' => 'draft',
    ]);
    $yacht->saveSubTables([
        'life_jackets' => 'yes',
    ]);

    $response = $this->patchJson("/api/yachts/{$yacht->id}", [
        'life_jackets' => 'unknown',
        'changed_by_type' => 'user',
        'source_type' => 'manual',
        'correction_label' => 'other',
        'change_reason' => 'Reviewer could not verify this equipment.',
    ]);

    $response->assertOk();

    $change = BoatFieldChange::query()
        ->where('yacht_id', $yacht->id)
        ->where('field_name', 'life_jackets')
        ->latest('id')
        ->first();

    expect($change)->not->toBeNull();
    expect($change->old_value)->toBe('"yes"');
    expect($change->new_value)->toBe('"unknown"');
    expect($change->changed_by_type)->toBe('user');
    expect($change->source_type)->toBe('manual');
    expect($change->ai_session_id)->toBeNull();
    expect($change->reason)->toBe('Reviewer could not verify this equipment.');
    expect($change->correction_label)->toBe('other');
    expect($change->meta['scope'])->toBe('yacht_update');
});
