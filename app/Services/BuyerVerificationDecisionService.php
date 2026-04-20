<?php

namespace App\Services;

use App\Models\BuyerVerification;
use App\Models\BuyerVerificationFlag;
use Carbon\CarbonImmutable;

class BuyerVerificationDecisionService
{
    public function __construct(
        private readonly KycRuleEngine $ruleEngine,
    ) {
    }

    public function evaluate(BuyerVerification $verification): array
    {
        $verification->loadMissing(['profile', 'kycAnswers.option', 'kycAnswers.question']);

        $result = $this->ruleEngine->evaluateAnswers(
            $verification->kycAnswers()->with(['option', 'question'])->get(),
            'buyer'
        );
        $profile = $verification->profile;
        $flags = $result['flags'];
        $score = (int) $result['score'];
        $reasonCodes = $result['reason_codes'];
        $override = $result['outcome_override'];

        $profileName = $this->normalize((string) ($profile?->full_name ?? ''));
        $verifiedName = $this->normalize((string) ($profile?->verified_full_name ?? ''));
        if ($profileName !== '' && $verifiedName !== '' && $profileName !== $verifiedName) {
            $score += 50;
            $flags[] = $this->buildFlag(
                'identity_mismatch',
                'critical',
                'The verified identity name does not match the buyer profile name.',
                true
            );
            $reasonCodes[] = 'identity_mismatch';
        }

        $profileIban = $this->normalizeIban((string) ($profile?->iban ?? ''));
        $verifiedIban = $this->normalizeIban((string) ($profile?->verified_iban ?? ''));
        if ($profileIban !== '' && $verifiedIban !== '' && $profileIban !== $verifiedIban) {
            $score += 40;
            $flags[] = $this->buildFlag(
                'iban_mismatch_or_suspicious_bank_signal',
                'critical',
                'The verified IBAN does not match the IBAN submitted in the buyer profile.',
                true
            );
            $reasonCodes[] = 'iban_mismatch_or_suspicious_bank_signal';
        }

        $outcome = $override ? strtolower((string) $override) : null;
        if (!$outcome) {
            if ($score >= 50) {
                $outcome = 'rejected';
            } elseif ($score >= 20) {
                $outcome = 'manual_review';
            } else {
                $outcome = 'approved';
            }
        }

        $verifiedAt = null;
        $expiresAt = null;
        if ($outcome === 'approved') {
            $verifiedAt = CarbonImmutable::now();
            $expiresAt = $verifiedAt->addDays(90);
        }

        BuyerVerificationFlag::query()->where('buyer_verification_id', $verification->id)->delete();
        foreach ($flags as $flag) {
            BuyerVerificationFlag::create([
                'buyer_verification_id' => $verification->id,
                'flag_code' => $flag['flag_code'],
                'severity' => $flag['severity'],
                'message' => $flag['message'],
                'metadata_json' => $flag['metadata_json'] ?? null,
                'is_blocking' => (bool) ($flag['is_blocking'] ?? false),
            ]);
        }

        $manualReview = $outcome === 'manual_review';
        $verification->risk_score = $score;
        $verification->manual_review_required = $manualReview;
        $verification->decision = $outcome;
        $verification->decision_reason = empty($reasonCodes) ? null : implode(', ', array_unique($reasonCodes));
        $verification->reason_codes_json = array_values(array_unique($reasonCodes));
        $verification->verified_at = $verifiedAt;
        $verification->expires_at = $expiresAt;
        $verification->save();

        return [
            'outcome' => $outcome,
            'risk_score' => $score,
            'flags' => $flags,
            'reason_codes' => array_values(array_unique($reasonCodes)),
            'review_required' => $manualReview,
            'verified_at' => $verifiedAt?->toIso8601String(),
            'expires_at' => $expiresAt?->toIso8601String(),
        ];
    }

    private function buildFlag(string $flagCode, string $severity, string $message, bool $blocking): array
    {
        return [
            'flag_code' => $flagCode,
            'severity' => $severity,
            'message' => $message,
            'metadata_json' => null,
            'is_blocking' => $blocking,
        ];
    }

    private function normalize(string $value): string
    {
        return strtolower(trim(preg_replace('/\s+/', ' ', $value) ?: ''));
    }

    private function normalizeIban(string $value): string
    {
        return strtoupper(preg_replace('/[^A-Z0-9]/i', '', $value) ?: '');
    }
}
