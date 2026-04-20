<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ProfileSetupService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProfileSetupController extends Controller
{
    /**
     * Return the current user's onboarding / profile-setup status.
     *
     * Frontend uses this to decide:
     *   - Which panel to render (SellerOnboardingPanel vs BuyerVerificationPanel)
     *   - Whether to show the onboarding warning banner
     */
    public function status(Request $request, ProfileSetupService $service): JsonResponse
    {
        $user = $request->user();
        abort_if(!$user, 401, 'Unauthorized.');

        return response()->json([
            'data' => $service->statusFor($user->loadMissing(['sellerProfile', 'buyerProfile', 'sellerOnboarding', 'buyerVerification'])),
        ]);
    }

    /**
     * Search for address predictions using Google Places.
     */
    public function search(Request $request, ProfileSetupService $service): JsonResponse
    {
        $user = $request->user();
        abort_if(!$user, 401, 'Unauthorized.');

        $validated = $request->validate([
            'q' => 'required|string|min:3|max:255',
        ]);

        $result = $service->search($validated['q']);

        return response()->json([
            'data' => $result['items'] ?? [],
            'error' => $result['error'] ?? null,
        ]);
    }

    /**
     * Save a selected address to the user's profile.
     */
    public function saveAddress(Request $request, ProfileSetupService $service): JsonResponse
    {
        $user = $request->user();
        abort_if(!$user, 401, 'Unauthorized.');

        $validated = $request->validate([
            'place_id' => 'required|string|max:255',
        ]);

        try {
            $status = $service->saveAddress($user->loadMissing(['sellerProfile', 'buyerProfile']), $validated['place_id']);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'message' => 'Address saved',
            'data' => $status,
        ]);
    }
}
