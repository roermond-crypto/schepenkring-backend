<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ChatAbuseService;
use App\Models\Location;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;

class ChatWidgetController extends Controller
{
    public function init(Request $request, ChatAbuseService $abuse)
    {
        $request->validate([
            'visitor_id' => 'nullable|string|max:64',
            'harbor_id' => 'nullable|integer',
            'boat_id' => 'nullable|integer',
        ]);

        $abuse->ensureNotBlocked(null, null, null, $request->ip());
        $abuse->rateLimit($request, $request->input('visitor_id'), null);

        $visitorId = $request->input('visitor_id') ?: (string) Str::uuid();
        $sessionId = (string) Str::uuid();
        $harborId = $request->input('harbor_id');
        $boatId = $request->input('boat_id');
        $boat = null;

        // Auto-resolve location from boat or user session
        if ($boatId) {
            $boat = \App\Models\Yacht::find($boatId);
            if ($boat && $boat->location_id) {
                $harborId = $boat->location_id;
            }
        }

        // If logged-in user, could resolve location automatically, but for now fallback to Location 1 if none
        if (!$harborId) {
            $harborId = Location::query()->value('id') ?? 1;
        }

        $payload = [
            'visitor_id' => $visitorId,
            'session_id' => $sessionId,
            'harbor_id' => $harborId,
            'issued_at' => now()->timestamp,
        ];

        $sessionJwt = Crypt::encryptString(json_encode($payload));
        $location = Location::find($harborId);

        return response()->json([
            'visitor_id' => $visitorId,
            'session_id' => $sessionId,
            'session_jwt' => $sessionJwt,
            'context' => [
                'location' => $location ? [
                    'id' => $location->id,
                    'name' => $location->name ?? null,
                    'branding' => $location->widget_settings ?? null,
                    'texts' => $location->texts ?? null,
                ] : null,
                'boat' => $boat ? [
                    'id' => $boat->id,
                    'name' => trim(($boat->make ?? '') . ' ' . ($boat->model ?? '')),
                    'status' => $boat->status ?? null,
                ] : null,
                'tabs_enabled' => ['chat', 'tasks', 'booking'], // Hardcoded logic for enabled tabs for now
            ]
        ]);
    }
}
