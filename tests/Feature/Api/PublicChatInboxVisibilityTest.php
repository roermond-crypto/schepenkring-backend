<?php

use App\Enums\UserStatus;
use App\Enums\UserType;
use App\Models\Conversation;
use App\Models\Location;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

test('public lead creation updates conversation activity and surfaces in admin inbox', function () {
    $location = Location::create([
        'name' => 'Inbox Harbor',
        'code' => 'INB',
        'status' => 'ACTIVE',
    ]);

    $admin = User::factory()->create([
        'type' => UserType::ADMIN,
        'status' => UserStatus::ACTIVE,
    ]);

    for ($i = 1; $i <= 25; $i++) {
        $conversation = Conversation::create([
            'location_id' => $location->id,
            'channel' => 'web_widget',
            'channel_origin' => 'web_widget',
            'status' => 'open',
        ]);

        $timestamp = now()->subMinutes($i);
        $conversation->forceFill([
            'last_message_at' => $timestamp,
            'last_customer_message_at' => $timestamp,
            'updated_at' => $timestamp,
            'created_at' => $timestamp,
        ])->save();
    }

    $response = $this->postJson('/api/public/leads', [
        'location_id' => $location->id,
        'source_url' => 'https://example.test/boats/1',
        'name' => 'Peter',
        'email' => 'peter@example.test',
        'message' => 'I need details for this boat',
        'client_message_id' => 'public-lead-msg-1',
        'visitor_id' => 'visitor-peter-1',
    ]);

    $response->assertCreated();

    $conversationId = $response->json('conversation.id');
    $leadId = $response->json('lead.id');

    $conversation = Conversation::findOrFail($conversationId);
    expect($conversation->last_message_at)->not->toBeNull();
    expect($conversation->last_customer_message_at)->not->toBeNull();

    Sanctum::actingAs($admin);

    $this->getJson('/api/chat/conversations?limit=20')
        ->assertOk()
        ->assertJsonPath('data.0.id', $conversationId)
        ->assertJsonPath('data.0.lead.id', $leadId);
});

test('public follow up message refreshes conversation recency in admin inbox', function () {
    $location = Location::create([
        'name' => 'Follow Up Harbor',
        'code' => 'FUH',
        'status' => 'ACTIVE',
    ]);

    $admin = User::factory()->create([
        'type' => UserType::ADMIN,
        'status' => UserStatus::ACTIVE,
    ]);

    for ($i = 1; $i <= 20; $i++) {
        $conversation = Conversation::create([
            'location_id' => $location->id,
            'channel' => 'web_widget',
            'channel_origin' => 'web_widget',
            'status' => 'open',
        ]);

        $timestamp = now()->subMinutes($i);
        $conversation->forceFill([
            'last_message_at' => $timestamp,
            'last_customer_message_at' => $timestamp,
            'updated_at' => $timestamp,
            'created_at' => $timestamp,
        ])->save();
    }

    $target = Conversation::create([
        'location_id' => $location->id,
        'channel' => 'web_widget',
        'channel_origin' => 'web_widget',
        'status' => 'open',
    ]);

    $oldTimestamp = now()->subDay();
    $target->forceFill([
        'last_message_at' => $oldTimestamp,
        'last_customer_message_at' => $oldTimestamp,
        'updated_at' => $oldTimestamp,
        'created_at' => $oldTimestamp,
    ])->save();

    $this->postJson("/api/public/conversations/{$target->id}/messages", [
        'body' => 'Any update on this boat?',
        'client_message_id' => 'public-follow-up-1',
    ])->assertCreated();

    $target->refresh();
    expect($target->last_message_at)->not->toBeNull();
    expect($target->last_message_at->greaterThan($oldTimestamp))->toBeTrue();
    expect($target->last_customer_message_at)->not->toBeNull();

    Sanctum::actingAs($admin);

    $this->getJson('/api/chat/conversations?limit=20')
        ->assertOk()
        ->assertJsonPath('data.0.id', $target->id);
});

test('internal conversation replies update staff activity timestamps', function () {
    $location = Location::create([
        'name' => 'Reply Harbor',
        'code' => 'RPH',
        'status' => 'ACTIVE',
    ]);

    $admin = User::factory()->create([
        'type' => UserType::ADMIN,
        'status' => UserStatus::ACTIVE,
    ]);

    $conversation = Conversation::create([
        'location_id' => $location->id,
        'channel' => 'web_widget',
        'channel_origin' => 'web_widget',
        'status' => 'open',
    ]);

    Sanctum::actingAs($admin);

    $this->postJson("/api/conversations/{$conversation->id}/messages", [
        'body' => 'Staff reply from CRM thread',
        'client_message_id' => 'crm-reply-1',
    ])->assertCreated();

    $conversation->refresh();
    expect($conversation->last_message_at)->not->toBeNull();
    expect($conversation->last_staff_message_at)->not->toBeNull();
});
