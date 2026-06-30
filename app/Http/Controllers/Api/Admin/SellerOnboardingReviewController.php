<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\SellerOnboarding;
use App\Support\SellerOnboardingStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SellerOnboardingReviewController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = SellerOnboarding::with(['user:id,name,email', 'profile'])
            ->orderByDesc('updated_at');

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        $items = $query->paginate((int) $request->input('per_page', 30));

        return response()->json([
            'data'      => $items->map(fn ($o) => $this->serialize($o)),
            'total'     => $items->total(),
            'page'      => $items->currentPage(),
            'last_page' => $items->lastPage(),
        ]);
    }

    public function show(SellerOnboarding $sellerOnboarding): JsonResponse
    {
        $sellerOnboarding->load(['user:id,name,email', 'profile', 'flags', 'kycAnswers.question']);

        return response()->json(['data' => $this->serializeFull($sellerOnboarding)]);
    }

    public function approve(Request $request, SellerOnboarding $sellerOnboarding): JsonResponse
    {
        $sellerOnboarding->status           = SellerOnboardingStatus::APPROVED;
        $sellerOnboarding->decision         = 'approved';
        $sellerOnboarding->approved_at      = now();
        $sellerOnboarding->verified_at      = now();
        $sellerOnboarding->expires_at       = now()->addYears(2);
        $sellerOnboarding->can_publish_boat = true;
        $sellerOnboarding->decision_reason  = $request->input('reason');
        $sellerOnboarding->save();

        return response()->json(['data' => $this->serialize($sellerOnboarding->fresh(['user', 'profile']))]);
    }

    public function reject(Request $request, SellerOnboarding $sellerOnboarding): JsonResponse
    {
        $request->validate(['reason' => 'nullable|string|max:1000']);

        $sellerOnboarding->status          = SellerOnboardingStatus::REJECTED;
        $sellerOnboarding->decision        = 'rejected';
        $sellerOnboarding->can_publish_boat = false;
        $sellerOnboarding->decision_reason  = $request->input('reason');
        $sellerOnboarding->save();

        return response()->json(['data' => $this->serialize($sellerOnboarding->fresh(['user', 'profile']))]);
    }

    private function serialize(SellerOnboarding $o): array
    {
        return [
            'id'                    => $o->id,
            'status'                => $o->status,
            'decision'              => $o->decision,
            'kyc_status'            => $o->kyc_status,
            'risk_score'            => $o->risk_score,
            'manual_review_required' => (bool) $o->manual_review_required,
            'can_publish_boat'      => (bool) $o->can_publish_boat,
            'approved_at'           => $o->approved_at?->toIso8601String(),
            'verified_at'           => $o->verified_at?->toIso8601String(),
            'submitted_at'          => $o->submitted_at?->toIso8601String(),
            'created_at'            => $o->created_at?->toIso8601String(),
            'user'                  => $o->user ? [
                'id'    => $o->user->id,
                'name'  => $o->user->name,
                'email' => $o->user->email,
            ] : null,
            'profile' => $o->profile ? [
                'seller_type'  => $o->profile->seller_type,
                'full_name'    => $o->profile->full_name,
                'email'        => $o->profile->email,
                'phone'        => $o->profile->phone,
                'city'         => $o->profile->city,
                'country'      => $o->profile->country,
                'company_name' => $o->profile->company_name,
                'kvk_number'   => $o->profile->kvk_number,
            ] : null,
        ];
    }

    private function serializeFull(SellerOnboarding $o): array
    {
        return array_merge($this->serialize($o), [
            'decision_reason' => $o->decision_reason,
            'flags' => $o->flags->map(fn ($f) => [
                'flag_code' => $f->flag_code,
                'severity'  => $f->severity,
                'message'   => $f->message,
                'is_blocking' => $f->is_blocking,
            ]),
            'kyc_answers' => $o->kycAnswers->map(fn ($a) => [
                'question' => $a->question?->prompt,
                'value'    => $a->normalized_value,
            ]),
        ]);
    }
}
