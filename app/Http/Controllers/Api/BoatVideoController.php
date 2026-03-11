<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Video;
use App\Models\Yacht;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;

class BoatVideoController extends Controller
{
    public function index($yachtId)
    {
        $videos = Video::where('yacht_id', $yachtId)->get();
        return response()->json($videos);
    }

    public function store(Request $request, $yachtId)
    {
        // First verify yacht exists and user has access
        $yacht = Yacht::findOrFail($yachtId);

        $request->validate([
            'video' => 'required|file|mimetypes:video/mp4,video/quicktime,video/x-m4v|max:102400', // 100MB max
            'template_type' => 'nullable|string'
        ]);

        if ($request->hasFile('video')) {
            $file = $request->file('video');
            
            $originalName = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
            $extension = $file->getClientOriginalExtension();
            $safeName = \Illuminate\Support\Str::slug($originalName) . '_' . time() . '.' . $extension;
            
            $path = $file->storeAs("yachts/{$yachtId}/videos", $safeName, 'public');

            $video = Video::create([
                'yacht_id' => $yachtId,
                'status' => 'ready', // or 'processing' if there is an async background job for this later
                'template_type' => $request->template_type ?? 'standard',
                'video_path' => $path,
                'file_size_bytes' => $file->getSize(),
            ]);

            return response()->json($video, 201);
        }

        return response()->json(['error' => 'No video uploaded'], 400);
    }

    public function publish($id)
    {
        $video = Video::findOrFail($id);
        
        $video->update([
            'status' => 'published'
        ]);

        return response()->json($video);
    }

    public function destroy($yachtId, $id)
    {
        $video = Video::where('yacht_id', $yachtId)->findOrFail($id);
        
        if ($video->video_path && Storage::disk('public')->exists($video->video_path)) {
            Storage::disk('public')->delete($video->video_path);
        }

        if ($video->thumbnail_path && Storage::disk('public')->exists($video->thumbnail_path)) {
            Storage::disk('public')->delete($video->thumbnail_path);
        }

        $video->delete();

        return response()->json(['message' => 'Video deleted']);
    }
}
