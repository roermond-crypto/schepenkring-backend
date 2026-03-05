<?php

namespace App\Http\Controllers;

use App\Models\InteractionTemplate;
use App\Models\InteractionTemplateTranslation;
use App\Services\ContentTranslationService;
use App\Support\TranslationStatus;
use Illuminate\Http\Request;

class InteractionTemplateController extends Controller
{
    public function index(Request $request)
    {
        $query = InteractionTemplate::query()->with('eventType');
        if ($request->filled('event_type_id')) {
            $query->where('event_type_id', $request->integer('event_type_id'));
        }
        if ($request->filled('channel')) {
            $query->where('channel', $request->string('channel'));
        }
        if ($request->filled('active')) {
            $query->where('is_active', filter_var($request->query('active'), FILTER_VALIDATE_BOOL));
        }

        return response()->json($query->orderBy('channel')->orderByDesc('version')->get());
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'event_type_id' => 'nullable|exists:interaction_event_types,id',
            'channel' => 'required|string|max:30',
            'name' => 'required|string|max:160',
            'source_locale' => 'nullable|string|max:5',
            'subject' => 'nullable|string|max:255',
            'body' => 'required|string',
            'version' => 'nullable|integer|min:1',
            'is_active' => 'nullable|boolean',
            'placeholders' => 'nullable|array',
        ]);

        $validated['source_locale'] = strtolower((string) ($validated['source_locale'] ?? $request->input('locale', config('locales.default'))));

        $template = InteractionTemplate::create(array_merge($validated, [
            'created_by' => $request->user()?->id,
            'updated_by' => $request->user()?->id,
        ]));

        $template->source_hash = app(ContentTranslationService::class)->templateSourceHash($template);
        $template->save();

        return response()->json($template, 201);
    }

    public function update(Request $request, InteractionTemplate $template)
    {
        $validated = $request->validate([
            'channel' => 'nullable|string|max:30',
            'name' => 'nullable|string|max:160',
            'source_locale' => 'nullable|string|max:5',
            'subject' => 'nullable|string|max:255',
            'body' => 'nullable|string',
            'version' => 'nullable|integer|min:1',
            'is_active' => 'nullable|boolean',
            'placeholders' => 'nullable|array',
        ]);

        if (!empty($validated['source_locale'])) {
            $validated['source_locale'] = strtolower((string) $validated['source_locale']);
        }

        $template->fill($validated);
        $template->updated_by = $request->user()?->id;
        $template->save();

        $sourceHash = app(ContentTranslationService::class)->templateSourceHash($template);
        if ($sourceHash !== $template->source_hash) {
            $template->source_hash = $sourceHash;
            $template->save();
            InteractionTemplateTranslation::where('interaction_template_id', $template->id)
                ->where(function ($query) use ($sourceHash) {
                    $query->whereNull('translated_from_hash')
                        ->orWhere('translated_from_hash', '!=', $sourceHash);
                })
                ->update(['status' => TranslationStatus::OUTDATED]);
        }

        return response()->json($template);
    }

    public function destroy(Request $request, InteractionTemplate $template)
    {
        $template->delete();
        return response()->json(['message' => 'deleted']);
    }
}
