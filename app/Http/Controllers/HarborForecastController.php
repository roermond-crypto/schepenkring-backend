<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\HarborForecastService;
use Illuminate\Http\Request;

class HarborForecastController extends Controller
{
    public function __construct(private HarborForecastService $service)
    {
    }

    public function show(User $harbor, Request $request)
    {
        $this->ensurePartner($harbor);
        $actor = $request->user();
        if (!$actor || (strtolower((string) $actor->role) !== 'admin' && $actor->id !== $harbor->id)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        return response()->json($this->service->buildForHarbor($harbor->id));
    }

    public function myForecast(Request $request)
    {
        $user = $request->user();
        if (!$user || strtolower((string) $user->role) !== 'partner') {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        return response()->json($this->service->buildForHarbor($user->id));
    }

    private function ensurePartner(User $harbor): void
    {
        if (strtolower((string) $harbor->role) !== 'partner') {
            abort(404, 'Harbor not found');
        }
    }
}
