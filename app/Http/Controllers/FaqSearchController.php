<?php

namespace App\Http\Controllers;

use App\Models\FaqEntry;
use App\Models\FaqTranslation;
use App\Services\FaqPineconeService;
use App\Services\LocaleService;
use Illuminate\Http\Request;

class FaqSearchController extends Controller
{
    public function search(Request $request, FaqPineconeService $pinecone)
    {
        $validated = $request->validate([
            'query' => 'required|string|max:500',
            'language' => 'nullable|string|max:5',
            'locale' => 'nullable|string|max:5',
            'namespace' => 'nullable|string|max:50',
            'top_k' => 'nullable|integer|min:1|max:20',
        ]);

        $language = strtolower((string) ($validated['locale'] ?? $validated['language'] ?? 'nl'));

        $matches = $pinecone->query(
            $validated['query'],
            $language,
            $validated['namespace'] ?? null,
            (int) ($validated['top_k'] ?? 10)
        );

        $ids = array_values(array_filter(array_map(fn ($match) => $match['id'] ?? null, $matches)));

        $translations = FaqTranslation::with('faq')
            ->whereIn('id', $ids)
            ->get()
            ->keyBy('id');

        $results = [];
        foreach ($matches as $match) {
            $translation = $translations->get($match['id'] ?? '');
            if (!$translation) {
                continue;
            }

            $faq = $translation->faq;
            if (!$faq || !$faq->is_active) {
                continue;
            }

            $results[] = [
                'score' => $match['score'] ?? null,
                'translation_id' => $translation->id,
                'faq_id' => $translation->faq_id,
                'language' => $translation->language,
                'namespace' => $faq->namespace,
                'category' => $faq->category,
                'subcategory' => $faq->subcategory,
                'question' => $translation->question,
                'answer' => $translation->answer,
            ];
        }

        return response()->json([
            'query' => $validated['query'],
            'language' => $language,
            'results' => $results,
        ]);
    }

    public function showTranslation(string $translationId)
    {
        $translation = FaqTranslation::with('faq')->findOrFail($translationId);
        if (!$translation->faq || !$translation->faq->is_active) {
            return response()->json(['message' => 'FAQ not available'], 404);
        }

        return response()->json([
            'translation' => $translation,
            'faq' => $translation->faq,
        ]);
    }

    public function showBySlug(Request $request, string $slug)
    {
        $localeService = app(LocaleService::class);
        $localeInfo = $localeService->resolve($request, $request->user());
        $language = $localeInfo['locale'];
        $fallbacks = $localeInfo['fallbacks'];

        $faq = FaqEntry::where('slug', $slug)->firstOrFail();
        if (!$faq->is_active) {
            return response()->json(['message' => 'FAQ not available'], 404);
        }
        $translation = null;
        $chain = array_values(array_unique(array_merge([$language], $fallbacks)));
        foreach ($chain as $candidate) {
            $translation = $faq->translations()->where('language', $candidate)->first();
            if ($translation) {
                $language = $candidate;
                break;
            }
        }

        if (!$translation) {
            return response()->json(['message' => 'Translation not found'], 404);
        }

        return response()->json([
            'translation' => $translation,
            'faq' => $faq,
            'requested_locale' => $localeInfo['locale'],
            'fallback_locale_used' => $language !== $localeInfo['locale'] ? $language : null,
        ]);
    }
}
