<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\Location;
use App\Services\ChatAccessService;
use App\Services\ChatContactService;
use App\Services\ChatConversationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Validation\ValidationException;

class ChatConversationController extends Controller
{
    public function index(Request $request, ChatAccessService $access)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $query = Conversation::with([
            'contact',
            'lead',
            'location:id,name,code',
            'assignedEmployee:id,name,email',
        ])
            ->orderByDesc('last_message_at')
            ->orderByDesc('updated_at');

        // Client users should only see their own conversations, not all
        // conversations at their location (which would be a privacy violation).
        if ($user->isClient()) {
            $query->where(function ($sub) use ($user) {
                $sub->where('user_id', $user->id)
                    ->orWhereHas('contact', function ($q) use ($user) {
                        $q->where('email', $user->email);
                    });
            });
            if ($user->client_location_id) {
                $query->where('location_id', $user->client_location_id);
            }
        } else {
            $query = $access->scopeConversations($query, $user, $request->boolean('assigned_only'));
        }

        $locationId = $request->integer('location_id') ?: $request->integer('harbor_id');
        if ($locationId) {
            $query->where('location_id', $locationId);
        }
        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }
        if ($request->filled('priority')) {
            $query->where('priority', $request->query('priority'));
        }

        if ($request->filled('cursor')) {
            $query->where('last_message_at', '<', $request->query('cursor'));
        }

        $limit = (int) $request->query('limit', 20);
        $conversations = $query->limit($limit)->get();

        return response()->json([
            'data' => $conversations,
            'next_cursor' => $conversations->last()?->last_message_at?->toIso8601String(),
        ]);
    }

    public function show(Request $request, string $id, ChatAccessService $access)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $conversation = Conversation::with([
            'contact',
            'lead',
            'location:id,name,code',
            'assignedEmployee:id,name,email',
            'messages' => function ($query) {
                $query->with(['attachments', 'employee:id,name,email'])
                    ->orderBy('created_at', 'asc')
                    ->limit(200);
            },
        ])->findOrFail($id);

        if (!$access->canAccessConversation($user, $conversation)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        return response()->json($conversation);
    }

    public function store(Request $request, ChatConversationService $service)
    {
        $payload = $request->validate([
            'visitor_id' => 'nullable|string|max:64',
            'session_jwt' => 'nullable|string',
            'contact' => 'nullable|array',
            'contact.name' => 'nullable|string|max:255',
            'contact.email' => 'nullable|email',
            'contact.phone' => 'nullable|string|max:50',
            'contact.whatsapp_user_id' => 'nullable|string|max:100',
            'contact.language_preferred' => 'nullable|string|max:5',
            'contact.do_not_contact' => 'nullable|boolean',
            'contact.consent_marketing' => 'nullable|boolean',
            'contact.consent_service_messages' => 'nullable|boolean',
            'boat_id' => 'nullable|integer',
            'page_url' => 'nullable|string|max:2048',
            'location_id' => 'nullable|integer|exists:locations,id',
            'harbor_id' => 'nullable|integer',
            'widget_harbor_id' => 'nullable|integer',
            'channel_origin' => 'nullable|string|max:50',
            'ai_mode' => 'nullable|string|max:10',
            'priority' => 'nullable|string|max:10',
            'language_preferred' => 'nullable|string|max:5',
            'language_detected' => 'nullable|string|max:5',
            'utm_source' => 'nullable|string|max:100',
            'utm_medium' => 'nullable|string|max:100',
            'utm_campaign' => 'nullable|string|max:100',
            'ref_code' => 'nullable|string|max:100',
            'reuse' => 'nullable|boolean',
        ]);

        if (! empty($payload['location_id']) && empty($payload['harbor_id'])) {
            $payload['harbor_id'] = (int) $payload['location_id'];
        }

        // If a logged-in client user does not supply a harbor_id, automatically
        // use their assigned location so the conversation is always linked to
        // the correct harbor and the "not connected to a location" error is avoided.
        $user = $request->user();
        if (empty($payload['harbor_id']) && $user?->isClient() && $user->client_location_id) {
            $payload['harbor_id'] = (int) $user->client_location_id;
        }

        if (!empty($payload['session_jwt'])) {
            try {
                $decoded = json_decode(Crypt::decryptString($payload['session_jwt']), true);
                if (empty($payload['visitor_id']) && !empty($decoded['visitor_id'])) {
                    $payload['visitor_id'] = $decoded['visitor_id'];
                }
                if (empty($payload['harbor_id']) && !empty($decoded['harbor_id'])) {
                    $payload['harbor_id'] = $decoded['harbor_id'];
                }
            } catch (\Throwable $e) {
                return response()->json(['message' => 'Invalid session token'], 401);
            }
        }

        $conversation = $service->createConversation($payload, $request, $request->user());

        return response()->json($conversation, 201);
    }

    public function update(Request $request, string $id, ChatAccessService $access)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $conversation = Conversation::findOrFail($id);
        if (!$access->canAccessConversation($user, $conversation)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $payload = $request->validate([
            'status' => 'nullable|string|in:open,pending,solved',
            'priority' => 'nullable|string|in:low,normal,high',
            'ai_mode' => 'nullable|string|in:auto,assist,off',
            'location_id' => 'nullable|integer|exists:locations,id',
            'harbor_id' => 'nullable|integer',
            'assign_to' => 'nullable|integer',
        ]);

        if (! empty($payload['location_id']) && empty($payload['harbor_id'])) {
            $payload['harbor_id'] = (int) $payload['location_id'];
        }

        if (isset($payload['status'])) {
            $conversation->status = $payload['status'];
        }
        if (isset($payload['priority'])) {
            $conversation->priority = $payload['priority'];
        }
        if (isset($payload['ai_mode'])) {
            $conversation->ai_mode = $payload['ai_mode'];
        }
        if (isset($payload['harbor_id'])) {
            $newLocationId = (int) $payload['harbor_id'];
            if (! Location::query()->whereKey($newLocationId)->exists()) {
                throw ValidationException::withMessages([
                    'location_id' => 'The selected location is invalid.',
                ]);
            }
            if (! $access->canAccessLocation($user, $newLocationId)) {
                return response()->json(['message' => 'Forbidden'], 403);
            }
            $conversation->location_id = $newLocationId;
        }
        if (isset($payload['assign_to'])) {
            $conversation->assigned_to = (int) $payload['assign_to'];
            $conversation->assigned_employee_id = $conversation->assigned_employee_id ?? (int) $payload['assign_to'];
        }

        $conversation->save();

        return response()->json($conversation);
    }

    public function updateContact(Request $request, string $id, ChatAccessService $access, ChatContactService $contacts)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }
        if (!$user->isStaff()) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $conversation = Conversation::with(['contact', 'lead'])->findOrFail($id);
        if (!$access->canAccessConversation($user, $conversation)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $payload = $request->validate([
            'name' => 'sometimes|nullable|string|max:255',
            'email' => 'sometimes|nullable|email',
            'phone' => 'sometimes|nullable|string|max:50',
            'whatsapp_user_id' => 'sometimes|nullable|string|max:100',
            'language_preferred' => 'sometimes|nullable|string|max:5',
            'do_not_contact' => 'sometimes|boolean',
            'consent_marketing' => 'sometimes|boolean',
            'consent_service_messages' => 'sometimes|boolean',
        ]);

        $contacts->updateConversationContact($conversation, $payload);
        $this->syncLeadIdentity($conversation, $payload);

        return response()->json($conversation->fresh()->load(['contact', 'lead']));
    }

    public function stream(Request $request, string $id, ChatAccessService $access)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $conversation = Conversation::with(['messages' => function ($query) {
            $query->with(['attachments', 'employee:id,name,email'])
                ->orderBy('created_at', 'asc')
                ->limit(100);
        }])->findOrFail($id);

        if (!$access->canAccessConversation($user, $conversation)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        return response()->stream(function () use ($conversation) {
            echo "event: init\n";
            echo 'data: ' . json_encode($conversation->messages) . "\n\n";
            echo "event: done\n";
            echo "data: {}\n\n";
            ob_flush();
            flush();
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'Connection' => 'keep-alive',
        ]);
    }

    private function syncLeadIdentity(Conversation $conversation, array $payload): void
    {
        if (! $conversation->lead) {
            return;
        }

        $updates = [];
        foreach (['name', 'email', 'phone'] as $field) {
            if (array_key_exists($field, $payload)) {
                $updates[$field] = $payload[$field];
            }
        }

        if ($updates === []) {
            return;
        }

        $conversation->lead->fill($updates);
        $conversation->lead->save();
    }
}
