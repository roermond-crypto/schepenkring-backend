<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\HarborChannel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Provisions HarborChannel rows for voice numbers. No admin UI has ever
 * existed for HarborChannel, for any provider — rows were only ever
 * created via tests or a direct DB insert (confirmed during the planning
 * audit). This is the first one, scoped to channel=voice so it doesn't
 * accidentally touch the WhatsApp/phone(Telnyx) rows the same table holds.
 */
class VoiceNumberController extends Controller
{
    public function index(): JsonResponse
    {
        $numbers = HarborChannel::with('location:id,name')
            ->where('channel', 'voice')
            ->orderBy('harbor_id')
            ->get();

        return response()->json(['data' => $numbers]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $this->validated($request);
        $validated['channel'] = 'voice';
        $validated['provider'] = $validated['provider'] ?? 'retell';

        $channel = HarborChannel::create($validated);

        return response()->json(['data' => $channel], 201);
    }

    public function update(Request $request, HarborChannel $number): JsonResponse
    {
        if ($number->channel !== 'voice') {
            abort(404);
        }

        $number->update($this->validated($request));

        return response()->json(['data' => $number]);
    }

    public function destroy(HarborChannel $number): JsonResponse
    {
        if ($number->channel !== 'voice') {
            abort(404);
        }

        $number->delete();

        return response()->json(['deleted' => true]);
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'harbor_id' => 'required|integer|exists:locations,id',
            'provider' => 'nullable|string|in:retell,telnyx',
            'from_number' => 'required|string|max:32',
            'status' => 'nullable|string|in:active,inactive',
            'metadata' => 'nullable|array',
        ]);
    }
}
