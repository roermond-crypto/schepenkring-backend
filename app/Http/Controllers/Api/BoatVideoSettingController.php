<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BoatVideoSetting;
use App\Models\Yacht;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class BoatVideoSettingController extends Controller
{
    /**
     * Get video settings for a yacht
     */
    public function show($yachtId): JsonResponse
    {
        $yacht = Yacht::findOrFail($yachtId);
        
        $settings = BoatVideoSetting::firstOrCreate(
            ['yacht_id' => $yacht->id],
            [
                'auto_publish_social' => false,
                'video_crop_format' => '16:9',
                'auto_generate_caption' => false,
                'platforms' => ['instagram', 'facebook'],
            ]
        );

        // Load current social post statuses
        $socialPosts = $yacht->socialPosts()->get();

        return response()->json([
            'settings' => $settings,
            'social_posts' => $socialPosts,
            'image_count' => $yacht->images()->count(),
        ]);
    }

    /**
     * Update video settings for a yacht
     */
    public function update(Request $request, $yachtId): JsonResponse
    {
        $yacht = Yacht::findOrFail($yachtId);
        $settings = BoatVideoSetting::firstOrCreate(
            ['yacht_id' => $yacht->id],
            [
                'auto_publish_social' => false,
                'video_crop_format' => '16:9',
                'auto_generate_caption' => false,
                'platforms' => ['instagram', 'facebook'],
            ]
        );

        $validated = $request->validate([
            'auto_publish_social' => 'boolean',
            'caption_template' => 'nullable|string',
            'hashtags_template' => 'nullable|string',
            'platforms' => 'array',
            'video_crop_format' => 'string|in:1:1,9:16,16:9',
            'auto_generate_caption' => 'boolean',
        ]);

        $settings->update($validated);

        return response()->json($settings);
    }
}
