<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Location;
use Illuminate\Http\Request;

class LocationWidgetSettingsController extends Controller
{
    /**
     * Get widget settings for a location.
     */
    public function show($id)
    {
        $location = Location::findOrFail($id);

        return response()->json([
            'enabled' => $location->chat_widget_enabled,
            'welcome_text' => $location->chat_widget_welcome_text,
            'theme' => $location->chat_widget_theme,
        ]);
    }

    /**
     * Update widget settings for a location.
     */
    public function update(Request $request, $id)
    {
        $request->validate([
            'enabled' => 'required|boolean',
            'welcome_text' => 'nullable|string|max:1000',
            'theme' => 'nullable|string|in:ocean,violet,sunset',
        ]);

        $location = Location::findOrFail($id);
        $location->update([
            'chat_widget_enabled' => $request->enabled,
            'chat_widget_welcome_text' => $request->welcome_text,
            'chat_widget_theme' => $request->theme ?? 'ocean',
        ]);

        return response()->json([
            'message' => 'Widget settings updated successfully',
            'location' => $location,
        ]);
    }
}
