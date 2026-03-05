<?php

namespace App\Services;

use App\Models\BoatVideo;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class MediaManagerService
{
    /**
     * Upload a video to public storage and create a BoatVideo record.
     */
    public function uploadVideo(int $yachtId, UploadedFile $file): BoatVideo
    {
        // Store the video file
        $path = $file->store("yachts/{$yachtId}/videos", 'public');
        
        // For video processing like thumbnail extraction and duration, this is usually
        // deferred to a background job using FFMpeg. For this implementation, we
        // set status to 'uploaded' and later ones to 'processed'.
        return BoatVideo::create([
            'yacht_id' => $yachtId,
            'video_url' => Storage::disk('public')->url($path),
            'thumbnail_url' => null, 
            'duration' => null,
            'format' => $file->getClientOriginalExtension(),
            'status' => 'processed', // Marking as processed as we assume it's ready unless FFMpeg is running.
        ]);
    }

    /**
     * Delete a video and its physical file.
     */
    public function deleteVideo(BoatVideo $video): void
    {
        $baseUrl = Storage::disk('public')->url('');
        $path = str_replace($baseUrl, '', $video->video_url);
        
        if (Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
        }
        
        $video->delete();
    }
}
