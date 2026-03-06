<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\Request;

class SettingsController extends Controller
{
    /**
     * Get all settings, optionally filtered by group.
     */
    public function index(Request $request)
    {
        $query = Setting::query()->orderBy('group')->orderBy('key');

        if ($request->filled('group')) {
            $query->where('group', $request->group);
        }

        return $query->get();
    }

    /**
     * Get a single setting by key.
     */
    public function show(string $key)
    {
        $setting = Setting::where('key', $key)->firstOrFail();
        return response()->json([
            'key'   => $setting->key,
            'value' => $setting->typed_value,
            'group' => $setting->group,
            'type'  => $setting->type,
        ]);
    }

    /**
     * Update or create a setting.
     */
    public function update(Request $request)
    {
        $validated = $request->validate([
            'key'         => 'required|string|max:255',
            'value'       => 'required',
            'group'       => 'sometimes|string|max:100',
            'type'        => 'sometimes|in:string,json,boolean,integer',
            'description' => 'sometimes|nullable|string',
        ]);

        $setting = Setting::set(
            key: $validated['key'],
            value: $validated['value'],
            group: $validated['group'] ?? 'general',
            type: $validated['type'] ?? 'string',
            description: $validated['description'] ?? null,
        );

        return response()->json($setting, 200);
    }

    /**
     * Bulk update settings.
     */
    public function bulkUpdate(Request $request)
    {
        $validated = $request->validate([
            'settings'         => 'required|array|min:1',
            'settings.*.key'   => 'required|string',
            'settings.*.value' => 'required',
            'settings.*.group' => 'sometimes|string',
            'settings.*.type'  => 'sometimes|string',
        ]);

        $results = [];
        foreach ($validated['settings'] as $item) {
            $results[] = Setting::set(
                key: $item['key'],
                value: $item['value'],
                group: $item['group'] ?? 'general',
                type: $item['type'] ?? 'string',
            );
        }

        return response()->json($results);
    }
}
