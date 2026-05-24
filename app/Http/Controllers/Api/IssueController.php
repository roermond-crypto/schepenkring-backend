<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\AnalyzeIssueWithAI;
use App\Models\Issue;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class IssueController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'required|string|max:5000',
            'yacht_id' => 'nullable|integer|exists:yachts,id',
        ]);

        $issue = Issue::create([
            ...$validated,
            'user_id' => $request->user()->id,
        ]);

        AnalyzeIssueWithAI::dispatch($issue);

        return response()->json([
            'message' => 'Issue reported. AI analysis is running in the background.',
            'issue_id' => $issue->id,
        ], 202);
    }

    public function retryAi(int $id): JsonResponse
    {
        $issue = Issue::findOrFail($id);
        AnalyzeIssueWithAI::dispatch($issue);

        return response()->json(['message' => 'AI analysis re-queued.']);
    }
}
