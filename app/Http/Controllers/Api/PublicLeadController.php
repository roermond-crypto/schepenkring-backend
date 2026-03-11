<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\Lead;
use App\Models\Location;
use App\Models\Message;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PublicLeadController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'location_id' => 'required|exists:locations,id',
            'source_url' => 'nullable|string',
            'name' => 'sometimes|nullable|string',
            'email' => 'sometimes|nullable|email',
            'phone' => 'sometimes|nullable|string',
            'message' => 'required|string',
            'client_message_id' => 'required|string',
            'visitor_id' => 'nullable|string'
        ]);

        return DB::transaction(function () use ($validated) {
            $yachtId = null;
            if (!empty($validated['source_url'])) {
                // Strip trailing slash if present for more consistent matching
                $lookupUrl = rtrim($validated['source_url'], '/');
                $yacht = \App\Models\Yacht::where('external_url', $lookupUrl)
                    ->orWhere('external_url', $lookupUrl . '/')
                    ->first();
                if ($yacht) {
                    $yachtId = $yacht->id;
                }
            }

            $lead = Lead::create([
                'location_id' => $validated['location_id'],
                'yacht_id' => $yachtId,
                'source' => 'web_widget',
                'source_url' => $validated['source_url'] ?? null,
                'name' => $validated['name'] ?? null,
                'email' => $validated['email'] ?? null,
                'phone' => $validated['phone'] ?? null,
                'status' => 'new'
            ]);

            $conversation = Conversation::create([
                'location_id' => $validated['location_id'],
                'channel' => 'web_widget',
                'lead_id' => $lead->id,
                'status' => 'open'
            ]);

            $lead->update(['conversation_id' => $conversation->id]);

            $message = Message::create([
                'conversation_id' => $conversation->id,
                'sender_type' => 'visitor',
                'body' => $validated['message'],
                'client_message_id' => $validated['client_message_id'],
                'delivery_state' => 'sent',
            ]);

            return response()->json([
                'lead' => $lead->load('location'),
                'conversation' => $conversation,
                'message' => $message,
            ], 201);
        });
    }
}
