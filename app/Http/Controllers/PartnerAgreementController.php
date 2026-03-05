<?php

namespace App\Http\Controllers;

use App\Models\PartnerAgreement;
use App\Models\PartnerProfile;
use App\Services\OnboardingService;
use App\Services\PartnerAgreementService;
use Illuminate\Http\Request;

class PartnerAgreementController extends Controller
{
    public function show(Request $request, PartnerAgreementService $agreements)
    {
        $user = $request->user();
        if (!$user || strtolower((string) $user->role) !== 'partner') {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        return response()->json([
            'agreement_version' => $agreements->currentVersion(),
            'agreement_text' => $agreements->currentText(),
            'status' => $user->status,
        ]);
    }

    public function accept(
        Request $request,
        PartnerAgreementService $agreements,
        OnboardingService $onboarding
    ) {
        $user = $request->user();
        if (!$user || strtolower((string) $user->role) !== 'partner') {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        if (strtolower((string) $user->status) !== 'pending_agreement') {
            return response()->json(['message' => 'Agreement step not available'], 403);
        }

        $validated = $request->validate([
            'accepted' => 'required|boolean',
        ]);

        if (!$validated['accepted']) {
            return response()->json(['message' => 'Agreement must be accepted'], 422);
        }

        $text = $agreements->currentText();
        $version = $agreements->currentVersion();
        $hash = hash('sha256', $text);

        PartnerAgreement::create([
            'user_id' => $user->id,
            'agreement_version' => $version,
            'agreement_text' => $text,
            'agreement_hash' => $hash,
            'accepted_at' => now(),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        PartnerProfile::where('user_id', $user->id)->update([
            'agreement_version' => $version,
            'agreement_accepted_at' => now(),
        ]);

        $user->status = 'contract_pending';
        $user->save();

        $onboarding->markStep($user, 'contract', $request);

        return response()->json([
            'message' => 'Agreement accepted',
            'status' => $user->status,
            'next_step' => '/partner/contract-signing',
        ]);
    }
}
