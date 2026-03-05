<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Symfony\Component\Process\Process;

class ImageSearchController extends Controller
{
    public function searchByImage(Request $request)
    {
        $request->validate([
            'image' => 'required|image|max:5120',
        ]);

        // 1. Save the search image temporarily
        $path = $request->file('image')->store('temp_search', 'public');
        $fullPath = storage_path("app/public/" . $path);

        // 2. Determine Python path (Local Windows vs VPS Virtual Env)
        if (PHP_OS_FAMILY === 'Windows') {
            $pythonPath = 'python';
        } else {
            // Point to the venv we created on the VPS
            $pythonPath = base_path('venv/bin/python');
        }

        // 3. Run the search script
        $process = new Process([
            $pythonPath,
            app_path('Scripts/image_to_image.py'),
            env('GEMINI_API_KEY'),
            env('PINECONE_API_KEY'),
            env('PINECONE_INDEX'),
            $fullPath
        ]);

        $process->setTimeout(60);
        $process->run();

        // 4. Delete the temporary search image
        if (file_exists($fullPath)) {
            unlink($fullPath);
        }

        if ($process->isSuccessful()) {
            return response()->json([
                'status' => 'success',
                'similar_boats' => json_decode($process->getOutput())
            ]);
        }

        return response()->json([
            'status' => 'error',
            'message' => 'Image search failed',
            'details' => $process->getErrorOutput()
        ], 500);
    }
}