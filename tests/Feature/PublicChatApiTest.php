<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\Lead;
use App\Models\Location;
use App\Models\Message;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
}
