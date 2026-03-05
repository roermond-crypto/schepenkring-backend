<?php

namespace App\Http\Controllers;

use App\Models\InteractionEventCategory;
use Illuminate\Http\Request;

class InteractionEventCategoryController extends Controller
{
    public function index(Request $request)
    {
        $query = InteractionEventCategory::query();
        if ($request->filled('enabled')) {
            $query->where('enabled', filter_var($request->query('enabled'), FILTER_VALIDATE_BOOL));
        }
        return response()->json($query->orderBy('name')->get());
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'key' => 'required|string|max:120|unique:interaction_event_categories,key',
            'name' => 'required|string|max:160',
            'description' => 'nullable|string',
            'enabled' => 'nullable|boolean',
        ]);

        $category = InteractionEventCategory::create($validated);
        return response()->json($category, 201);
    }

    public function update(Request $request, InteractionEventCategory $category)
    {
        $validated = $request->validate([
            'name' => 'nullable|string|max:160',
            'description' => 'nullable|string',
            'enabled' => 'nullable|boolean',
        ]);

        $category->fill($validated)->save();
        return response()->json($category);
    }

    public function destroy(Request $request, InteractionEventCategory $category)
    {
        $category->delete();
        return response()->json(['message' => 'deleted']);
    }
}
