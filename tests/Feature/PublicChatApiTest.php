<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\Lead;
use App\Models\Location;
use App\Models\Message;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PublicChatApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_lead_and_chat_publicly()
    {
        $location = Location::create([
            'name' => 'Test Harbor',
            'code' => 'TH',
            'status' => 'ACTIVE'
        ]);

        // 1. Create Public Lead
        $response = $this->postJson('/api/public/leads', [
            'location_id' => $location->id,
            'source' => 'web_widget',
            'source_url' => 'https://example.com',
            'message' => 'Hello, I am interested in a boat.',
            'client_message_id' => 'initial-msg-001'
        ]);

        $response->assertStatus(201);
        $conversationId = $response->json('conversation.id');
        $this->assertNotNull($conversationId);

        // 2. Send Visitor Message
        $msgResponse = $this->postJson("/api/public/conversations/{$conversationId}/messages", [
            'body' => 'I have a question about pricing.',
            'client_message_id' => 'msg-002'
        ]);
        $msgResponse->assertStatus(201);

        // 3. Test Idempotency
        $dupResponse = $this->postJson("/api/public/conversations/{$conversationId}/messages", [
            'body' => 'I have a question about pricing.',
            'client_message_id' => 'msg-002'
        ]);
        $dupResponse->assertStatus(200); // 200 for existing

        // 4. Update Lead Info
        $updateResponse = $this->patchJson("/api/public/conversations/{$conversationId}/lead", [
            'name' => 'Public Visitor',
            'email' => 'visitor@email.com',
            'phone' => '+31612345678'
        ]);
        $updateResponse->assertStatus(200);

        $this->assertEquals('Public Visitor', Lead::first()->name);
    }

    public function test_public_lead_creation_does_not_crash_when_knowledge_tables_are_missing()
    {
        Schema::dropIfExists('knowledge_relationships');
        Schema::dropIfExists('knowledge_entities');

        $location = Location::create([
            'name' => 'Fallback Harbor',
            'code' => 'FH',
            'status' => 'ACTIVE',
        ]);

        $response = $this->postJson('/api/public/leads', [
            'location_id' => $location->id,
            'source_url' => 'https://example.com/fallback',
            'message' => 'Hello, I want more information.',
            'client_message_id' => 'fallback-msg-001',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('conversation.location_id', $location->id)
            ->assertJsonPath('message.sender_type', 'visitor');

        $this->assertDatabaseCount('conversations', 1);
        $this->assertDatabaseCount('leads', 1);
    }

    public function test_public_conversation_history_can_be_loaded_with_matching_visitor_id()
    {
        $location = Location::create([
            'name' => 'History Harbor',
            'code' => 'HH',
            'status' => 'ACTIVE',
        ]);

        $conversation = Conversation::create([
            'location_id' => $location->id,
            'visitor_id' => 'visitor-history-001',
            'channel' => 'web_widget',
            'channel_origin' => 'web_widget',
            'status' => 'open',
        ]);

        $visitorMessage = Message::create([
            'conversation_id' => $conversation->id,
            'sender_type' => 'visitor',
            'text' => 'Is this the same thread?',
            'body' => 'Is this the same thread?',
            'channel' => 'web',
            'message_type' => 'text',
            'delivery_state' => 'sent',
        ]);

        Message::create([
            'conversation_id' => $conversation->id,
            'sender_type' => 'ai',
            'text' => 'Yes, this conversation is shared.',
            'body' => 'Yes, this conversation is shared.',
            'channel' => 'web',
            'message_type' => 'text',
            'delivery_state' => 'sent',
            'metadata' => [
                'in_reply_to_message_id' => $visitorMessage->id,
            ],
        ]);

        $response = $this->getJson("/api/public/conversations/{$conversation->id}?visitor_id=visitor-history-001");

        $response->assertOk()
            ->assertJsonPath('conversation.id', $conversation->id)
            ->assertJsonCount(2, 'messages');
    }

    public function test_public_lead_reuses_existing_open_conversation_for_same_location_and_visitor()
    {
        $location = Location::create([
            'name' => 'Shared Harbor',
            'code' => 'SH',
            'status' => 'ACTIVE',
        ]);

        $conversation = Conversation::create([
            'location_id' => $location->id,
            'visitor_id' => 'visitor-shared-001',
            'channel' => 'dashboard_client',
            'channel_origin' => 'dashboard_client',
            'status' => 'open',
        ]);

        $response = $this->postJson('/api/public/leads', [
            'location_id' => $location->id,
            'source_url' => 'https://example.com/shared',
            'message' => 'Continue the same conversation please.',
            'client_message_id' => 'shared-msg-001',
            'visitor_id' => 'visitor-shared-001',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('conversation.id', $conversation->id);

        $this->assertDatabaseCount('conversations', 1);
        $this->assertDatabaseCount('leads', 1);
    }
}
