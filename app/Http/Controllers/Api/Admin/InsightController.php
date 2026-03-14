<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\AiDailyInsight;
use App\Services\AiDailyInsightService;
use Illuminate\Http\Request;

class InsightController extends Controller
{
    public function __construct(private AiDailyInsightService $insights)
    {
    }

    public function index(Request $request)
    {
        $this->requireAdmin($request);

        $validated = $request->validate([
            'status' => 'nullable|string|in:running,completed,failed',
            'environment' => 'nullable|string|max:50',
            'from' => 'nullable|date',
            'to' => 'nullable|date',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $paginator = $this->insights->list($validated);
        $paginator->setCollection(
            $paginator->getCollection()->map(fn (AiDailyInsight $insight) => $this->insights->serialize($insight))
        );

        return response()->json($paginator);
    }

    public function latest(Request $request)
    {
        $this->requireAdmin($request);

        $validated = $request->validate([
            'environment' => 'nullable|string|max:50',
        ]);

        $insight = $this->insights->latest($validated['environment'] ?? null);

        return response()->json([
            'data' => $insight ? $this->insights->serialize($insight) : null,
        ]);
    }

    public function show(Request $request, AiDailyInsight $insight)
    {
        $this->requireAdmin($request);

        $validated = $request->validate([
            'include_raw' => 'nullable|boolean',
        ]);

        return response()->json([
            'data' => $this->insights->serialize($insight, (bool) ($validated['include_raw'] ?? false)),
        ]);
    }

    public function generate(Request $request)
    {
        $this->requireAdmin($request);

        $validated = $request->validate([
            'start' => 'nullable|date',
            'end' => 'nullable|date',
            'timezone' => 'nullable|string|max:100',
        ]);

        $insight = $this->insights->generate($validated);

        return response()->json([
            'message' => 'AI insights generated',
            'data' => $this->insights->serialize($insight),
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
