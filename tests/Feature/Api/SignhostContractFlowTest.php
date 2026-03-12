<?php

use App\Enums\UserStatus;
use App\Enums\UserType;
use App\Models\Location;
use App\Models\SignRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\UploadedFile;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

test('contract generate endpoint can continue into signhost and return a sign url', function () {
    Storage::fake('public');

    config()->set('services.signhost.base_url', 'https://api.signhost.com/api/');
    config()->set('services.signhost.app_key', 'signhost-app-key');
    config()->set('services.signhost.user_token', 'signhost-user-token');

    $location = Location::create([
        'name' => 'Schepenkring Marina',
        'code' => 'SKM',
        'status' => 'ACTIVE',
    ]);

    $admin = User::factory()->create([
        'type' => UserType::ADMIN,
        'status' => UserStatus::ACTIVE,
    ]);

    Http::fake(function ($request) {
        return match ($request->method().' '.$request->url()) {
            'POST https://api.signhost.com/api/transaction' => Http::response([
                'Id' => 'txn-generic-123',
            ], 200),
            'PUT https://api.signhost.com/api/transaction/txn-generic-123/file/Contract.pdf' => Http::response('', 200),
            'PUT https://api.signhost.com/api/transaction/txn-generic-123/start' => Http::response('', 200),
            'GET https://api.signhost.com/api/transaction/txn-generic-123' => Http::response([
                'Id' => 'txn-generic-123',
                'Signers' => [[
                    'SignUrl' => 'https://signhost.app/sign/generic-link',
                ]],
            ], 200),
            default => Http::response(['message' => 'Unexpected Signhost call'], 500),
        };
    });

    Sanctum::actingAs($admin);

    $uploadedPdf = UploadedFile::fake()->createWithContent(
        'signed-contract.pdf',
        "%PDF-1.4\nUploaded contract from frontend\n%%EOF"
    );

    $response = $this
        ->withHeader('Idempotency-Key', 'signhost-generate-send-generic-1')
        ->withHeader('Accept', 'application/json')
        ->post('/api/contracts/generate', [
            'entity_type' => 'Deal',
            'entity_id' => 99,
            'location_id' => $location->id,
            'title' => 'Purchase Contract',
            'send_to_signhost' => true,
            'recipients' => [[
                'name' => 'Buyer One',
                'email' => 'buyer@example.test',
                'role' => 'buyer',
            ]],
            'reference' => 'deal-99-contract',
            'pdf' => $uploadedPdf,
        ]);

    $response->assertOk()
        ->assertJsonPath('message', 'Contract generated and Signhost request created')
        ->assertJsonPath('sign_url', 'https://signhost.app/sign/generic-link')
        ->assertJsonPath('sign_request.signhost_transaction_id', 'txn-generic-123')
        ->assertJsonPath('sign_request.status', 'SENT');

    $signRequest = SignRequest::query()->first();

    expect($signRequest)->not->toBeNull();
    expect($signRequest->entity_type)->toBe('Deal');
    expect($signRequest->entity_id)->toBe(99);
    expect($signRequest->status)->toBe('SENT');
    expect($signRequest->signhost_transaction_id)->toBe('txn-generic-123');
    expect($signRequest->sign_url)->toBe('https://signhost.app/sign/generic-link');
    expect(data_get($signRequest->metadata, 'contract_source'))->toBe('upload');
    expect(data_get($signRequest->metadata, 'contract_original_filename'))->toBe('signed-contract.pdf');
    expect(data_get($signRequest->metadata, 'contract_pdf_path'))->not->toBeNull();

    Storage::disk('public')->assertExists((string) data_get($signRequest->metadata, 'contract_pdf_path'));
    expect(Storage::disk('public')->get((string) data_get($signRequest->metadata, 'contract_pdf_path')))
        ->toBe("%PDF-1.4\nUploaded contract from frontend\n%%EOF");
});

test('deal contract generate endpoint can continue into signhost and return a sign url', function () {
    Storage::fake('public');

    config()->set('services.signhost.base_url', 'https://api.signhost.com/api/');
    config()->set('services.signhost.app_key', 'signhost-app-key');
    config()->set('services.signhost.user_token', 'signhost-user-token');

    $location = Location::create([
        'name' => 'Schepenkring Marina',
        'code' => 'SKM',
        'status' => 'ACTIVE',
    ]);

    $admin = User::factory()->create([
        'type' => UserType::ADMIN,
        'status' => UserStatus::ACTIVE,
    ]);

    Http::fake(function ($request) {
        return match ($request->method().' '.$request->url()) {
            'POST https://api.signhost.com/api/transaction' => Http::response([
                'Id' => 'txn-123',
            ], 200),
            'PUT https://api.signhost.com/api/transaction/txn-123/file/Contract.pdf' => Http::response('', 200),
            'PUT https://api.signhost.com/api/transaction/txn-123/start' => Http::response('', 200),
            'GET https://api.signhost.com/api/transaction/txn-123' => Http::response([
                'Id' => 'txn-123',
                'Signers' => [[
                    'SignUrl' => 'https://signhost.app/sign/buyer-link',
                ]],
            ], 200),
            default => Http::response(['message' => 'Unexpected Signhost call'], 500),
        };
    });

    Sanctum::actingAs($admin);

    $response = $this
        ->withHeader('Idempotency-Key', 'signhost-generate-send-1')
        ->postJson("/api/deals/42/contract/generate", [
            'location_id' => $location->id,
            'title' => 'Purchase Contract',
            'send_to_signhost' => true,
            'recipients' => [[
                'name' => 'Buyer One',
                'email' => 'buyer@example.test',
                'role' => 'buyer',
            ]],
            'reference' => 'deal-42-contract',
        ]);

    $response->assertOk()
        ->assertJsonPath('message', 'Contract generated and Signhost request created')
        ->assertJsonPath('sign_url', 'https://signhost.app/sign/buyer-link')
        ->assertJsonPath('sign_request.signhost_transaction_id', 'txn-123')
        ->assertJsonPath('sign_request.status', 'SENT')
        ->assertJsonPath('transaction.signing_url_buyer', 'https://signhost.app/sign/buyer-link');

    $signRequest = SignRequest::query()->first();

    expect($signRequest)->not->toBeNull();
    expect($signRequest->entity_type)->toBe('Deal');
    expect($signRequest->entity_id)->toBe(42);
    expect($signRequest->status)->toBe('SENT');
    expect($signRequest->signhost_transaction_id)->toBe('txn-123');
    expect($signRequest->sign_url)->toBe('https://signhost.app/sign/buyer-link');
    expect(data_get($signRequest->metadata, 'contract_pdf_path'))->not->toBeNull();

    Storage::disk('public')->assertExists((string) data_get($signRequest->metadata, 'contract_pdf_path'));
});
