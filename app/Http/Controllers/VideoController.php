<?php

namespace App\Http\Controllers;

use App\Jobs\RenderBoatVideo;
use App\Models\VideoJob;
use App\Models\Yacht;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class VideoController extends Controller
{
    /**
     * Available music tracks.
     */
    private const MUSIC_TRACKS = [
        ['slug' => 'ocean_breeze', 'name' => 'Ocean Breeze', 'duration' => '2:30'],
        ['slug' => 'calm_waters', 'name' => 'Calm Waters', 'duration' => '3:00'],
        ['slug' => 'nautical_journey', 'name' => 'Nautical Journey', 'duration' => '2:45'],
        ['slug' => 'sunset_sail', 'name' => 'Sunset Sail', 'duration' => '3:15'],
    ];

    /**
     * GET /api/video/music-tracks
     * Returns available background music tracks.
     */
    public function musicTracks(): JsonResponse
    {
        return response()->json(self::MUSIC_TRACKS);
    }

    /**
     * POST /api/yachts/{id}/generate-video
     * Queue a new video rendering job.
     */
    public function generate(Request $request, $id): JsonResponse
    {
        $yacht = Yacht::findOrFail($id);

        $request->validate([
            'music_track' => 'nullable|string',
            'voiceover_path' => 'nullable|string',
        ]);

        // Check for existing active job
        $existingJob = VideoJob::where('yacht_id', $id)
            ->whereIn('status', ['queued', 'processing'])
            ->first();

        if ($existingJob) {
            return response()->json([
                'message' => 'A video is already being rendered for this yacht',
                'job' => $existingJob,
            ], 409);
        }

        // Create job record
        $videoJob = VideoJob::create([
            'yacht_id' => $yacht->id,
            'user_id' => auth()->id(),
            'status' => 'queued',
            'music_track' => $request->music_track,
            'has_voiceover' => !empty($request->voiceover_path),
            'voiceover_path' => $request->voiceover_path,
            'progress' => 0,
        ]);

        // Dispatch to queue
        RenderBoatVideo::dispatch($videoJob->id);

        return response()->json([
            'message' => 'Video rendering queued',
            'job' => $videoJob,
        ], 202);
    }

    /**
     * GET /api/video-jobs/{id}
     * Get the status of a video rendering job.
     */
    public function status($id): JsonResponse
    {
        $job = VideoJob::findOrFail($id);

        return response()->json([
            'id' => $job->id,
            'status' => $job->status,
            'progress' => $job->progress,
            'video_url' => $job->video_url,
            'duration_seconds' => $job->duration_seconds,
            'file_size_bytes' => $job->file_size_bytes,
            'image_count' => $job->image_count,
            'error_log' => $job->status === 'failed' ? $job->error_log : null,
            'created_at' => $job->created_at,
            'updated_at' => $job->updated_at,
        ]);
    }

    /**
     * GET /api/yachts/{id}/videos
     * List all video jobs for a yacht.
     */
    public function list($id): JsonResponse
    {
        $videos = VideoJob::where('yacht_id', $id)
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($job) {
                return [
                    'id' => $job->id,
                    'status' => $job->status,
                    'progress' => $job->progress,
                    'video_url' => $job->video_url,
                    'duration_seconds' => $job->duration_seconds,
                    'file_size_bytes' => $job->file_size_bytes,
                    'music_track' => $job->music_track,
                    'created_at' => $job->created_at,
                ];
            });

        return response()->json($videos);
    }
}
