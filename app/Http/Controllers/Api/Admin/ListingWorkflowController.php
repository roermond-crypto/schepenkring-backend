<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\ListingWorkflow;
use App\Services\SellerIntakeWorkflowService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ListingWorkflowController extends Controller
{
    public function index(Request $request, SellerIntakeWorkflowService $service): JsonResponse
    {
        $status = trim((string) $request->query('status', ''));
        $query = ListingWorkflow::query()
            ->with(['intake.latestPayment', 'user', 'assignedAdmin', 'yacht'])
            ->latest('id');

        if ($status !== '') {
            $query->where('status', $status);
        }

        $items = $query->limit(100)->get()->map(
            fn (ListingWorkflow $workflow) => $service->serializeWorkflow($workflow)
        );

        return response()->json([
            'data' => $items,
        ]);
    }

    public function show(int $id, SellerIntakeWorkflowService $service): JsonResponse
    {
        $workflow = ListingWorkflow::query()
            ->with(['intake.latestPayment', 'versions', 'reviews', 'user', 'assignedAdmin', 'yacht'])
            ->findOrFail($id);

        return response()->json([
            'data' => $service->serializeWorkflow($workflow),
        ]);
    }

    public function startAi(Request $request, int $id, SellerIntakeWorkflowService $service): JsonResponse
    {
        $workflow = ListingWorkflow::query()->with(['intake', 'user', 'yacht'])->findOrFail($id);
        $workflow = $service->startAiGeneration($workflow, $request->user());

        return response()->json([
            'message' => 'AI draft generated',
            'data' => $service->serializeWorkflow($workflow),
        ]);
    }

    public function markReviewed(Request $request, int $id, SellerIntakeWorkflowService $service): JsonResponse
    {
        $workflow = ListingWorkflow::query()->with(['intake', 'user', 'yacht'])->findOrFail($id);
        $validated = $request->validate([
            'message' => 'nullable|string|max:2000',
        ]);

        $workflow = $service->markReviewed($workflow, $request->user(), $validated['message'] ?? null);

        return response()->json([
            'message' => 'Listing marked as reviewed',
            'data' => $service->serializeWorkflow($workflow),
        ]);
    }

    public function publish(Request $request, int $id, SellerIntakeWorkflowService $service): JsonResponse
    {
        $workflow = ListingWorkflow::query()->with(['intake', 'user', 'yacht'])->findOrFail($id);

        try {
            $workflow = $service->publish($workflow, $request->user());
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'message' => 'Listing published',
            'data' => $service->serializeWorkflow($workflow),
        ]);
    }

    public function reject(Request $request, int $id, SellerIntakeWorkflowService $service): JsonResponse
    {
        $workflow = ListingWorkflow::query()->findOrFail($id);
        $validated = $request->validate([
            'message' => 'nullable|string|max:2000',
        ]);

        $workflow = $service->reject($workflow, $request->user(), $validated['message'] ?? null);

        return response()->json([
            'message' => 'Listing rejected',
            'data' => $service->serializeWorkflow($workflow),
        ]);
    }

    public function archive(Request $request, int $id, SellerIntakeWorkflowService $service): JsonResponse
    {
        $workflow = ListingWorkflow::query()->findOrFail($id);
        $validated = $request->validate([
            'message' => 'nullable|string|max:2000',
        ]);

        $workflow = $service->archive($workflow, $request->user(), $validated['message'] ?? null);

        return response()->json([
            'message' => 'Listing archived',
            'data' => $service->serializeWorkflow($workflow),
        ]);
    }
}
