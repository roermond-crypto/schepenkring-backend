<?php

namespace App\Http\Controllers;

use App\Jobs\GenerateFaqLongDescription;
use App\Jobs\TranslateFaq;
use App\Models\FaqEntry;
use App\Models\FaqTranslation;
use App\Services\FaqImportService;
use App\Services\FaqPineconeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class FaqAdminController extends Controller
{
    public function index(Request $request)
    {
        $query = FaqTranslation::with('faq');

        if ($request->filled('language')) {
            $query->where('language', strtolower($request->query('language')));
        }

        if ($request->filled('needs_review')) {
            $query->where('needs_review', filter_var($request->query('needs_review'), FILTER_VALIDATE_BOOL));
        }

        if ($request->filled('long_description_status')) {
            $query->where('long_description_status', $request->query('long_description_status'));
        }

        if ($request->filled('namespace') || $request->filled('category') || $request->filled('subcategory')) {
            $query->whereHas('faq', function ($faqQuery) use ($request) {
                if ($request->filled('namespace')) {
                    $faqQuery->where('namespace', $request->query('namespace'));
                }
                if ($request->filled('category')) {
                    $faqQuery->where('category', $request->query('category'));
                }
                if ($request->filled('subcategory')) {
                    $faqQuery->where('subcategory', $request->query('subcategory'));
                }
            });
        }

        $translations = $query->orderBy('updated_at', 'desc')->paginate(25);

        return response()->json($translations);
    }

    public function import(Request $request, FaqImportService $importer, FaqPineconeService $pinecone)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv',
            'language' => 'nullable|string|max:5',
            'generate_long_descriptions' => 'nullable|boolean',
            'index_after_import' => 'nullable|boolean',
        ]);

        $path = $request->file('file')->store('imports');
        $filePath = Storage::path($path);

        $indexAfterImport = (bool) $request->input('index_after_import', false);

        $result = $importer->import($filePath, [
            'default_language' => $request->input('language', 'nl'),
            'generate_long_descriptions' => (bool) $request->input('generate_long_descriptions', false),
            'return_translation_ids' => $indexAfterImport,
        ]);

        $translationIds = $result['translation_ids'] ?? [];
        unset($result['translation_ids']);

        $indexed = 0;
        if ($indexAfterImport && !empty($translationIds)) {
            $translations = FaqTranslation::with('faq')->whereIn('id', $translationIds)->get();
            foreach ($translations as $translation) {
                if ($pinecone->upsertTranslation($translation)) {
                    $indexed++;
                }
            }
        }

        return response()->json([
            'message' => 'FAQ import completed',
            'result' => array_merge($result, [
                'indexed' => $indexed,
            ]),
        ]);
    }

    public function reindex(Request $request, FaqPineconeService $pinecone)
    {
        $validated = $request->validate([
            'language' => 'nullable|string|max:5',
            'translation_ids' => 'nullable|array',
            'translation_ids.*' => 'string',
            'force' => 'nullable|boolean',
            'limit' => 'nullable|integer|min:1|max:1000',
        ]);

        $query = FaqTranslation::with('faq');
        if (!empty($validated['translation_ids'])) {
            $query->whereIn('id', $validated['translation_ids']);
        } elseif (!($validated['force'] ?? false)) {
            $query->whereNull('indexed_at');
        }

        if (!empty($validated['language'])) {
            $query->where('language', strtolower($validated['language']));
        }

        $limit = (int) ($validated['limit'] ?? 200);
        $translations = $query->limit($limit)->get();

        $indexed = 0;
        foreach ($translations as $translation) {
            if ($pinecone->upsertTranslation($translation)) {
                $indexed++;
            }
        }

        return response()->json([
            'message' => 'FAQ Pinecone indexing complete',
            'indexed' => $indexed,
        ]);
    }

    public function generateLongDescriptions(Request $request)
    {
        $validated = $request->validate([
            'language' => 'nullable|string|max:5',
            'translation_ids' => 'nullable|array',
            'translation_ids.*' => 'string',
            'force' => 'nullable|boolean',
            'limit' => 'nullable|integer|min:1|max:500',
        ]);

        $query = FaqTranslation::query();
        if (!empty($validated['translation_ids'])) {
            $query->whereIn('id', $validated['translation_ids']);
        } elseif (!($validated['force'] ?? false)) {
            $query->where(function ($q) {
                $q->whereNull('long_description')
                  ->orWhere('long_description_status', 'pending')
                  ->orWhere('long_description_status', 'failed');
            });
        }

        if (!empty($validated['language'])) {
            $query->where('language', strtolower($validated['language']));
        }

        $limit = (int) ($validated['limit'] ?? 100);
        $translations = $query->limit($limit)->get();

        foreach ($translations as $translation) {
            GenerateFaqLongDescription::dispatch($translation->id, (bool) ($validated['force'] ?? false));
        }

        return response()->json([
            'message' => 'Long description jobs queued',
            'queued' => $translations->count(),
        ]);
    }

    public function translate(Request $request)
    {
        $validated = $request->validate([
            'faq_ids' => 'nullable|array',
            'faq_ids.*' => 'string',
            'target_language' => 'required|string|max:5',
            'source_language' => 'nullable|string|max:5',
            'force' => 'nullable|boolean',
        ]);

        $faqQuery = FaqEntry::query();
        if (!empty($validated['faq_ids'])) {
            $faqQuery->whereIn('id', $validated['faq_ids']);
        }

        $faqs = $faqQuery->get();
        foreach ($faqs as $faq) {
            TranslateFaq::dispatch(
                $faq->id,
                $validated['target_language'],
                $validated['source_language'] ?? null,
                (bool) ($validated['force'] ?? false)
            );
        }

        return response()->json([
            'message' => 'Translation jobs queued',
            'queued' => $faqs->count(),
        ]);
    }

    public function approveTranslation(string $translationId)
    {
        $translation = FaqTranslation::findOrFail($translationId);
        $translation->needs_review = false;
        $translation->translation_status = \App\Support\TranslationStatus::REVIEWED;
        $translation->save();

        return response()->json([
            'message' => 'Translation approved',
            'translation' => $translation,
        ]);
    }
}
