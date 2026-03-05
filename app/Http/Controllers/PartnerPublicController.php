<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Yacht;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PartnerPublicController extends Controller
{
    /**
     * Display all yachts of the partner identified by the token.
     */
    public function showFleet(string $token): JsonResponse
    {
        $partner = User::where('partner_token', $token)
            ->where('role', 'Partner')
            ->where('status', 'Active') // only active partners
            ->first();

        if (!$partner) {
            return response()->json(['message' => 'Invalid or expired link.'], 404);
        }

        $yachts = Yacht::with(['images', 'availabilityRules'])
            ->where('user_id', $partner->id)
            // Optionally exclude drafts – public should see only published
            ->where('status', '!=', 'Draft')
            ->orderBy('boat_name', 'asc')
            ->get();

        return response()->json([
            'partner' => [
                'name' => $partner->name,
                'email' => $partner->email,
            ],
            'yachts' => $yachts,
        ]);
    }
}