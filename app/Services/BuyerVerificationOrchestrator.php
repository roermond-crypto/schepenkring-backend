<?php

namespace App\Services;

use App\Models\BuyerProfile;
use App\Models\BuyerVerification;
use App\Models\BuyerVerificationKycAnswer;
use App\Models\BuyerVerificationReview;
use App\Models\BuyerVerificationSignhostTransaction;
use App\Models\KycQuestion;
use App\Models\User;
use App\Support\BuyerVerificationStatus;
use Illuminate\Support\Facades\DB;

class BuyerVerificationOrchestrator
{
    public function __construct(
        private readonly SignhostService $signhost,
        private readonly BuyerVerificationDecisionService $decisionService,
    ) {
    }

    public function getOrCreate(User $user): BuyerVerification
    {
        $verification = BuyerVerification::query()
            ->where('user_id', $user->id)
            ->latest('id')
            ->first();

        if (!$verification || $verification->isCurrentlyValid() === false && strtolower((string) $verification->decision) === 'approved') {
            $verification = BuyerVerification::create([
                'user_id' => $user->id,
                'status' => BuyerVerificationStatus::CREATED,
                'idin_status' => 'pending',
                'ideal_status' => 'pending',
                'kyc_status' => 'pending',
                'decision' => null,
            ]);
        }

        BuyerProfile::query()->firstOrCreate(
            ['user_id' => $user->id],
            [
                'full_name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
            ]
        );

        return $verification->fresh(['profile', 'latestSignhostPhase', 'flags']);
    }

    public function saveProfile(User $user, array $payload): BuyerVerification
    {
        return DB::transaction(function () use ($user, $payload) {
            $verification = $this->getOrCreate($user);

            BuyerProfile::query()->updateOrCreate(
                ['user_id' => $user->id],
                $payload
            );

            $verification->status = BuyerVerificationStatus::PROFILE_COMPLETED;
            $verification->save();

            return $verification->fresh(['profile', 'flags', 'latestSignhostPhase']);
        });
    }

    public function startVerification(BuyerVerification $verification, string $providerStep = 'idin'): BuyerVerificationSignhostTransaction
    {
        $verification->loadMissing('profile', 'user');
        $existing = $verification->signhostTransactions()
            ->where('provider_step', $providerStep)
            ->latest('id')
            ->first();

        if ($existing && in_array($existing->status, ['pending', 'signing', 'started'], true)) {
            return $existing;
        }

        $payload = [
            'flow_type' => 'buyer_verification_v1',
            'phase_type' => 'verification',
            'provider_step' => $providerStep,
            'user_id' => $verification->user_id,
            'verification_id' => $verification->id,
            'email' => $verification->user?->email,
            'name' => $verification->profile?->full_name ?: $verification->user?->name,
        ];

        $result = $this->signhost->createVerificationPhaseTransaction(
            $verification->user,
            $payload,
            null,
            sprintf('buyer-verification-%s-%s', $verification->id, $providerStep)
        );

        $transaction = BuyerVerificationSignhostTransaction::create([
            'buyer_verification_id' => $verification->id,
            'user_id' => $verification->user_id,
            'phase_type' => 'verification',
            'provider_step' => $providerStep,
            'signhost_transaction_id' => $result['transaction_id'] ?? null,
            'status' => 'started',
            'redirect_url' => $result['redirect_url'] ?? null,
            'payload_json' => $payload,
            'provider_response_json' => $result['transaction'] ?? null,
        ]);

        $verification->latest_signhost_phase_id = $transaction->id;
        $verification->status = BuyerVerificationStatus::SIGNHOST_VERIFICATION_STARTED;
        if ($providerStep === 'idin') {
            $verification->idin_status = 'pending';
        }
        if ($providerStep === 'ideal') {
            $verification->ideal_status = 'pending';
        }
        $verification->save();

        return $transaction;
    }

