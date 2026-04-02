<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;

use Illuminate\Http\Request;
use App\Models\BoatDocument;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class BoatDocumentController extends Controller
{
    public function index(Request $request, $yachtId)
    {
        $documents = BoatDocument::where('boat_id', $yachtId)
            ->orderByDesc('uploaded_at')
            ->orderByDesc('id')
            ->get();

        return response()->json(
            $documents->map(fn (BoatDocument $document) => $this->serializeDocument($request, $document))
        );
    }

    public function store(Request $request, $yachtId)
    {
        $request->validate([
            'file' => 'required_without:files|file|mimes:pdf,doc,docx,jpg,jpeg,png|max:10240',
            'files' => 'required_without:file|array|min:1|max:20',
            'files.*' => 'file|mimes:pdf,doc,docx,jpg,jpeg,png|max:10240',
            'document_type' => 'nullable|string'
        ]);

        $files = [];
        if ($request->hasFile('files')) {
            $files = array_filter($request->file('files') ?? []);
        } elseif ($request->hasFile('file')) {
            $files = [$request->file('file')];
        }

        if ($files !== []) {
            $documents = [];

            foreach ($files as $file) {
            $originalName = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
            $extension = $file->getClientOriginalExtension();
                $safeBase = Str::slug($originalName);
                $safeName = ($safeBase !== '' ? $safeBase : 'document') . '_' . Str::lower(Str::random(12)) . '.' . $extension;

            $path = $file->storeAs("yachts/{$yachtId}/documents", $safeName, 'public');

                $document = BoatDocument::create([
                'boat_id' => $yachtId,
                'user_id' => Auth::id(),
                'file_path' => '/storage/' . $path,
                'file_type' => $extension,
                'document_type' => $request->document_type
            ]);

                $documents[] = $this->serializeDocument($request, $document);
            }

            return response()->json([
                'documents' => $documents,
                'document' => $documents[0] ?? null,
            ], 201);
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

    private function serializeDocument(Request $request, BoatDocument $document): array
    {
        $payload = $document->toArray();
        $payload['file_url'] = $this->resolveDocumentUrl($request, $document->file_path);

        return $payload;
    }

    private function resolveDocumentUrl(Request $request, ?string $filePath): ?string
    {
        $value = trim((string) $filePath);
        if ($value === '') {
            return null;
        }

        if (preg_match('/^https?:\/\//i', $value) === 1) {
            return $value;
        }

        $relativePath = ltrim(preg_replace('#^/storage/#', '', $value) ?? $value, '/');
        $publicUrl = Storage::disk('public')->url($relativePath);

        if (preg_match('/^https?:\/\//i', $publicUrl) === 1) {
            return $publicUrl;
        }

        return rtrim($request->getSchemeAndHttpHost(), '/') . '/' . ltrim($publicUrl, '/');
    }
}
