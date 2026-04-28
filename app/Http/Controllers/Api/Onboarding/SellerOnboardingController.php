<?php

namespace App\Http\Controllers\Api\Onboarding;

use App\Http\Controllers\Controller;
use App\Models\KycQuestion;
use App\Models\SellerOnboarding;
use App\Services\SellerOnboardingOrchestrator;
use App\Support\SellerOnboardingStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SellerOnboardingController extends Controller
{
    public function start(Request $request, SellerOnboardingOrchestrator $orchestrator): JsonResponse
    {
        $user = $this->ensureSeller($request);
        $onboarding = $orchestrator->getOrCreate($user);

        return response()->json([
            'message' => 'Seller onboarding ready',
            'data' => $this->formatStatus($onboarding),
        ]);
    }

    public function status(Request $request, SellerOnboardingOrchestrator $orchestrator): JsonResponse
    {
        $user = $this->ensureSeller($request);
        $onboarding = $orchestrator->getOrCreate($user);

        return response()->json([
            'data' => $this->formatStatus($onboarding->fresh([
                'profile',
                'flags',
                'latestContract',
                'latestSignhostPhase',
                'reviews',
            ])),
        ]);
    }

    public function updateProfile(Request $request, SellerOnboardingOrchestrator $orchestrator): JsonResponse
    {
        $user = $this->ensureSeller($request);

        $validated = $request->validate([
            'seller_type' => 'required|in:private,business',
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

        if ($validated['seller_type'] === 'business') {
            $request->validate([
                'company_name' => 'required|string|max:255',
                'kvk_number' => 'required|string|max:100',
            ]);
        }

        $onboarding = $orchestrator->saveProfile($user, $validated);

        return response()->json([
            'message' => 'Seller profile saved',
            'data' => $this->formatStatus($onboarding),
        ]);
    }

    public function paymentSession(Request $request, SellerOnboardingOrchestrator $orchestrator): JsonResponse
    {
        $user = $this->ensureSeller($request);
        $onboarding = $orchestrator->getOrCreate($user);

        $validated = $request->validate([
            'redirect_url' => 'required|url|max:500',
            'idempotency_key' => 'nullable|string|max:255',
        ]);

        try {
            $payment = $orchestrator->createPaymentSession($onboarding, $validated);
        } catch (\RuntimeException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 502);
        }

        return response()->json([
            'payment' => $payment,
            'checkout_url' => $payment->checkout_url,
            'data' => $this->formatStatus($onboarding->fresh(['profile', 'flags', 'latestContract', 'latestSignhostPhase'])),
        ]);
    }

    public function paymentStatus(Request $request, SellerOnboardingOrchestrator $orchestrator, \App\Services\MollieService $mollie): JsonResponse
    {
        $user = $this->ensureSeller($request);
        $onboarding = $orchestrator->getOrCreate($user);
        $payment = $onboarding->payments()->latest('id')->first();

        // If not paid locally, sync with Mollie
        if ($payment && $payment->status !== 'paid' && $payment->mollie_payment_id) {
            \Illuminate\Support\Facades\Log::info('[PaymentSync] Checking Mollie for payment ' . $payment->mollie_payment_id);
            try {
                $molliePayment = $mollie->getPayment($payment->mollie_payment_id);
                \Illuminate\Support\Facades\Log::info('[PaymentSync] Mollie status: ' . ($molliePayment['status'] ?? 'unknown'));
                
                if (isset($molliePayment['status']) && $molliePayment['status'] !== $payment->status) {
                    $orchestrator->handlePaymentStatus($payment, $molliePayment['status']);
                    \Illuminate\Support\Facades\Log::info('[PaymentSync] Updated local status to: ' . $molliePayment['status']);
                }
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::error('[PaymentSync] Error: ' . $e->getMessage());
            }
        }

        return response()->json([
            'payment' => $payment->fresh(),
            'data' => $this->formatStatus($onboarding->fresh(['profile', 'flags', 'latestContract', 'latestSignhostPhase'])),
        ]);
    }

    public function generateContract(Request $request, SellerOnboardingOrchestrator $orchestrator): JsonResponse
    {
        $user = $this->ensureSeller($request);
        $onboarding = $orchestrator->getOrCreate($user);

        if ($onboarding->payment_status !== 'paid') {
            return response()->json(['message' => 'Payment must be completed before contract generation.'], 422);
        }

        $contract = $orchestrator->generateContract($onboarding);

        return response()->json([
            'message' => 'Contract generated',
            'contract' => $contract,
            'data' => $this->formatStatus($onboarding->fresh(['profile', 'flags', 'latestContract', 'latestSignhostPhase'])),
        ]);
    }

    public function startSignhost(Request $request, SellerOnboardingOrchestrator $orchestrator): JsonResponse
    {
        $user = $this->ensureSeller($request);
        $onboarding = $orchestrator->getOrCreate($user);

        if ($onboarding->payment_status !== 'paid') {
            return response()->json(['message' => 'Payment must be completed before the Signhost flow can start.'], 422);
        }

        if (!$onboarding->latestContract && $onboarding->contract_status === 'pending') {
            $orchestrator->generateContract($onboarding);
            $onboarding = $onboarding->fresh(['profile', 'flags', 'latestContract', 'latestSignhostPhase']);
        }

        try {
            if ($onboarding->idin_status !== 'completed') {
                $phase = $orchestrator->startVerification($onboarding, 'idin');
            } elseif ($onboarding->ideal_status !== 'completed') {
                $phase = $orchestrator->startVerification($onboarding, 'ideal');
            } elseif (
                $onboarding->kyc_status === 'completed'
                && strtolower((string) $onboarding->decision) === 'approved'
                && $onboarding->contract_status !== 'signed'
            ) {
                $phase = $orchestrator->startContractSigning($onboarding);
            } else {
                return response()->json(['message' => 'No Signhost step is currently available.'], 422);
            }
        } catch (\RuntimeException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 503);
        }

        return response()->json([
            'message' => 'Signhost flow started',
            'redirect_url' => $phase->redirect_url,
            'phase' => $phase,
            'data' => $this->formatStatus($onboarding->fresh(['profile', 'flags', 'latestContract', 'latestSignhostPhase'])),
        ]);
    }

    public function verificationRedirect(Request $request, SellerOnboardingOrchestrator $orchestrator): JsonResponse
    {
        $user = $this->ensureSeller($request);
        $onboarding = $orchestrator->getOrCreate($user);
        $phase = $onboarding->signhostTransactions()
            ->whereIn('status', ['pending', 'started', 'signing'])
            ->latest('id')
            ->first();

        return response()->json([
            'redirect_url' => $phase?->redirect_url,
            'phase' => $phase,
            'data' => $this->formatStatus($onboarding->fresh(['profile', 'flags', 'latestContract', 'latestSignhostPhase'])),
        ]);
    }

    public function kycQuestions(Request $request, SellerOnboardingOrchestrator $orchestrator): JsonResponse
    {
        $user = $this->ensureSeller($request);
        $onboarding = $orchestrator->getOrCreate($user);
        $profile = $onboarding->profile;
        $sellerType = $profile?->seller_type ?: 'private';
        $answers = $onboarding->kycAnswers()->get()->keyBy('question_key');

        $questions = KycQuestion::query()
            ->with('options')
            ->where('is_active', true)
            ->whereIn('audience', ['seller', 'both'])
            ->whereIn('seller_type_scope', ['all', $sellerType])
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
                    'seller_type_scope' => $question->seller_type_scope,
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
            'data' => $this->formatStatus($onboarding->fresh(['profile', 'flags', 'latestContract', 'latestSignhostPhase'])),
        ]);
    }

    public function answerKyc(Request $request, SellerOnboardingOrchestrator $orchestrator): JsonResponse
    {
        $user = $this->ensureSeller($request);
        $onboarding = $orchestrator->getOrCreate($user);

        $validated = $request->validate([
            'answers' => 'required|array|min:1',
        ]);

        $onboarding = $orchestrator->saveAnswers($onboarding, $validated['answers']);

        return response()->json([
            'message' => 'KYC answers saved',
            'data' => $this->formatStatus($onboarding),
        ]);
    }

    public function submit(Request $request, SellerOnboardingOrchestrator $orchestrator): JsonResponse
    {
        $user = $this->ensureSeller($request);
        $onboarding = $orchestrator->getOrCreate($user);

        if ($onboarding->idin_status !== 'completed' || $onboarding->ideal_status !== 'completed') {
            return response()->json(['message' => 'Verification must be completed before KYC submission.'], 422);
        }

        $requiredQuestionIds = KycQuestion::query()
            ->where('is_active', true)
            ->whereIn('audience', ['seller', 'both'])
            ->whereIn('seller_type_scope', ['all', $onboarding->profile?->seller_type ?: 'private'])
            ->where('required', true)
            ->pluck('id');

        $answeredIds = $onboarding->kycAnswers()->pluck('kyc_question_id');
        if ($requiredQuestionIds->diff($answeredIds)->isNotEmpty()) {
            return response()->json(['message' => 'All required KYC questions must be answered.'], 422);
        }

        try {
            $decision = $orchestrator->submit($onboarding);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'message' => 'KYC submission processed',
            'decision' => $decision,
            'data' => $this->formatStatus($onboarding->fresh(['profile', 'flags', 'latestContract', 'latestSignhostPhase', 'reviews'])),
        ]);
    }

    private function ensureSeller(Request $request)
    {
        $user = $request->user();
        if (!$user || $user->role !== 'seller') {
            abort(403, 'Seller account required.');
        }

        return $user;
    }

    private function formatStatus(SellerOnboarding $onboarding): array
    {
        $providerRedirect = $onboarding->latestSignhostPhase?->redirect_url
            ?: $onboarding->signhostTransactions()
                ->whereIn('status', ['pending', 'started', 'signing'])
                ->latest('id')
                ->value('redirect_url');

        return [
            'onboarding_id' => $onboarding->id,
            'status' => $onboarding->status,
            'next_step' => $this->nextStep($onboarding),
            'seller_type' => $onboarding->profile?->seller_type,
            'payment_status' => $onboarding->payment_status,
            'idin_status' => $onboarding->idin_status,
            'ideal_status' => $onboarding->ideal_status,
            'kyc_status' => $onboarding->kyc_status,
            'contract_status' => $onboarding->contract_status,
            'risk_score' => $onboarding->risk_score,
            'manual_review_required' => $onboarding->manual_review_required,
            'decision' => $onboarding->decision,
            'verified_at' => $onboarding->verified_at?->toIso8601String(),
            'expires_at' => $onboarding->expires_at?->toIso8601String(),
            'is_currently_valid' => $onboarding->isCurrentlyValid(),
            'profile' => $onboarding->profile ? [
                'seller_type' => $onboarding->profile->seller_type,
                'full_name' => $onboarding->profile->full_name,
                'email' => $onboarding->profile->email,
                'phone' => $onboarding->profile->phone,
                'address_line_1' => $onboarding->profile->address_line_1,
                'address_line_2' => $onboarding->profile->address_line_2,
                'city' => $onboarding->profile->city,
                'state' => $onboarding->profile->state,
                'postal_code' => $onboarding->profile->postal_code,
                'country' => $onboarding->profile->country,
                'birth_date' => $onboarding->profile->birth_date?->toDateString(),
                'iban' => $onboarding->profile->iban,
                'company_name' => $onboarding->profile->company_name,
                'kvk_number' => $onboarding->profile->kvk_number,
            ] : null,
            'payment' => $onboarding->payments()->latest('id')->first() ? [
                'status' => $onboarding->payments()->latest('id')->value('status'),
                'checkout_url' => $onboarding->payments()->latest('id')->value('checkout_url'),
                'paid_at' => optional($onboarding->payments()->latest('id')->first()?->paid_at)?->toIso8601String(),
            ] : null,
            'contract' => $onboarding->latestContract ? [
                'id' => $onboarding->latestContract->id,
                'type' => $onboarding->latestContract->contract_type,
                'status' => $onboarding->latestContract->status,
                'pdf_path' => $onboarding->latestContract->contract_pdf_path,
                'sign_url' => $onboarding->latestContract->sign_url,
                'generated_at' => $onboarding->latestContract->generated_at?->toIso8601String(),
                'signed_at' => $onboarding->latestContract->signed_at?->toIso8601String(),
            ] : null,
            'flags' => $onboarding->flags->map(fn ($flag) => [
                'flag_code' => $flag->flag_code,
                'severity' => $flag->severity,
                'message' => $flag->message,
                'is_blocking' => $flag->is_blocking,
            ])->values(),
            'can_publish_boat' => $onboarding->can_publish_boat,
            'provider_redirect_url' => $providerRedirect,
        ];
    }

    private function nextStep(SellerOnboarding $onboarding): ?string
    {
        if ($onboarding->status === SellerOnboardingStatus::APPROVED) {
            if ($onboarding->expires_at?->isPast()) {
                return 'reverification';
            }
            return null;
        }

        if (!$onboarding->profile?->seller_type) {
            return 'profile';
        }

        if ($onboarding->status === SellerOnboardingStatus::MANUAL_REVIEW) {
            return 'manual_review';
        }

        if ($onboarding->status === SellerOnboardingStatus::REJECTED) {
            return 'rejected';
        }

        if ($onboarding->payment_status !== 'paid') {
            return 'payment';
        }

        if (!$onboarding->latestContract || !in_array($onboarding->contract_status, ['generated', 'signing', 'signed'], true)) {
            return 'contract';
        }

        if ($onboarding->idin_status !== 'completed' || $onboarding->ideal_status !== 'completed') {
            return 'verification';
        }

        if ($onboarding->kyc_status !== 'completed') {
            return 'kyc';
        }

        if ($onboarding->contract_status !== 'signed') {
            return 'signing';
        }

        return null;
    }
}
