<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\Lead;
use App\Models\Location;
use App\Models\Yacht;
use App\Services\ChatAiReplyService;
use App\Services\ChatContactService;
use App\Services\ChatConversationService;
use App\Support\CopilotLanguage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PublicLeadController extends Controller
{
    public function store(
        Request $request,
        ChatConversationService $chat,
        ChatContactService $contacts,
        ChatAiReplyService $ai,
        CopilotLanguage $language
    )
    {
        $validated = $request->validate([
            'location_id' => 'nullable|exists:locations,id',
            'boat_id' => 'nullable|exists:yachts,id',
            'source_url' => 'nullable|string',
            'name' => 'sometimes|nullable|string',
            'email' => 'sometimes|nullable|email',
            'phone' => 'sometimes|nullable|string',
            'message' => 'required|string',
            'client_message_id' => 'required|string',
            'visitor_id' => 'nullable|string'
        ]);

        return DB::transaction(function () use ($validated, $request, $chat, $contacts, $ai, $language) {
            $yacht = $this->resolveYacht($validated);
            $yachtId = $yacht?->id;
            $locationId = $this->resolveLocationId($validated, $yacht);

            if (! $locationId) {
                throw ValidationException::withMessages([
                    'location_id' => 'A location is required for public chat. Pass a location_id or use a yacht page that maps to a location.',
                ]);
            }

            $contact = $contacts->resolveContact([
                'name' => $validated['name'] ?? null,
                'email' => $validated['email'] ?? null,
                'phone' => $validated['phone'] ?? null,
            ], null);

            $lead = Lead::create([
                'location_id' => $locationId,
                'yacht_id' => $yachtId,
                'source' => 'web_widget',
                'source_url' => $validated['source_url'] ?? null,
                'name' => $validated['name'] ?? null,
                'email' => $validated['email'] ?? null,
                'phone' => $validated['phone'] ?? null,
                'status' => 'new'
            ]);

            $conversation = Conversation::create([
                'location_id' => $locationId,
                'boat_id' => $yachtId,
                'contact_id' => $contact?->id,
                'visitor_id' => $validated['visitor_id'] ?? null,
                'channel' => 'web_widget',
                'channel_origin' => 'web_widget',
                'ai_mode' => 'auto',
                'lead_id' => $lead->id,
                'status' => 'open',
                'page_url' => $validated['source_url'] ?? null,
            ]);

            $lead->update(['conversation_id' => $conversation->id]);

            $resolvedLanguage = $language->resolve(
                $validated['message'],
                $contact?->language_preferred,
                $request->header('Accept-Language'),
                $contact?->language_preferred
            );

            $message = $chat->addMessage($conversation, [
                'sender_type' => 'visitor',
                'text' => $validated['message'],
                'language' => $resolvedLanguage['language'],
                'client_message_id' => $validated['client_message_id'],
                'delivery_state' => 'sent',
            ], $request);

            $chat->syncLanguageContext($conversation, $resolvedLanguage, null, 'visitor', true);
            $aiMessage = $ai->generateForVisitorMessage($conversation->fresh(), $message, $request);

            return response()->json([
                'lead' => $lead->load('location'),
                'conversation' => $conversation->fresh(['contact', 'lead']),
                'message' => $message,
                'ai_message' => $aiMessage,
            ], 201);
        });
    }

    private function resolveYacht(array $validated): ?Yacht
    {
        if (! empty($validated['source_url'])) {
            $lookupUrl = rtrim((string) $validated['source_url'], '/');
            $yacht = Yacht::query()
                ->where('external_url', $lookupUrl)
                ->orWhere('external_url', $lookupUrl . '/')
                ->first();

            if ($yacht) {
                return $yacht;
            }
        }

        if (! empty($validated['boat_id'])) {
            return Yacht::query()->find($validated['boat_id']);
        }

        return null;
    }

    private function resolveLocationId(array $validated, ?Yacht $yacht): ?int
    {
        if (isset($validated['location_id'])) {
            return (int) $validated['location_id'];
        }

        if ($yacht?->location_id) {
            return (int) $yacht->location_id;
        }

        return $this->resolveDefaultPublicChatLocationId();
    }

    private function resolveDefaultPublicChatLocationId(): ?int
    {
        $locationId = Location::query()
            ->where('status', 'ACTIVE')
            ->where('chat_widget_enabled', true)
            ->orderBy('name')
            ->value('id');

        return $locationId ? (int) $locationId : null;
    }
}
