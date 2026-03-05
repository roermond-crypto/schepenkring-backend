<?php

namespace App\Http\Controllers;

use App\Models\InteractionEventType;
use Illuminate\Http\Request;

class InteractionEventTypeController extends Controller
{
    public function index(Request $request)
    {
        $query = InteractionEventType::query()->with('category');
        if ($request->filled('enabled')) {
            $query->where('enabled', filter_var($request->query('enabled'), FILTER_VALIDATE_BOOL));
        }
        if ($request->filled('category_id')) {
            $query->where('category_id', $request->integer('category_id'));
        }

        return response()->json($query->orderBy('name')->get());
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'category_id' => 'nullable|exists:interaction_event_categories,id',
            'key' => 'required|string|max:120|unique:interaction_event_types,key',
            'name' => 'required|string|max:160',
            'description' => 'nullable|string',
            'default_channels' => 'nullable|array',
            'enabled' => 'nullable|boolean',
        ]);

        $eventType = InteractionEventType::create($validated);
        return response()->json($eventType, 201);
    }

    public function update(Request $request, InteractionEventType $eventType)
    {
        $validated = $request->validate([
            'category_id' => 'nullable|exists:interaction_event_categories,id',
            'name' => 'nullable|string|max:160',
            'description' => 'nullable|string',
            'default_channels' => 'nullable|array',
            'enabled' => 'nullable|boolean',
        ]);

        $eventType->fill($validated)->save();
        return response()->json($eventType);
    }

    public function destroy(Request $request, InteractionEventType $eventType)
    {
        $eventType->delete();
        return response()->json(['message' => 'deleted']);
    }
}
