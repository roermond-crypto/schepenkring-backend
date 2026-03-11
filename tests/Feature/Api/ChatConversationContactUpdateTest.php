<?php

use App\Enums\UserStatus;
use App\Enums\UserType;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Lead;
use App\Models\Location;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

test('staff can update a conversation contact and sync lead identity fields', function () {
    $location = Location::create([
        'name' => 'Amsterdam Marina',
        'code' => 'AMS',
        'status' => 'ACTIVE',
    ]);

    $employee = chatStaffForLocation($location);

    $contact = Contact::create([
        'name' => 'Anonymous Visitor',
        'email' => 'anon@example.com',
        'phone' => '+31000000001',
        'consent_service_messages' => false,
    ]);

    $conversation = Conversation::create([
        'location_id' => $location->id,
        'contact_id' => $contact->id,
        'channel' => 'web_widget',
        'channel_origin' => 'web_widget',
        'status' => 'open',
    ]);

    $lead = Lead::create([
        'conversation_id' => $conversation->id,
        'location_id' => $location->id,
        'status' => 'new',
        'source' => 'chat',
        'source_url' => 'https://example.test/chat',
        'name' => 'Anonymous Visitor',
    ]);

    $conversation->forceFill(['lead_id' => $lead->id])->save();

    Sanctum::actingAs($employee);

    $response = $this->patchJson("/api/chat/conversations/{$conversation->id}/contact", [
        'name' => 'Jane Visitor',
        'email' => 'jane@example.com',
        'phone' => '+31000000999',
        'whatsapp_user_id' => '31612312312',
        'language_preferred' => 'nl-NL',
        'do_not_contact' => true,
        'consent_marketing' => true,
        'consent_service_messages' => true,
    ]);

    $response->assertOk()
        ->assertJsonPath('contact.id', $contact->id)
        ->assertJsonPath('contact.name', 'Jane Visitor')
        ->assertJsonPath('contact.email', 'jane@example.com')
        ->assertJsonPath('contact.phone', '+31000000999')
        ->assertJsonPath('contact.whatsapp_user_id', '31612312312')
        ->assertJsonPath('contact.language_preferred', 'nl')
        ->assertJsonPath('lead.name', 'Jane Visitor')
        ->assertJsonPath('lead.email', 'jane@example.com')
        ->assertJsonPath('lead.phone', '+31000000999');

    $this->assertDatabaseHas('contacts', [
        'id' => $contact->id,
        'name' => 'Jane Visitor',
        'email' => 'jane@example.com',
        'phone' => '+31000000999',
        'whatsapp_user_id' => '31612312312',
        'language_preferred' => 'nl',
        'do_not_contact' => true,
        'consent_marketing' => true,
        'consent_service_messages' => true,
    ]);

    $this->assertDatabaseHas('leads', [
        'id' => $lead->id,
        'name' => 'Jane Visitor',
        'email' => 'jane@example.com',
        'phone' => '+31000000999',
    ]);
});

test('staff can create a contact for a conversation without one', function () {
    $location = Location::create([
        'name' => 'Rotterdam Harbor',
        'code' => 'RTM',
        'status' => 'ACTIVE',
    ]);

    $employee = chatStaffForLocation($location);

    $conversation = Conversation::create([
        'location_id' => $location->id,
        'channel' => 'web_widget',
        'channel_origin' => 'web_widget',
        'status' => 'open',
    ]);

    Sanctum::actingAs($employee);

    $response = $this->patchJson("/api/chat/conversations/{$conversation->id}/contact", [
        'name' => 'New Visitor',
        'email' => 'new.visitor@example.com',
        'phone' => '+31000000002',
    ]);

    $response->assertOk()
        ->assertJsonPath('contact.name', 'New Visitor')
        ->assertJsonPath('contact.email', 'new.visitor@example.com')
        ->assertJsonPath('contact.phone', '+31000000002');

    $conversation->refresh();

    expect($conversation->contact_id)->not->toBeNull();

    $contact = Contact::findOrFail($conversation->contact_id);

    expect($contact->user_id)->toBeNull();
    expect($contact->name)->toBe('New Visitor');
});

test('staff can attach an existing contact when the conversation has none', function () {
    $location = Location::create([
        'name' => 'Den Haag Harbor',
        'code' => 'DHA',
        'status' => 'ACTIVE',
    ]);

    $employee = chatStaffForLocation($location);

    $existing = Contact::create([
        'name' => 'Matched Visitor',
        'email' => 'matched@example.com',
        'phone' => '+31000000003',
    ]);

    $conversation = Conversation::create([
        'location_id' => $location->id,
        'channel' => 'web_widget',
        'channel_origin' => 'web_widget',
        'status' => 'open',
    ]);

    Sanctum::actingAs($employee);

    $response = $this->patchJson("/api/chat/conversations/{$conversation->id}/contact", [
        'email' => 'matched@example.com',
        'name' => 'Matched Visitor Updated',
    ]);

    $response->assertOk()
        ->assertJsonPath('contact.id', $existing->id)
        ->assertJsonPath('contact.name', 'Matched Visitor Updated');

    $conversation->refresh();

    expect($conversation->contact_id)->toBe($existing->id);
});

test('staff cannot update a chat contact outside their location access', function () {
    $conversationLocation = Location::create([
        'name' => 'Groningen Harbor',
        'code' => 'GRN',
        'status' => 'ACTIVE',
    ]);

    $otherLocation = Location::create([
        'name' => 'Utrecht Harbor',
        'code' => 'UTR',
        'status' => 'ACTIVE',
    ]);

    $employee = chatStaffForLocation($otherLocation);

    $conversation = Conversation::create([
        'location_id' => $conversationLocation->id,
        'channel' => 'web_widget',
        'channel_origin' => 'web_widget',
        'status' => 'open',
    ]);

    Sanctum::actingAs($employee);

    $this->patchJson("/api/chat/conversations/{$conversation->id}/contact", [
        'name' => 'Blocked Visitor',
    ])->assertForbidden();
});

test('staff cannot change a conversation contact to an identifier owned by another contact', function () {
    $location = Location::create([
        'name' => 'Eindhoven Harbor',
        'code' => 'EIN',
        'status' => 'ACTIVE',
    ]);

    $employee = chatStaffForLocation($location);

    $contact = Contact::create([
        'name' => 'Original Visitor',
        'email' => 'original@example.com',
    ]);

    Contact::create([
        'name' => 'Other Visitor',
        'email' => 'other@example.com',
    ]);

    $conversation = Conversation::create([
        'location_id' => $location->id,
        'contact_id' => $contact->id,
        'channel' => 'web_widget',
        'channel_origin' => 'web_widget',
        'status' => 'open',
    ]);

    Sanctum::actingAs($employee);

    $response = $this->patchJson("/api/chat/conversations/{$conversation->id}/contact", [
        'email' => 'other@example.com',
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['email']);

    $contact->refresh();

    expect($contact->email)->toBe('original@example.com');
});

function chatStaffForLocation(Location $location): User
{
    $employee = User::factory()->create([
        'type' => UserType::EMPLOYEE,
        'status' => UserStatus::ACTIVE,
    ]);

    $employee->locations()->attach($location->id, ['role' => 'sales']);

    return $employee;
}
