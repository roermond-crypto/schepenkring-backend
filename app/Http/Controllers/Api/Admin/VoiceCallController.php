<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\CallSession;
use App\Services\LocationAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VoiceCallController extends Controller
{
    public function __construct(private LocationAccessService $locationAccess)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'provider' => 'nullable|string',
            'direction' => 'nullable|string',
            'outcome' => 'nullable|string',
            'campaign_id' => 'nullable|integer',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $user = $request->user();
        $query = CallSession::with(['seller:id,name', 'yacht:id,boat_name', 'location:id,name', 'campaign:id,name'])
            ->orderByDesc('created_at');

        $query = $this->locationAccess->scopeQuery($query, $user, 'harbor_id');

        if (! empty($validated['provider'])) {
            $query->where('provider', $validated['provider']);
        }
        if (! empty($validated['direction'])) {
            $query->where('direction', $validated['direction']);
        }
        if (! empty($validated['outcome'])) {
            $query->where('outcome', $validated['outcome']);
        }
        if (! empty($validated['campaign_id'])) {
            $query->where('campaign_id', $validated['campaign_id']);
        }

        return response()->json($query->paginate($validated['per_page'] ?? 25));
    }

    public function show(Request $request, CallSession $callSession): JsonResponse
    {
        if (! $this->locationAccess->sharesLocation($request->user(), $callSession->harbor_id)) {
            abort(403, 'Forbidden');
        }

        $callSession->load(['seller:id,name', 'yacht:id,boat_name', 'location:id,name', 'campaign:id,name', 'transcripts']);

        return response()->json(['data' => $callSession]);
    }
}
