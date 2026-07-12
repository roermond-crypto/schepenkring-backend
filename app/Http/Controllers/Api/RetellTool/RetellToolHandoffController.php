<?php

namespace App\Http\Controllers\Api\RetellTool;

use App\Http\Controllers\Controller;
use App\Models\FollowUp;
use App\Models\Location;
use App\Models\User;
use App\Services\ActivityFeedService;
use App\Services\FollowUpService;
use App\Services\NotificationDispatchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * POST /api/integrations/retell/tools/handoffs/find-destination — the
 * warm-transfer decision from spec §11. Laravel decides whether a transfer
 * is even allowed; Retell only ever executes what this returns.
 */
class RetellToolHandoffController extends Controller
{
    use RetellToolHelpers;

    public function __construct(
        private FollowUpService $followUps,
        private ActivityFeedService $activityFeed,
        private NotificationDispatchService $notifications,
    ) {
    }

    public function findDestination(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'location_id' => 'required|integer',
            'reason' => 'nullable|string',
            'subject_type' => 'nullable|string',
            'subject_id' => 'nullable|integer',
        ]);

        return $this->safe(function () use ($validated, $request) {
            $this->assertLocationScope((int) $validated['location_id'], $this->resolveCallSession($request));

            $location = Location::find($validated['location_id']);
            $broker = $location?->default_seller_id ? User::find($location->default_seller_id) : null;

            if (! $broker || ! $broker->phone) {
                $followUp = $this->fallbackToCallback($validated);

                // Spec §11: "If nobody answers... Notify assigned broker" —
                // applies just as much when nobody was even reachable to
                // attempt the transfer in the first place.
                if ($broker) {
                    $this->notifications->notifyUser(
                        $broker,
                        'voice_call_transfer_missed',
                        'Gemiste warme overdracht',
                        $validated['reason'] ?? 'Een beller wilde worden doorverbonden, maar er was geen bereikbaar telefoonnummer geregistreerd.',
                        ['location_id' => $location?->id, 'follow_up_id' => $followUp?->id],
                    );
                }

                return response()->json([
                    'transfer_allowed' => false,
                    'transfer_type' => null,
                    'destination' => null,
                    'private_briefing' => $validated['reason'] ?? null,
                    'fallback_action' => 'create_callback',
                    'follow_up_id' => $followUp?->id,
                ]);
            }

            $this->activityFeed->record('location', $location->id, 'call.transfer.requested', "Warm transfer requested to {$broker->name}", [
                'reason' => $validated['reason'] ?? null,
            ]);

            return response()->json([
                'transfer_allowed' => true,
                'transfer_type' => 'warm',
                'destination' => $broker->phone,
                'private_briefing' => $validated['reason'] ?? null,
                'fallback_action' => 'create_callback',
            ]);
        });
    }

    private function fallbackToCallback(array $validated): ?FollowUp
    {
        if (empty($validated['subject_type']) || empty($validated['subject_id'])) {
            return null;
        }

        return $this->followUps->applyOutcome($validated['subject_type'], $validated['subject_id'], 'broker_contact_requested', [
            'ai_summary' => $validated['reason'] ?? null,
        ]);
    }
}
