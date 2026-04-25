<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ListingWorkflow;
use App\Services\SellerIntakeWorkflowService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ListingWorkflowController extends Controller
{
    public function show(Request $request, int $id, SellerIntakeWorkflowService $service): JsonResponse
    {
        $user = $request->user();
        abort_if(! $user, 401, 'Unauthorized.');

        $workflow = ListingWorkflow::query()
            ->where('user_id', $user->id)
            ->with(['intake.latestPayment', 'yacht', 'versions', 'reviews', 'assignedAdmin', 'user'])
            ->findOrFail($id);

        return response()->json([
            'data' => $service->serializeWorkflow($workflow),
        ]);
    }

    public function preview(Request $request, int $id, SellerIntakeWorkflowService $service): JsonResponse
    {
        $user = $request->user();
        abort_if(! $user, 401, 'Unauthorized.');

        $workflow = ListingWorkflow::query()
            ->where('user_id', $user->id)
            ->with(['intake.latestPayment', 'yacht', 'versions', 'reviews', 'assignedAdmin', 'user'])
            ->findOrFail($id);

        return response()->json([
            'data' => [
                'workflow' => $service->serializeWorkflow($workflow),
                'preview' => $service->buildPreview($workflow),
            ],
        ]);
    }

    public function approve(Request $request, int $id, SellerIntakeWorkflowService $service): JsonResponse
    {
        $user = $request->user();
        abort_if(! $user, 401, 'Unauthorized.');

        $workflow = ListingWorkflow::query()->where('user_id', $user->id)->findOrFail($id);
        $validated = $request->validate([
            'message' => 'nullable|string|max:2000',
        ]);

        $workflow = $service->approveByClient($workflow, $user, $validated['message'] ?? null);

        return response()->json([
            'message' => 'Listing approved',
            'data' => $service->serializeWorkflow($workflow),
        ]);
    }

    public function requestChanges(Request $request, int $id, SellerIntakeWorkflowService $service): JsonResponse
    {
        $user = $request->user();
        abort_if(! $user, 401, 'Unauthorized.');

        $workflow = ListingWorkflow::query()->where('user_id', $user->id)->findOrFail($id);
        $validated = $request->validate([
            'message' => 'nullable|string|max:2000',
        ]);

        $workflow = $service->requestChanges($workflow, $user, $validated['message'] ?? null);

        return response()->json([
            'message' => 'Changes requested',
            'data' => $service->serializeWorkflow($workflow),
        ]);
    }
}
