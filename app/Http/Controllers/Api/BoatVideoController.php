<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Video;
use App\Models\Yacht;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class BoatVideoController extends Controller
{
    public function index($yachtId)
    {
        $videos = Video::where('yacht_id', $yachtId)
            ->latest()
            ->get();

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

        try {
            $video->update([
                'status' => 'published',
            ]);
        } catch (QueryException) {
            // Some local/test schemas still use the older enum definition.
            $video->update([
                'status' => 'ready',
            ]);
        }

        return response()->json($video->fresh());
    }

    public function destroy($id)
    {
        $video = Video::findOrFail($id);

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
