<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BoatVideo;
use App\Models\Yacht;
use App\Models\BoatVideoSetting;
use App\Services\MediaManagerService;
use App\Services\AiCaptionGeneratorService;
use App\Services\SocialPublishManagerService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class BoatVideoController extends Controller
{
    protected $mediaManager;
    protected $aiGenerator;
    protected $socialPublisher;

    public function __construct(
        MediaManagerService $mediaManager,
        AiCaptionGeneratorService $aiGenerator,
        SocialPublishManagerService $socialPublisher
    ) {
        $this->mediaManager = $mediaManager;
        $this->aiGenerator = $aiGenerator;
        $this->socialPublisher = $socialPublisher;
    }

    /**
     * Get boat video(s) endpoint
     */
    public function index($yachtId): JsonResponse
    {
        $videos = BoatVideo::where('yacht_id', $yachtId)->get();
        return response()->json($videos);
    }

    /**
     * Upload video endpoint
     */
    public function upload(Request $request, $yachtId): JsonResponse
    {
        $request->validate([
            'video' => 'required|file|mimes:mp4,mov,avi,wmv|max:102400', // max 100MB roughly
        ]);

        $yacht = Yacht::findOrFail($yachtId);
        $video = $this->mediaManager->uploadVideo($yacht->id, $request->file('video'));

        // Check if auto-publish is enabled
        $settings = BoatVideoSetting::where('yacht_id', $yacht->id)->first();
        
        if ($settings && $settings->auto_publish_social) {
            try {
                // If auto caption is enabled, generate and save it
                if ($settings->auto_generate_caption) {
                    $aiResult = $this->aiGenerator->generateForYacht($yacht);
                    $settings->update([
                        'caption_template' => $aiResult['caption'],
                        'hashtags_template' => $aiResult['hashtags'],
                    ]);
                }

                // Make sure there are platforms to publish to
                if (!empty($settings->platforms)) {
                    $this->socialPublisher->publish($video, $settings);
                }
            } catch (\Exception $e) {
                // Log the publish error but don't fail the upload response
                \Illuminate\Support\Facades\Log::error('Auto-publish failed during boat video upload: ' . $e->getMessage());
            }
        }

        return response()->json($video, 201);
    }

    /**
     * Remove/Delete video endpoint
     */
    public function destroy($id): JsonResponse
    {
        $video = BoatVideo::findOrFail($id);
        $this->mediaManager->deleteVideo($video);

        return response()->json(['message' => 'Video deleted successfully']);
    }

    /**
     * Trigger AI Extraction for Caption
     */
    public function generateAiCaption($id): JsonResponse
    {
        $video = BoatVideo::findOrFail($id);
        $yacht = $video->yacht;

        if (!$yacht) {
            return response()->json(['message' => 'Yacht not found for this video.'], 404);
        }

        $result = $this->aiGenerator->generateForYacht($yacht);

        // Auto-save to settings if they exist
        $settings = BoatVideoSetting::firstOrCreate(
            ['yacht_id' => $yacht->id],
            ['platforms' => ['instagram', 'facebook']]
        );

        $settings->update([
            'caption_template' => $result['caption'],
            'hashtags_template' => $result['hashtags'],
        ]);

        return response()->json($result);
    }

    /**
     * Publish video to Social Media (Francis API)
     */
    public function publish($id): JsonResponse
    {
        $video = BoatVideo::findOrFail($id);
        $settings = BoatVideoSetting::where('yacht_id', $video->yacht_id)->first();

        // Prevent ErrorException by avoiding unsafe property access on pull
        if (!$settings || empty($settings->platforms)) {
            return response()->json(['message' => 'Publishing failed. No social platforms configured.'], 400);
        }

        try {
            $this->socialPublisher->publish($video, $settings);
            return response()->json(['message' => 'Publish request sent successfully.']);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * Webhook for Francis API status sync
     */
    public function handleWebhook(Request $request): JsonResponse
    {
        $request->validate([
            'status' => 'required|string',
            'platform' => 'required|string',
            'boat_id' => 'required|integer',
        ]);

        $this->socialPublisher->handleWebhook($request->all());

        return response()->json(['message' => 'Webhook received and processed.']);
    }
}
