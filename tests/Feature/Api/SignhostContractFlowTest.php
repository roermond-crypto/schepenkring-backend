<?php

use App\Enums\UserStatus;
use App\Enums\UserType;
use App\Models\Location;
use App\Models\SignDocument;
use App\Models\SignRequest;
use App\Models\User;
use App\Models\UserNotification;
use App\Models\WebhookEvent;
use App\Models\Yacht;
use App\Jobs\ProcessSignhostWebhookJob;
use App\Services\ActionSecurity;
use App\Services\NotificationDispatchService;
use App\Services\SignhostService;
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

    Http::assertSent(function ($request) {
        if ($request->method() !== 'POST' || $request->url() !== 'https://api.signhost.com/api/transaction') {
            return false;
        }

        $data = $request->data();

        return $request->hasHeader('Authorization', 'APIKey signhost-user-token')
            && $request->hasHeader('Application', 'APPKey signhost-app-key')
            && ! $request->hasHeader('X-Auth-Client-Id')
            && ! $request->hasHeader('X-Auth-Client-Token')
            && $data['Seal'] === false
            && $data['Reference'] === 'deal-99-contract'
            && $data['Signers'][0]['Email'] === 'buyer@example.test'
            && $data['Signers'][0]['SendSignRequest'] === true
            && $data['Signers'][0]['SignRequestMessage'] === 'Please review and sign this document.'
            && $data['Signers'][0]['Verifications'][0]['Type'] === 'Scribble'
            && $data['Signers'][0]['Verifications'][0]['ScribbleName'] === 'Buyer One';
    });

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

    Http::assertSent(function ($request) {
        if ($request->method() !== 'POST' || $request->url() !== 'https://api.signhost.com/api/transaction') {
            return false;
        }

        $data = $request->data();

        return $request->hasHeader('Authorization', 'APIKey signhost-user-token')
            && $request->hasHeader('Application', 'APPKey signhost-app-key')
            && ! $request->hasHeader('X-Auth-Client-Id')
            && ! $request->hasHeader('X-Auth-Client-Token')
            && $data['Seal'] === false
            && $data['Reference'] === 'deal-42-contract'
            && $data['Signers'][0]['Email'] === 'buyer@example.test'
            && $data['Signers'][0]['SendSignRequest'] === true
            && $data['Signers'][0]['SignRequestMessage'] === 'Please review and sign this document.'
            && $data['Signers'][0]['Verifications'][0]['Type'] === 'Scribble'
            && $data['Signers'][0]['Verifications'][0]['ScribbleName'] === 'Buyer One';
    });

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

test('client yacht responses expose latest signhost summary and yacht signhost notifications link back to the yacht page', function () {
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

    $client = User::factory()->create([
        'type' => UserType::CLIENT,
        'status' => UserStatus::ACTIVE,
        'client_location_id' => $location->id,
        'email' => 'client@example.test',
        'name' => 'Client One',
    ]);

    $yacht = Yacht::create([
        'user_id' => $client->id,
        'ref_harbor_id' => $location->id,
        'boat_name' => 'Blue Pearl',
        'status' => 'For Sale',
        'vessel_id' => 'SK-TEST-001',
        'allow_bidding' => false,
        'auction_enabled' => false,
    ]);

    $pdfPath = 'contracts/yacht-contract.pdf';
    $pdfContents = "%PDF-1.4\nYacht Contract\n%%EOF";
    Storage::disk('public')->put($pdfPath, $pdfContents);

    $signRequest = SignRequest::create([
        'location_id' => $location->id,
        'entity_type' => 'Yacht',
        'entity_id' => $yacht->id,
        'provider' => 'signhost',
        'status' => 'DRAFT',
        'requested_by_user_id' => $admin->id,
        'metadata' => [
            'recipients' => [[
                'name' => $client->name,
                'email' => $client->email,
                'role' => 'buyer',
            ]],
        ],
    ]);

    SignDocument::create([
        'sign_request_id' => $signRequest->id,
        'file_path' => $pdfPath,
        'sha256' => hash('sha256', $pdfContents),
        'type' => 'original',
    ]);

    Http::fake(function ($request) {
        return match ($request->method().' '.$request->url()) {
            'POST https://api.signhost.com/api/transaction' => Http::response([
                'Id' => 'txn-yacht-123',
            ], 200),
            'PUT https://api.signhost.com/api/transaction/txn-yacht-123/file/Contract.pdf' => Http::response('', 200),
            'PUT https://api.signhost.com/api/transaction/txn-yacht-123/start' => Http::response('', 200),
            'GET https://api.signhost.com/api/transaction/txn-yacht-123' => Http::response([
                'Id' => 'txn-yacht-123',
                'Signers' => [[
                    'SignUrl' => 'https://signhost.app/sign/client-buyer-link',
                ]],
            ], 200),
            default => Http::response(['message' => 'Unexpected Signhost call'], 500),
        };
    });

    Sanctum::actingAs($admin);

    $response = $this
        ->withHeader('Idempotency-Key', 'yacht-signhost-create-1')
        ->postJson("/api/yachts/{$yacht->id}/signhost/create", [
            'location_id' => $location->id,
            'recipients' => [[
                'name' => $client->name,
                'email' => $client->email,
                'role' => 'buyer',
            ]],
            'reference' => 'yacht-1-contract',
        ]);

    $response->assertOk()
        ->assertJsonPath('transaction.signing_url_buyer', 'https://signhost.app/sign/client-buyer-link')
        ->assertJsonPath('transaction.status', 'signing');

    $notification = UserNotification::query()
        ->where('user_id', $client->id)
        ->latest('id')
        ->first();

    expect($notification)->not->toBeNull();
    expect($notification?->notification)->not->toBeNull();
    expect(data_get($notification?->notification, 'data.url'))
        ->toBe("/dashboard/client/yachts/{$yacht->id}?step=6");
    expect(data_get($notification?->notification, 'data.sign_request_id'))
        ->toBe($signRequest->id);

    Sanctum::actingAs($client);

    $this->getJson("/api/yachts/{$yacht->id}")
        ->assertOk()
        ->assertJsonPath('latest_signhost.sign_request_id', $signRequest->id)
        ->assertJsonPath('latest_signhost.status', 'signing')
        ->assertJsonPath('latest_signhost.client_sign_url', 'https://signhost.app/sign/client-buyer-link');

    $this->getJson('/api/yachts')
        ->assertOk()
        ->assertJsonPath('0.latest_signhost.status', 'signing')
        ->assertJsonPath('0.latest_signhost.client_sign_url', 'https://signhost.app/sign/client-buyer-link');
});

