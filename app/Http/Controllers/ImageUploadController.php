<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Illuminate\Support\Facades\Log;

class ImageUploadController extends Controller
{
public function upload(Request $request)
{
    // 1. Validate the incoming request
    $request->validate([
        'image' => 'required|image|mimes:jpeg,png,jpg|max:10240',
    ]);

    if (!$request->hasFile('image')) {
        return response()->json([
            'status' => 'error',
            'message' => 'No image file detected.'
        ], 400);
    }

    $file = $request->file('image');

    // 2. Store the file in storage/app/public/boats
    $fileName = time() . '_' . $file->getClientOriginalName();
    $path = $file->storeAs('boats', $fileName, 'public');

    $fullPath = storage_path("app/public/" . $path);
    $publicUrl = asset("storage/" . $path);

    // 3. Determine Python path
    if (PHP_OS_FAMILY === 'Windows') {
        $pythonPath = 'python';
    } else {
        $venvPath = base_path('venv/bin/python');
        $pythonPath = (file_exists($venvPath) && is_executable($venvPath)) ? $venvPath : 'python3';
    }

    $scriptPath = app_path('Scripts/pinecone_sync.py');

    // 4. Log the start
    Log::info('Starting image embedding', [
        'image_path' => $fullPath,
        'public_url' => $publicUrl,
        'script' => $scriptPath,
        'python' => $pythonPath
    ]);

    // 5. Run the Python script
    $process = new Process([
        $pythonPath,
        $scriptPath,
        env('GEMINI_API_KEY'),
        env('PINECONE_API_KEY'), // still passed but ignored by script
        env('PINECONE_INDEX'),   // still passed but ignored
        $fullPath,
        $publicUrl
    ]);

    $process->setTimeout(90);

    try {
        $process->mustRun();
        $output = trim($process->getOutput());
        $data = json_decode($output, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            Log::error('Invalid JSON from Python script', ['output' => $output]);
            return response()->json([
                'status' => 'partial_success',
                'message' => 'Image saved locally, but embedding script returned invalid data.',
                'local_url' => $publicUrl
            ], 500);
        }

        if (isset($data['error'])) {
            Log::error('Embedding script error', $data);
            return response()->json([
                'status' => 'partial_success',
                'message' => 'Image saved locally, but embedding failed: ' . $data['error'],
                'local_url' => $publicUrl
            ], 500);
        }

        // Save to database
        \App\Models\ImageEmbedding::updateOrCreate(
            ['filename' => $data['filename']],
            [
                'public_url' => $data['public_url'],
                'embedding' => $data['embedding'],
                'description' => $data['description']
            ]
        );

        Log::info('Image embedding stored in database', ['filename' => $data['filename']]);

        return response()->json([
            'status' => 'success',
            'message' => 'Image uploaded and embedding stored in database.',
            'data' => [
                'file_name' => $fileName,
                'public_url' => $publicUrl,
                'description' => $data['description']
            ]
        ], 200);

    } catch (\Exception $e) {
        Log::error('Image embedding process failed', [
            'error' => $e->getMessage(),
            'output' => $process->getErrorOutput()
        ]);

        return response()->json([
            'status' => 'partial_success',
            'message' => 'Image saved locally, but embedding process failed.',
            'error_details' => $process->getErrorOutput(),
            'local_url' => $publicUrl
        ], 500);
    }
}
}