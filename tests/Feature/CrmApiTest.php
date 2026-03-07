<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\Lead;
use App\Models\Location;
use App\Models\Message;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CrmApiTest extends TestCase
{
    use RefreshDatabase;
    
    public function test_can_list_leads_and_reply()
    {
        $user = User::first() ?? User::factory()->create();
        Sanctum::actingAs($user);
        
        $location = Location::first() ?? Location::create([
            'name' => 'Test Location',
            'code' => 'TL',
            'status' => 'ACTIVE'
        ]);
        
        $lead = Lead::first() ?? Lead::create([
            'location_id' => $location->id,
            'source' => 'web_widget',
            'name' => 'Tester',
            'email' => 'tester@test.com',
            'status' => 'new'
        ]);

        $conversation = Conversation::first() ?? Conversation::create([
            'location_id' => $location->id,
            'channel' => 'web_widget',
            'lead_id' => $lead->id,
            'status' => 'open'
        ]);

        $lead->update(['conversation_id' => $conversation->id]);

        $response = $this->getJson('/api/leads');
        $response->assertStatus(200);

        $replyResponse = $this->postJson("/api/conversations/{$conversation->id}/messages", [
            'body' => 'Testing internal reply',
            'client_message_id' => 'testing-reply-123'
        ]);
        
        $replyResponse->assertStatus(201);
        
        $listResponse = $this->getJson("/api/conversations/{$conversation->id}/messages");
        $listResponse->assertStatus(200);
    }
}
