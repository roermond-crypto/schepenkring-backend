<?php

namespace App\Http\Controllers\Api\Onboarding;

use App\Http\Controllers\Controller;
use App\Models\BuyerVerification;
use App\Models\KycQuestion;
use App\Services\BuyerVerificationOrchestrator;
use App\Support\BuyerVerificationStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BuyerVerificationController extends Controller
{
    public function start(Request $request, BuyerVerificationOrchestrator $orchestrator): JsonResponse
    {
        $user = $this->ensureBuyer($request);
        $verification = $orchestrator->getOrCreate($user);

        return response()->json([
            'message' => 'Buyer verification ready',
            'data' => $this->formatStatus($verification),
        ]);
    }

    public function status(Request $request, BuyerVerificationOrchestrator $orchestrator): JsonResponse
    {
        $user = $this->ensureBuyer($request);
        $verification = $orchestrator->getOrCreate($user);

        return response()->json([
            'data' => $this->formatStatus($verification->fresh([
                'profile',
                'flags',
                'latestSignhostPhase',
                'reviews',
            ])),
        ]);
    }

    public function updateProfile(Request $request, BuyerVerificationOrchestrator $orchestrator): JsonResponse
    {
        $user = $this->ensureBuyer($request);

        $validated = $request->validate([
            'buyer_type' => 'required|in:private,business',
            'full_name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'phone' => 'required|string|max:50',
            'address_line_1' => 'required|string|max:255',
            'address_line_2' => 'nullable|string|max:255',
            'city' => 'required|string|max:255',
            'state' => 'nullable|string|max:255',
            'postal_code' => 'required|string|max:50',
            'country' => 'required|string|size:2',
            'birth_date' => 'required|date',
            'iban' => 'nullable|string|max:64',
            'company_name' => 'nullable|string|max:255',
            'kvk_number' => 'nullable|string|max:100',
        ]);

        if ($validated['buyer_type'] === 'business') {
            $request->validate([
                'company_name' => 'required|string|max:255',
                'kvk_number' => 'required|string|max:100',
            ]);
        }

        $verification = $orchestrator->saveProfile($user, $validated);

        return response()->json([
            'message' => 'Buyer profile saved',
            'data' => $this->formatStatus($verification),
        ]);
    }

    public function startSignhost(Request $request, BuyerVerificationOrchestrator $orchestrator): JsonResponse
    {
        $user = $this->ensureBuyer($request);
        $verification = $orchestrator->getOrCreate($user);

        try {
            if ($verification->idin_status !== 'completed') {
                $phase = $orchestrator->startVerification($verification, 'idin');
            } elseif ($verification->ideal_status !== 'completed') {
                $phase = $orchestrator->startVerification($verification, 'ideal');
            } else {
                return response()->json(['message' => 'No Signhost step is currently available.'], 422);
            }
        } catch (\RuntimeException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 503);
        }

        return response()->json([
            'message' => 'Buyer verification flow started',
            'redirect_url' => $phase->redirect_url,
            'phase' => $phase,
            'data' => $this->formatStatus($verification->fresh(['profile', 'flags', 'latestSignhostPhase'])),
        ]);
    }

    public function verificationRedirect(Request $request, BuyerVerificationOrchestrator $orchestrator): JsonResponse
    {
        $user = $this->ensureBuyer($request);
        $verification = $orchestrator->getOrCreate($user);
        $phase = $verification->signhostTransactions()
            ->whereIn('status', ['pending', 'started', 'signing'])
            ->latest('id')
            ->first();

        return response()->json([
            'redirect_url' => $phase?->redirect_url,
            'phase' => $phase,
            'data' => $this->formatStatus($verification->fresh(['profile', 'flags', 'latestSignhostPhase'])),
        ]);
    }

    public function kycQuestions(Request $request, BuyerVerificationOrchestrator $orchestrator): JsonResponse
    {
        $user = $this->ensureBuyer($request);
        $verification = $orchestrator->getOrCreate($user);
        $answers = $verification->kycAnswers()->get()->keyBy('question_key');

        $questions = KycQuestion::query()
            ->with('options')
            ->where('is_active', true)
            ->whereIn('audience', ['buyer', 'both'])
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->filter(function (KycQuestion $question) use ($answers) {
                $conditions = $question->conditions_json ?? [];
                if (empty($conditions)) {
                    return true;
                }

                $dependencyKey = (string) ($conditions['question_key'] ?? '');
                if ($dependencyKey === '') {
                    return true;
                }

                $expected = strtolower(trim((string) ($conditions['value'] ?? '')));
                $actual = strtolower(trim((string) ($answers[$dependencyKey]?->normalized_value ?? '')));

                return $expected === '' || $actual === $expected;
            })
            ->values()
            ->map(function (KycQuestion $question) use ($answers) {
                return [
                    'id' => $question->id,
                    'key' => $question->key,
                    'prompt' => $question->prompt,
                    'input_type' => $question->input_type,
                    'required' => $question->required,
                    'audience' => $question->audience,
                    'conditions' => $question->conditions_json,
                    'answer' => $answers[$question->key]?->normalized_value,
                    'options' => $question->options->map(fn ($option) => [
                        'id' => $option->id,
                        'value' => $option->value,
                        'label' => $option->label,
                    ])->values(),
                ];
            });

        return response()->json([
            'questions' => $questions,
            'data' => $this->formatStatus($verification->fresh(['profile', 'flags', 'latestSignhostPhase'])),
        ]);
    }

    public function answerKyc(Request $request, BuyerVerificationOrchestrator $orchestrator): JsonResponse
    {
        $user = $this->ensureBuyer($request);
        $verification = $orchestrator->getOrCreate($user);

        $validated = $request->validate([
            'answers' => 'required|array|min:1',
        ]);

        $verification = $orchestrator->saveAnswers($verification, $validated['answers']);

        return response()->json([
            'message' => 'Buyer KYC answers saved',
            'data' => $this->formatStatus($verification),
        ]);
    }

    public function submit(Request $request, BuyerVerificationOrchestrator $orchestrator): JsonResponse
    {
        $user = $this->ensureBuyer($request);
        $verification = $orchestrator->getOrCreate($user);

        if ($verification->idin_status !== 'completed' || $verification->ideal_status !== 'completed') {
            return response()->json(['message' => 'Verification must be completed before KYC submission.'], 422);
        }

        $requiredQuestionIds = KycQuestion::query()
            ->where('is_active', true)
            ->whereIn('audience', ['buyer', 'both'])
            ->where('required', true)
            ->pluck('id');

        $answeredIds = $verification->kycAnswers()->pluck('kyc_question_id');
        if ($requiredQuestionIds->diff($answeredIds)->isNotEmpty()) {
            return response()->json(['message' => 'All required KYC questions must be answered.'], 422);
        }

        $decision = $orchestrator->submit($verification);

        return response()->json([
            'message' => 'Buyer KYC submission processed',
            'decision' => $decision,
            'data' => $this->formatStatus($verification->fresh(['profile', 'flags', 'latestSignhostPhase', 'reviews'])),
        ]);
    }

    private function ensureBuyer(Request $request)
    {
        $user = $request->user();
        if (!$user || $user->role !== 'buyer') {
            abort(403, 'Buyer account required.');
        }

        return $user;
    }

    private function formatStatus(BuyerVerification $verification): array
    {
        $providerRedirect = $verification->latestSignhostPhase?->redirect_url
            ?: $verification->signhostTransactions()
                ->whereIn('status', ['pending', 'started', 'signing'])
                ->latest('id')
                ->value('redirect_url');

        return [
            'verification_id' => $verification->id,
            'status' => $verification->status,
            'next_step' => $this->nextStep($verification),
            'buyer_type' => $verification->profile?->buyer_type,
            'idin_status' => $verification->idin_status,
            'ideal_status' => $verification->ideal_status,
            'kyc_status' => $verification->kyc_status,
            'risk_score' => $verification->risk_score,
            'manual_review_required' => $verification->manual_review_required,
            'decision' => $verification->decision,
            'verified_at' => $verification->verified_at?->toIso8601String(),
            'expires_at' => $verification->expires_at?->toIso8601String(),
            'is_currently_valid' => $verification->isCurrentlyValid(),
            'profile' => $verification->profile ? [
                'buyer_type' => $verification->profile->buyer_type,
                'full_name' => $verification->profile->full_name,
                'email' => $verification->profile->email,
                'phone' => $verification->profile->phone,
                'address_line_1' => $verification->profile->address_line_1,
                'address_line_2' => $verification->profile->address_line_2,
                'city' => $verification->profile->city,
                'state' => $verification->profile->state,
                'postal_code' => $verification->profile->postal_code,
                'country' => $verification->profile->country,
                'birth_date' => $verification->profile->birth_date?->toDateString(),
                'iban' => $verification->profile->iban,
                'company_name' => $verification->profile->company_name,
                'kvk_number' => $verification->profile->kvk_number,
            ] : null,
            'flags' => $verification->flags->map(fn ($flag) => [
                'flag_code' => $flag->flag_code,
                'severity' => $flag->severity,
                'message' => $flag->message,
                'is_blocking' => $flag->is_blocking,
            ])->values(),
            'provider_redirect_url' => $providerRedirect,
        ];
    }

    private function nextStep(BuyerVerification $verification): ?string
    {
        if ($verification->status === BuyerVerificationStatus::APPROVED) {
            if ($verification->expires_at?->isPast()) {
                return 'reverification';
            }
            return null;
        }

        if ($verification->status === BuyerVerificationStatus::MANUAL_REVIEW) {
            return 'manual_review';
        }

        if ($verification->status === BuyerVerificationStatus::REJECTED) {
            return 'rejected';
        }

        if (!$verification->profile?->buyer_type) {
            return 'profile';
        }

        if ($verification->idin_status !== 'completed' || $verification->ideal_status !== 'completed') {
            return 'verification';
        }

        if ($verification->kyc_status !== 'completed') {
            return 'kyc';
        }

        return null;
    }
}
