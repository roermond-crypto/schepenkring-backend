<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Illuminate\Support\Facades\Log;

use App\Models\ImageEmbedding;
use Illuminate\Support\Facades\Storage;

class BoatAnalysisController extends Controller
{
    public function identify(Request $request)
    {
        $request->validate(['query' => 'required|string']);

        // Determine Python interpreter path
        if (PHP_OS_FAMILY === 'Windows') {
            $pythonPath = 'python';
        } else {
            // Try venv path first
            $venvPath = base_path('venv/bin/python');
            if (file_exists($venvPath) && is_executable($venvPath)) {
                $pythonPath = $venvPath;
            } else {
                // Fallback to system python3
                $pythonPath = 'python3';
                Log::warning('Venv Python not found, using system python3');
            }
        }

        // Full path to the Python script
        $scriptPath = app_path('Scripts/boat_analysis.py');

        Log::info('Running boat analysis', [
            'python' => $pythonPath,
            'script' => $scriptPath,
            'query' => $request->input('query')
        ]);

        $process = new Process([
            $pythonPath,
            $scriptPath,
            env('GEMINI_API_KEY'),
            env('PINECONE_API_KEY'),
            env('PINECONE_INDEX'),
            $request->input('query')
        ]);

        $process->setTimeout(180); // 3 minutes

        try {
            $process->mustRun();
            $output = $process->getOutput();
            $data = json_decode($output, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                Log::error('Invalid JSON from Python script', ['output' => $output]);
                return response()->json([
                    'status' => 'error',
                    'message' => 'Invalid response from analysis service',
                ], 500);
            }

            return response()->json($data);
        } catch (ProcessFailedException $e) {
            Log::error('Boat analysis process failed', [
                'error' => $e->getMessage(),
                'output' => $process->getErrorOutput()
            ]);
            return response()->json([
                'status' => 'error',
                'message' => 'Analysis failed',
                'error' => $process->getErrorOutput()
            ], 500);
        }
    }

public function destroy($filename)
{
    try {
        // Find the record
        $embedding = ImageEmbedding::where('filename', $filename)->firstOrFail();

        // Delete the physical file
        $path = 'public/boats/' . $filename;
        if (Storage::exists($path)) {
            Storage::delete($path);
        }

        // Delete the database record
        $embedding->delete();

        return response()->json(['message' => 'Boat deleted successfully']);
    } catch (\Exception $e) {
        return response()->json(['message' => 'Delete failed: ' . $e->getMessage()], 500);
    }
}
}