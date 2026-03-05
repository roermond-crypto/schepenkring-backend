<?php

namespace App\Http\Controllers;

use App\Models\InteractionTimelineEntry;
use App\Models\UserInteractionSummary;
use Illuminate\Http\Request;

class InteractionHubController extends Controller
{
    public function timeline(Request $request)
    {
        $validated = $request->validate([
            'user_id' => 'nullable|integer|exists:users,id',
            'contact_id' => 'nullable|string',
            'conversation_id' => 'nullable|string',
            'channel' => 'nullable|string',
            'direction' => 'nullable|string',
            'event_type_id' => 'nullable|integer|exists:interaction_event_types,id',
            'from' => 'nullable|date',
            'to' => 'nullable|date',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $query = InteractionTimelineEntry::query()->with('eventType');

        if (!empty($validated['user_id'])) {
            $query->where('user_id', $validated['user_id']);
        }
        if (!empty($validated['contact_id'])) {
            $query->where('contact_id', $validated['contact_id']);
        }
        if (!empty($validated['conversation_id'])) {
            $query->where('conversation_id', $validated['conversation_id']);
        }
        if (!empty($validated['channel'])) {
            $query->where('channel', $validated['channel']);
        }
        if (!empty($validated['direction'])) {
            $query->where('direction', $validated['direction']);
        }
        if (!empty($validated['event_type_id'])) {
            $query->where('event_type_id', $validated['event_type_id']);
        }
        if (!empty($validated['from'])) {
            $query->where('occurred_at', '>=', $validated['from']);
        }
        if (!empty($validated['to'])) {
            $query->where('occurred_at', '<=', $validated['to']);
        }

        $perPage = $validated['per_page'] ?? 50;

        return response()->json($query->orderByDesc('occurred_at')->paginate($perPage));
    }

    public function summary(Request $request)
    {
        $validated = $request->validate([
            'user_id' => 'required|integer|exists:users,id',
        ]);

        $summary = UserInteractionSummary::where('user_id', $validated['user_id'])->first();
        if (!$summary) {
            return response()->json(['message' => 'Summary not found'], 404);
        }

        return response()->json($summary);
    }
}
