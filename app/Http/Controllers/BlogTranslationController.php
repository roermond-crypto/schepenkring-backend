<?php

namespace App\Http\Controllers;

use App\Jobs\GenerateBlogTranslation;
use App\Models\BlogTranslation;
use App\Support\TranslationStatus;
use Illuminate\Http\Request;

class BlogTranslationController extends Controller
{
    public function index(Request $request)
    {
        $query = BlogTranslation::with('blog');

        if ($request->filled('blog_id')) {
            $query->where('blog_id', (int) $request->query('blog_id'));
        }
        if ($request->filled('locale')) {
            $query->where('locale', strtolower($request->query('locale')));
        }
        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }

        return response()->json($query->orderByDesc('updated_at')->paginate(25));
    }

    public function generate(Request $request)
    {
        $validated = $request->validate([
            'blog_ids' => 'nullable|array',
            'blog_ids.*' => 'integer',
            'target_locale' => 'required|string|max:5',
            'force' => 'nullable|boolean',
        ]);

        $blogIds = $validated['blog_ids'] ?? [];
        $target = strtolower($validated['target_locale']);
        $force = (bool) ($validated['force'] ?? false);

        if (empty($blogIds)) {
            return response()->json(['message' => 'blog_ids required'], 422);
        }

        foreach ($blogIds as $blogId) {
            GenerateBlogTranslation::dispatch($blogId, $target, $force);
        }

        return response()->json([
            'message' => 'Blog translation jobs queued',
            'queued' => count($blogIds),
            'target_locale' => $target,
        ]);
    }

    public function update(Request $request, BlogTranslation $translation)
    {
        $validated = $request->validate([
            'title' => 'nullable|string|max:255',
            'excerpt' => 'nullable|string|max:500',
            'content' => 'nullable|string',
            'meta_title' => 'nullable|string|max:255',
            'meta_description' => 'nullable|string|max:255',
            'status' => 'nullable|string|in:' . implode(',', TranslationStatus::all()),
        ]);

        $translation->fill($validated);
        $translation->save();

        return response()->json($translation);
    }

    public function approve(Request $request, BlogTranslation $translation)
    {
        $validated = $request->validate([
            'legal' => 'nullable|boolean',
        ]);

        $translation->status = ($validated['legal'] ?? false)
            ? TranslationStatus::LEGAL_APPROVED
            : TranslationStatus::REVIEWED;
        $translation->save();

        return response()->json([
            'message' => 'Translation approved',
            'translation' => $translation,
        ]);
    }
}