test('signhost webhook updates yacht latest signhost summary to signed and keeps yacht notification links', function () {
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

    $client = User::factory()->create([
        'type' => UserType::CLIENT,
        'status' => UserStatus::ACTIVE,
        'client_location_id' => $location->id,
        'email' => 'client-signed@example.test',
        'name' => 'Client Signed',
    ]);

    $yacht = Yacht::create([
        'user_id' => $client->id,
        'ref_harbor_id' => $location->id,
        'boat_name' => 'Silver Tide',
        'status' => 'For Sale',
        'vessel_id' => 'SK-TEST-002',
        'allow_bidding' => false,
        'auction_enabled' => false,
    ]);

    $signRequest = SignRequest::create([
        'location_id' => $location->id,
        'entity_type' => 'Yacht',
        'entity_id' => $yacht->id,
        'provider' => 'signhost',
        'status' => 'SENT',
        'signhost_transaction_id' => 'txn-yacht-signed-1',
        'sign_url' => 'https://signhost.app/sign/client-original-link',
        'requested_by_user_id' => $admin->id,
        'metadata' => [
            'recipients' => [[
                'name' => $client->name,
                'email' => $client->email,
                'role' => 'buyer',
            ]],
            'sign_urls' => [[
                'role' => 'buyer',
                'url' => 'https://signhost.app/sign/client-original-link',
            ]],
        ],
    ]);

    $event = WebhookEvent::create([
        'provider' => 'signhost',
        'event_key' => 'signhost:txn-yacht-signed-1:SIGNED',
        'idempotency_key' => 'signhost-webhook-1',
        'payload_json' => [
            'TransactionId' => 'txn-yacht-signed-1',
            'Status' => 'SIGNED',
        ],
    ]);

    Http::fake(function ($request) {
        return match ($request->method().' '.$request->url()) {
            'GET https://api.signhost.com/api/transaction/txn-yacht-signed-1/file/signed' => Http::response(
                "%PDF-1.4\nSigned yacht contract\n%%EOF",
                200,
                ['Content-Type' => 'application/pdf']
            ),
            default => Http::response(['message' => 'Unexpected Signhost call'], 500),
        };
    });

    $job = new ProcessSignhostWebhookJob($event->id);
    $job->handle(
        app(SignhostService::class),
        app(ActionSecurity::class),
        app(NotificationDispatchService::class),
    );

    $signRequest->refresh();

    expect($signRequest->status)->toBe('SIGNED');
    expect(data_get($signRequest->metadata, 'signed_document_path'))->not->toBeNull();

    $clientNotifications = UserNotification::query()
        ->where('user_id', $client->id)
        ->latest('id')
        ->get();

    expect($clientNotifications->count())->toBeGreaterThan(0);
    expect($clientNotifications->contains(function (UserNotification $notification) use ($yacht) {
        return data_get($notification->notification, 'data.url')
            === "/dashboard/client/yachts/{$yacht->id}?step=6";
    }))->toBeTrue();

    Sanctum::actingAs($client);

    $this->getJson("/api/yachts/{$yacht->id}")
        ->assertOk()
        ->assertJsonPath('latest_signhost.status', 'signed')
        ->assertJsonPath('latest_signhost.has_signed_document', true);
});
