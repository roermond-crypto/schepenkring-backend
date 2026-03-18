<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;

use Illuminate\Http\Request;
use App\Models\BoatDocument;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;

class BoatDocumentController extends Controller
{
    public function index($yachtId)
    {
        $documents = BoatDocument::where('boat_id', $yachtId)
            ->orderByDesc('uploaded_at')
            ->orderByDesc('id')
            ->get();
        return response()->json($documents);
    }

    public function store(Request $request, $yachtId)
    {
        $request->validate([
            'file' => 'required|file|mimes:pdf,doc,docx,jpg,jpeg,png|max:10240', // 10MB max
            'document_type' => 'nullable|string'
        ]);

        if ($request->hasFile('file')) {
            $file = $request->file('file');
            
            $originalName = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
            $extension = $file->getClientOriginalExtension();
            $safeName = \Illuminate\Support\Str::slug($originalName) . '_' . time() . '.' . $extension;
            
            $path = $file->storeAs("yachts/{$yachtId}/documents", $safeName, 'public');

            $document = BoatDocument::create([
                'boat_id' => $yachtId,
                'user_id' => Auth::id(),
                'file_path' => '/storage/' . $path,
                'file_type' => $extension,
                'document_type' => $request->document_type
            ]);

            return response()->json($document, 201);
        }

        return response()->json(['error' => 'No file uploaded'], 400);
    }

    public function destroy($yachtId, $id)
    {
        $document = BoatDocument::where('boat_id', $yachtId)->findOrFail($id);
        
        $relativePath = str_replace('/storage/', '', $document->file_path);
        
        if (Storage::disk('public')->exists($relativePath)) {
            Storage::disk('public')->delete($relativePath);
        }

        $document->delete();

        return response()->json(['message' => 'Document deleted']);
    }
}
