<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Yacht;
use App\Services\AuctionService;
use App\Services\LocationAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BoatAuctionController extends Controller
{
    public function __construct(
        private AuctionService $auctions,
        private LocationAccessService $locationAccess
    ) {
    }

    public function start(Request $request, int $yachtId): JsonResponse
    {
        $request->validate([
            'duration_minutes' => ['nullable', 'integer', 'min:1', 'max:1440'],
        ]);

        $yacht = Yacht::with('owner')->findOrFail($yachtId);
        $this->authorizeYachtAccess($request->user(), $yacht);

        $this->auctions->startLiveAuction($yacht, $request->user(), $request->integer('duration_minutes') ?: null);

        return response()->json([
            'auction' => $this->auctions->publicState($yacht->fresh()->load('owner'), null, true, 10, false),
        ]);
    }

    public function end(Request $request, int $yachtId): JsonResponse
    {
        $yacht = Yacht::with('owner')->findOrFail($yachtId);
        $this->authorizeYachtAccess($request->user(), $yacht);

        $session = $this->auctions->endLiveAuction($yacht, $request->user());

        return response()->json([
            'auction' => $this->auctions->publicState($yacht->fresh()->load('owner'), null, true, 10, false),
            'ended_session_id' => $session?->id,
        ]);
    }

    private function authorizeYachtAccess(User $user, Yacht $yacht): void
    {
        if ($user->isAdmin()) {
            return;
        }

        if ($user->isClient() && (int) $yacht->user_id === (int) $user->id) {
            return;
        }

        if ($user->isEmployee()) {
            $locationId = $this->resolveYachtLocationId($yacht);
            if ($locationId !== null && $this->locationAccess->sharesLocation($user, $locationId)) {
                return;
            }
        }

        abort(403, 'Forbidden');
    }

    private function resolveYachtLocationId(Yacht $yacht): ?int
    {
        if ($yacht->location_id) {
            return (int) $yacht->location_id;
        }

        if ($yacht->ref_harbor_id) {
            return (int) $yacht->ref_harbor_id;
        }

        return $yacht->owner?->client_location_id ? (int) $yacht->owner->client_location_id : null;
    }
}
