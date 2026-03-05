<?php

namespace App\Http\Controllers;

use App\Jobs\GenerateInteractionTemplateTranslation;
use App\Models\InteractionTemplateTranslation;
use App\Support\TranslationStatus;
use Illuminate\Http\Request;

class InteractionTemplateTranslationController extends Controller
{
    public function index(Request $request)
    {
        $query = InteractionTemplateTranslation::with('template');

        if ($request->filled('template_id')) {
            $query->where('interaction_template_id', (int) $request->query('template_id'));
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
            'template_ids' => 'nullable|array',
            'template_ids.*' => 'integer',
            'target_locale' => 'required|string|max:5',
            'force' => 'nullable|boolean',
        ]);

        $templateIds = $validated['template_ids'] ?? [];
        $target = strtolower($validated['target_locale']);
        $force = (bool) ($validated['force'] ?? false);

        if (empty($templateIds)) {
            return response()->json(['message' => 'template_ids required'], 422);
        }

        foreach ($templateIds as $templateId) {
            GenerateInteractionTemplateTranslation::dispatch($templateId, $target, $force);
        }

        return response()->json([
            'message' => 'Interaction template translation jobs queued',
            'queued' => count($templateIds),
            'target_locale' => $target,
        ]);
    }

    public function update(Request $request, InteractionTemplateTranslation $translation)
    {
        $validated = $request->validate([
            'subject' => 'nullable|string|max:255',
            'body' => 'nullable|string',
            'status' => 'nullable|string|in:' . implode(',', TranslationStatus::all()),
        ]);

        $translation->fill($validated);
        $translation->save();

        return response()->json($translation);
    }

    public function approve(Request $request, InteractionTemplateTranslation $translation)
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
