<?php

namespace App\Services;

use App\Models\KycQuestion;
use App\Models\SellerOnboarding;
use App\Models\SellerOnboardingContract;
use App\Models\SellerOnboardingKycAnswer;
use App\Models\SellerOnboardingPayment;
use App\Models\SellerOnboardingReview;
use App\Models\SellerOnboardingSignhostTransaction;
use App\Models\SellerProfile;
use App\Models\User;
use App\Support\SellerOnboardingStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class SellerOnboardingOrchestrator
{
    public function __construct(
        private readonly MollieService $mollie,
        private readonly SignhostService $signhost,
        private readonly SellerOnboardingContractPdfService $pdfService,
        private readonly SellerOnboardingDecisionService $decisionService,
    ) {
    }

    public function getOrCreate(User $user): SellerOnboarding
    {
        $onboarding = SellerOnboarding::query()
            ->where('user_id', $user->id)
            ->latest('id')
            ->first();

        if (
            !$onboarding ||
            (!$onboarding->isCurrentlyValid() && strtolower((string) $onboarding->decision) === 'approved')
        ) {
            $onboarding = SellerOnboarding::create([
                'user_id' => $user->id,
                'status' => SellerOnboardingStatus::CREATED,
                'payment_status' => 'pending',
                'idin_status' => 'pending',
                'ideal_status' => 'pending',
                'kyc_status' => 'pending',
                'contract_status' => 'pending',
                'decision' => null,
                'can_publish_boat' => false,
            ]);
        }

        SellerProfile::query()->firstOrCreate(
            ['user_id' => $user->id],
            [
                'full_name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
            ]
        );

        return $onboarding->fresh(['profile', 'latestContract', 'latestSignhostPhase', 'flags']);
    }

    public function saveProfile(User $user, array $payload): SellerOnboarding
    {
        return DB::transaction(function () use ($user, $payload) {
            $onboarding = $this->getOrCreate($user);

            SellerProfile::query()->updateOrCreate(
                ['user_id' => $user->id],
                $payload
            );

            $onboarding->status = SellerOnboardingStatus::PROFILE_COMPLETED;
            $onboarding->save();

            return $onboarding->fresh(['profile', 'flags', 'latestContract', 'latestSignhostPhase']);
        });
    }

    public function createPaymentSession(SellerOnboarding $onboarding, array $validated): SellerOnboardingPayment
    {
        $amount = number_format((float) config('services.seller_onboarding.payment_amount', 395.00), 2, '.', '');
        $idempotencyKey = (string) ($validated['idempotency_key'] ?? Str::uuid());
        $payload = [
            'amount' => [
                'currency' => 'EUR',
                'value' => $amount,
            ],
            'description' => 'Seller onboarding ' . $onboarding->id,
            'redirectUrl' => $validated['redirect_url'],
            'webhookUrl' => config('services.mollie.webhook_url') ?: url('/api/webhooks/mollie'),
            'metadata' => [
                'seller_onboarding_id' => $onboarding->id,
                'user_id' => $onboarding->user_id,
                'payment_type' => 'seller_onboarding',
            ],
        ];

        $response = $this->mollie->createPayment($payload, $idempotencyKey);

        $payment = SellerOnboardingPayment::create([
            'seller_onboarding_id' => $onboarding->id,
            'user_id' => $onboarding->user_id,
            'type' => 'seller_onboarding',
            'mollie_payment_id' => $response['id'] ?? null,
            'idempotency_key' => $idempotencyKey,
            'amount_currency' => 'EUR',
            'amount_value' => $amount,
            'status' => $response['status'] ?? 'open',
            'checkout_url' => data_get($response, '_links.checkout.href'),
            'webhook_events_count' => 0,
            'metadata_json' => $payload['metadata'],
        ]);

        $onboarding->payment_status = $payment->status;
        $onboarding->status = SellerOnboardingStatus::PAYMENT_PENDING;
        $onboarding->save();

        return $payment;
    }

    public function handlePaymentStatus(SellerOnboardingPayment $payment, string $status): SellerOnboarding
    {
        return DB::transaction(function () use ($payment, $status) {
            $payment->status = $status;
            $payment->webhook_events_count = (int) $payment->webhook_events_count + 1;
            if ($status === 'paid') {
                $payment->paid_at = now();
            }
            $payment->save();

            $onboarding = $payment->onboarding()->lockForUpdate()->firstOrFail();
            $onboarding->payment_status = $status;
            if ($status === 'paid') {
                $onboarding->status = SellerOnboardingStatus::PAYMENT_COMPLETED;
            }
            $onboarding->save();

            return $onboarding->fresh(['profile', 'latestContract', 'latestSignhostPhase', 'flags']);
        });
    }

    public function generateContract(SellerOnboarding $onboarding): SellerOnboardingContract
    {
        $onboarding->loadMissing('profile', 'user');
        $profile = $onboarding->profile;
        if (!$profile) {
            throw new \RuntimeException('Seller profile is required before contract generation.');
        }

        $existing = $onboarding->contracts()->latest('id')->first();
        if ($existing && $existing->contract_pdf_path) {
            if (!$onboarding->latest_contract_id) {
                $onboarding->latest_contract_id = $existing->id;
                $onboarding->status = SellerOnboardingStatus::CONTRACT_GENERATED;
                $onboarding->contract_status = 'generated';
                $onboarding->save();
            }

            return $existing;
        }

        $pdf = $this->pdfService->generate($onboarding, $profile);
        $contract = SellerOnboardingContract::create([
            'seller_onboarding_id' => $onboarding->id,
            'user_id' => $onboarding->user_id,
            'contract_uid' => (string) Str::uuid(),
            'contract_type' => $profile->seller_type === 'business' ? 'business_seller_agreement' : 'private_seller_agreement',
            'template_version' => 'v1',
            'contract_pdf_path' => $pdf['path'],
            'contract_sha256' => $pdf['sha256'],
            'status' => 'generated',
            'contract_payload' => [
                'seller_type' => $profile->seller_type,
                'full_name' => $profile->full_name,
                'company_name' => $profile->company_name,
                'iban' => $profile->iban,
            ],
            'generated_at' => now(),
        ]);

        $onboarding->latest_contract_id = $contract->id;
        $onboarding->status = SellerOnboardingStatus::CONTRACT_GENERATED;
        $onboarding->contract_status = 'generated';
        $onboarding->save();

        return $contract;
    }

    public function startVerification(SellerOnboarding $onboarding, string $providerStep = 'idin'): SellerOnboardingSignhostTransaction
    {
        $onboarding->loadMissing('profile', 'user');
        $existing = $onboarding->signhostTransactions()
            ->where('provider_step', $providerStep)
            ->latest('id')
            ->first();

        if ($existing && in_array($existing->status, ['pending', 'signing', 'started'], true)) {
            return $existing;
        }

        $payload = [
            'flow_type' => 'seller_onboarding_v1',
            'phase_type' => 'verification',
            'provider_step' => $providerStep,
            'user_id' => $onboarding->user_id,
            'onboarding_id' => $onboarding->id,
            'email' => $onboarding->user?->email,
            'name' => $onboarding->profile?->full_name ?: $onboarding->user?->name,
        ];

        $result = $this->signhost->createVerificationPhaseTransaction(
            $onboarding->user,
            $payload,
            null,
            sprintf('seller-onboarding-%s-%s', $onboarding->id, $providerStep)
        );

        $transaction = SellerOnboardingSignhostTransaction::create([
            'seller_onboarding_id' => $onboarding->id,
            'user_id' => $onboarding->user_id,
            'phase_type' => 'verification',
            'provider_step' => $providerStep,
            'signhost_transaction_id' => $result['transaction_id'] ?? null,
            'status' => 'started',
            'redirect_url' => $result['redirect_url'] ?? null,
            'payload_json' => $payload,
            'provider_response_json' => $result['transaction'] ?? null,
        ]);

        $onboarding->latest_signhost_phase_id = $transaction->id;
        $onboarding->status = SellerOnboardingStatus::SIGNHOST_VERIFICATION_STARTED;
        if ($providerStep === 'idin') {
            $onboarding->idin_status = 'pending';
        }
        if ($providerStep === 'ideal') {
            $onboarding->ideal_status = 'pending';
        }
        $onboarding->save();

        return $transaction;
    }

    public function startContractSigning(SellerOnboarding $onboarding): SellerOnboardingSignhostTransaction
    {
        $onboarding->loadMissing(['profile', 'user', 'latestContract']);
        $contract = $onboarding->latestContract ?: $this->generateContract($onboarding);

        $existing = $onboarding->signhostTransactions()
            ->where('provider_step', 'contract_signing')
            ->latest('id')
            ->first();

        if ($existing && in_array($existing->status, ['pending', 'signing', 'started'], true)) {
            return $existing;
        }

        $path = $contract->contract_pdf_path ? Storage::disk('public')->path($contract->contract_pdf_path) : null;
        $payload = [
            'flow_type' => 'seller_onboarding_v1',
            'phase_type' => 'contract_signing',
            'provider_step' => 'contract_signing',
            'user_id' => $onboarding->user_id,
            'onboarding_id' => $onboarding->id,
            'contract_id' => $contract->id,
        ];

        $result = $this->signhost->createVerificationPhaseTransaction(
            $onboarding->user,
            $payload,
            $path,
            sprintf('seller-onboarding-%s-contract', $onboarding->id)
        );

        $transaction = SellerOnboardingSignhostTransaction::create([
            'seller_onboarding_id' => $onboarding->id,
            'user_id' => $onboarding->user_id,
            'seller_onboarding_contract_id' => $contract->id,
            'phase_type' => 'contract_signing',
            'provider_step' => 'contract_signing',
            'signhost_transaction_id' => $result['transaction_id'] ?? null,
            'status' => 'started',
            'redirect_url' => $result['redirect_url'] ?? null,
            'payload_json' => $payload,
            'provider_response_json' => $result['transaction'] ?? null,
        ]);

        $contract->signhost_transaction_id = $transaction->signhost_transaction_id;
        $contract->sign_url = $transaction->redirect_url;
        $contract->status = 'signing';
        $contract->save();

        $onboarding->latest_signhost_phase_id = $transaction->id;
        $onboarding->status = SellerOnboardingStatus::CONTRACT_SIGNING_PENDING;
        $onboarding->contract_status = 'signing';
        $onboarding->can_publish_boat = false;
        $onboarding->save();

        return $transaction;
    }

    public function saveAnswers(SellerOnboarding $onboarding, array $answers): SellerOnboarding
    {
        return DB::transaction(function () use ($onboarding, $answers) {
            $questions = KycQuestion::query()
                ->with('options')
                ->whereIn('key', array_keys($answers))
                ->whereIn('audience', ['seller', 'both'])
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

                SellerOnboardingKycAnswer::query()->updateOrCreate(
                    [
                        'seller_onboarding_id' => $onboarding->id,
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

            $onboarding->kyc_status = 'completed';
            $onboarding->status = SellerOnboardingStatus::KYC_COMPLETED;
            $onboarding->save();

            return $onboarding->fresh(['kycAnswers.option', 'flags']);
        });
    }

    public function submit(SellerOnboarding $onboarding): array
    {
        $onboarding->loadMissing(['profile', 'kycAnswers.option', 'flags', 'latestContract']);

        if ($onboarding->payment_status !== 'paid') {
            throw new \RuntimeException('Payment must be completed before seller approval can be evaluated.');
        }

        if (!$onboarding->latestContract) {
            $this->generateContract($onboarding);
            $onboarding->refresh();
            $onboarding->loadMissing(['profile', 'kycAnswers.option', 'flags', 'latestContract']);
        }

        $decision = $this->decisionService->evaluate($onboarding);
        $onboarding->kyc_status = 'completed';
        $onboarding->submitted_at = now();

        if ($decision['outcome'] === 'rejected') {
            $onboarding->status = SellerOnboardingStatus::REJECTED;
            $onboarding->can_publish_boat = false;
            $onboarding->save();

            return $decision;
        }

        if ($decision['outcome'] === 'manual_review') {
            $onboarding->status = SellerOnboardingStatus::MANUAL_REVIEW;
            $onboarding->manual_review_required = true;
            $onboarding->can_publish_boat = false;
            $onboarding->save();

            SellerOnboardingReview::query()->firstOrCreate(
                [
                    'seller_onboarding_id' => $onboarding->id,
                    'status' => 'open',
                ],
                [
                    'opened_at' => now(),
                ]
            );

            return $decision;
        }

        $onboarding->status = SellerOnboardingStatus::CONTRACT_SIGNING_PENDING;
        $onboarding->approved_at = null;
        $onboarding->manual_review_required = false;
        $onboarding->can_publish_boat = false;
        $onboarding->save();

        $this->startContractSigning($onboarding);

        return $decision;
    }

    public function handleSignhostStatus(SellerOnboardingSignhostTransaction $transaction, string $status, array $payload): SellerOnboarding
    {
        return DB::transaction(function () use ($transaction, $status, $payload) {
            $transaction->status = $status;
            $transaction->webhook_last_payload = $payload;
            if (in_array($status, ['signed', 'completed'], true)) {
                $transaction->completed_at = now();
            }
            $transaction->save();

            $onboarding = $transaction->onboarding()->lockForUpdate()->firstOrFail();
            $profile = SellerProfile::query()->firstWhere('user_id', $onboarding->user_id);

            if ($transaction->provider_step === 'idin') {
                if (in_array($status, ['signed', 'completed'], true)) {
                    $onboarding->idin_status = 'completed';
                    $onboarding->status = SellerOnboardingStatus::IDIN_COMPLETED;
                    if ($profile) {
                        $profile->verified_full_name = (string) (data_get($payload, 'Signer.Name') ?? data_get($payload, 'name') ?? $profile->verified_full_name);
                        $profile->identity_verified_at = now();
                        $profile->save();
                    }
                    $onboarding->save();
                    $this->startVerification($onboarding, 'ideal');
                } elseif (in_array($status, ['rejected', 'expired', 'cancelled'], true)) {
                    $onboarding->idin_status = $status;
                    $onboarding->status = SellerOnboardingStatus::MANUAL_REVIEW;
                    $onboarding->manual_review_required = true;
                    $onboarding->save();
                }

                return $onboarding->fresh(['profile', 'latestContract', 'latestSignhostPhase', 'flags']);
            }

            if ($transaction->provider_step === 'ideal') {
                if (in_array($status, ['signed', 'completed'], true)) {
                    $onboarding->ideal_status = 'completed';
                    $onboarding->status = SellerOnboardingStatus::KYC_PENDING;
                    if ($profile) {
                        $profile->verified_iban = (string) (data_get($payload, 'Iban') ?? data_get($payload, 'iban') ?? $profile->verified_iban);
                        $profile->verified_bank_account_holder = (string) (data_get($payload, 'AccountHolder') ?? data_get($payload, 'account_holder') ?? $profile->verified_bank_account_holder);
                        $profile->bank_verified_at = now();
                        $profile->save();
                    }
                    $onboarding->save();
                } elseif (in_array($status, ['rejected', 'expired', 'cancelled'], true)) {
                    $onboarding->ideal_status = $status;
                    $onboarding->status = SellerOnboardingStatus::MANUAL_REVIEW;
                    $onboarding->manual_review_required = true;
                    $onboarding->save();
                }

                return $onboarding->fresh(['profile', 'latestContract', 'latestSignhostPhase', 'flags']);
            }

            if ($transaction->provider_step === 'contract_signing') {
                $contract = $transaction->contract ?: $onboarding->latestContract;
                if ($contract) {
                    $contract->status = $status;
                    if (in_array($status, ['signed', 'completed'], true)) {
                        $contract->signed_at = now();
                        $signed = $transaction->signhost_transaction_id
                            ? $this->signhost->downloadSignedFile($transaction->signhost_transaction_id)
                            : null;
                        if ($signed) {
                            $path = 'contracts/seller_onboarding_' . $onboarding->id . '_signed.pdf';
                            Storage::disk('public')->put($path, $signed);
                            $contract->signed_document_path = $path;
                        }
                    }
                    $contract->save();
                }

                if (in_array($status, ['signed', 'completed'], true)) {
                    $onboarding->contract_status = 'signed';
                    $onboarding->status = SellerOnboardingStatus::APPROVED;
                    $onboarding->decision = 'approved';
                    $onboarding->approved_at = now();
                    $onboarding->manual_review_required = false;
                    $onboarding->can_publish_boat = true;
                } elseif (in_array($status, ['rejected', 'expired', 'cancelled'], true)) {
                    $onboarding->contract_status = $status;
                    $onboarding->can_publish_boat = false;
                }
                $onboarding->save();
            }

            return $onboarding->fresh(['profile', 'latestContract', 'latestSignhostPhase', 'flags']);
        });
    }
}