    public function saveAnswers(BuyerVerification $verification, array $answers): BuyerVerification
    {
        return DB::transaction(function () use ($verification, $answers) {
            $questions = KycQuestion::query()
                ->with('options')
                ->whereIn('key', array_keys($answers))
                ->whereIn('audience', ['buyer', 'both'])
                ->get()
                ->keyBy('key');

            foreach ($answers as $key => $answerPayload) {
                $question = $questions->get($key);
                if (!$question) {
                    continue;
                }

                $rawValue = is_array($answerPayload) ? ($answerPayload['value'] ?? null) : $answerPayload;
                $normalized = is_string($rawValue) ? strtolower(trim($rawValue)) : $rawValue;
                $option = $question->options->firstWhere('value', $rawValue);

                BuyerVerificationKycAnswer::query()->updateOrCreate(
                    [
                        'buyer_verification_id' => $verification->id,
                        'kyc_question_id' => $question->id,
                    ],
                    [
                        'kyc_question_option_id' => $option?->id,
                        'question_key' => $question->key,
                        'answer_value' => is_scalar($rawValue) ? (string) $rawValue : json_encode($rawValue),
                        'normalized_value' => is_scalar($normalized) ? (string) $normalized : json_encode($normalized),
                        'answer_payload' => is_array($answerPayload) ? $answerPayload : ['value' => $rawValue],
                        'submitted_at' => now(),
                    ]
                );
            }

            $verification->kyc_status = 'completed';
            $verification->status = BuyerVerificationStatus::KYC_COMPLETED;
            $verification->save();

            return $verification->fresh(['kycAnswers.option', 'flags']);
        });
    }

    public function submit(BuyerVerification $verification): array
    {
        $verification->loadMissing(['profile', 'kycAnswers.option', 'flags']);

        $decision = $this->decisionService->evaluate($verification);
        $verification->kyc_status = 'completed';
        $verification->submitted_at = now();

        if ($decision['outcome'] === 'rejected') {
            $verification->status = BuyerVerificationStatus::REJECTED;
            $verification->save();

            return $decision;
        }

        if ($decision['outcome'] === 'manual_review') {
            $verification->status = BuyerVerificationStatus::MANUAL_REVIEW;
            $verification->manual_review_required = true;
            $verification->save();

            BuyerVerificationReview::query()->firstOrCreate(
                [
                    'buyer_verification_id' => $verification->id,
                    'status' => 'open',
                ],
                [
                    'opened_at' => now(),
                ]
            );

            return $decision;
        }

        $verification->status = BuyerVerificationStatus::APPROVED;
        $verification->approved_at = now();
        $verification->manual_review_required = false;
        $verification->save();

        return $decision;
    }

    public function handleSignhostStatus(BuyerVerificationSignhostTransaction $transaction, string $status, array $payload): BuyerVerification
    {
        return DB::transaction(function () use ($transaction, $status, $payload) {
            $transaction->status = $status;
            $transaction->webhook_last_payload = $payload;
            if (in_array($status, ['signed', 'completed'], true)) {
                $transaction->completed_at = now();
            }
            $transaction->save();

            $verification = $transaction->verification()->lockForUpdate()->firstOrFail();
            $profile = BuyerProfile::query()->firstWhere('user_id', $verification->user_id);

            if ($transaction->provider_step === 'idin') {
                if (in_array($status, ['signed', 'completed'], true)) {
                    $verification->idin_status = 'completed';
                    $verification->status = BuyerVerificationStatus::IDIN_COMPLETED;
                    if ($profile) {
                        $profile->verified_full_name = (string) (data_get($payload, 'Signer.Name') ?? data_get($payload, 'name') ?? $profile->verified_full_name);
                        $profile->identity_verified_at = now();
                        $profile->save();
                    }
                    $verification->save();
                    $this->startVerification($verification, 'ideal');
                } elseif (in_array($status, ['rejected', 'expired', 'cancelled'], true)) {
                    $verification->idin_status = $status;
                    $verification->status = BuyerVerificationStatus::MANUAL_REVIEW;
                    $verification->manual_review_required = true;
                    $verification->save();
                }

                return $verification->fresh(['profile', 'latestSignhostPhase', 'flags']);
            }

            if ($transaction->provider_step === 'ideal') {
                if (in_array($status, ['signed', 'completed'], true)) {
                    $verification->ideal_status = 'completed';
                    $verification->status = BuyerVerificationStatus::KYC_PENDING;
                    if ($profile) {
                        $profile->verified_iban = (string) (data_get($payload, 'Iban') ?? data_get($payload, 'iban') ?? $profile->verified_iban);
                        $profile->verified_bank_account_holder = (string) (data_get($payload, 'AccountHolder') ?? data_get($payload, 'account_holder') ?? $profile->verified_bank_account_holder);
                        $profile->bank_verified_at = now();
                        $profile->save();
                    }
                    $verification->save();
                } elseif (in_array($status, ['rejected', 'expired', 'cancelled'], true)) {
                    $verification->ideal_status = $status;
                    $verification->status = BuyerVerificationStatus::MANUAL_REVIEW;
                    $verification->manual_review_required = true;
                    $verification->save();
                }
            }

            return $verification->fresh(['profile', 'latestSignhostPhase', 'flags']);
        });
    }
}
