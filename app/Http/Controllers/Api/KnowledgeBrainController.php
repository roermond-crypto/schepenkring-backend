<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\KnowledgeBrainSuggestion;
use App\Services\KnowledgeBrainService;
use Illuminate\Http\Request;

class KnowledgeBrainController extends Controller
{
    public function __construct(private KnowledgeBrainService $brain)
    {
    }

    public function show(Request $request)
    {
        $user = $this->requireAdmin($request);

        $validated = $request->validate([
            'location_id' => 'nullable|integer|exists:locations,id',
        ]);

        return response()->json($this->brain->overview($user, $validated));
    }

    public function questions(Request $request)
    {
        $user = $this->requireAdmin($request);

        $validated = $request->validate([
            'location_id' => 'nullable|integer|exists:locations,id',
            'status' => 'nullable|string|in:pending,approved,declined',
            'search' => 'nullable|string|max:255',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        return response()->json($this->brain->listQuestions($user, $validated));
    }

    public function suggestions(Request $request)
    {
        $user = $this->requireAdmin($request);

        $validated = $request->validate([
            'location_id' => 'nullable|integer|exists:locations,id',
            'type' => 'nullable|string|in:missing_question,answer_improvement,duplicate_cluster,document_gap',
            'status' => 'nullable|string|in:pending,approved,declined',
            'search' => 'nullable|string|max:255',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        return response()->json($this->brain->listSuggestions($user, $validated));
    }

    public function refresh(Request $request)
    {
        $user = $this->requireAdmin($request);

        $validated = $request->validate([
            'location_id' => 'nullable|integer|exists:locations,id',
        ]);

        $summary = $this->brain->refresh($user, $validated);

        return response()->json([
            'message' => 'Knowledge Brain refreshed',
            'summary' => $summary,
        ]);
    }

    public function review(Request $request, KnowledgeBrainSuggestion $suggestion)
    {
        $user = $this->requireAdmin($request);

        $validated = $request->validate([
            'status' => 'required|string|in:pending,approved,declined',
            'question' => 'sometimes|nullable|string|max:500',
            'answer' => 'sometimes|nullable|string',
            'summary' => 'sometimes|nullable|string',
            'category' => 'sometimes|nullable|string|max:100',
            'language' => 'sometimes|nullable|string|max:5',
            'department' => 'sometimes|nullable|string|max:100',
            'visibility' => 'sometimes|nullable|string|in:internal,staff,public',
            'brand' => 'sometimes|nullable|string|max:100',
            'model' => 'sometimes|nullable|string|max:100',
            'tags' => 'sometimes|nullable|array',
            'tags.*' => 'string|max:50',
            'primary_faq_id' => 'sometimes|nullable|integer|exists:faqs,id',
        ]);

        $updated = $this->brain->reviewSuggestion($user, $suggestion, $validated);

        return response()->json([
            'message' => 'Knowledge Brain suggestion reviewed',
            'suggestion' => $updated,
        ]);
    }

    private function requireAdmin(Request $request)
    {
        $user = $request->user();
        if (! $user) {
            abort(401, 'Unauthorized');
        }

        if (! $user->isAdmin()) {
            abort(403, 'Forbidden');
        }

        return $user;
    }
}
