<?php

namespace App\Services;

/**
 * Validates Retell's post-call structured analysis (spec §16) before
 * anything downstream is allowed to touch a status, deal, bid, or
 * appointment. Retell's analysis is a claim, not a fact — an unrecognized
 * or internally-inconsistent outcome is refused rather than acted on.
 */
class RetellCallOutcomeValidator
{
    private const VALID_OUTCOMES = [
        // Seller call flow (spec §3)
        'interested', 'seller_onboarding_started', 'seller_onboarding_link_requested',
        'seller_onboarding_incomplete', 'callback_requested', 'information_requested',
        'yacht_details_requested', 'broker_contact_requested', 'needs_time',
        'not_interested', 'do_not_call', 'wrong_contact', 'wrong_number',
        'voicemail', 'no_answer', 'busy', 'ivr_failed', 'failed',
        // Buyer call flow (spec §4) — de-duplicated against the seller set above
        'viewing_requested', 'bid_support_requested', 'financing_question', 'contract_question',
    ];

    /**
     * @return array{valid: bool, errors: string[], outcome: ?string}
     */
    public function validate(array $analysis): array
    {
        $outcome = $analysis['outcome'] ?? null;
        $errors = [];

        if (! is_string($outcome) || ! in_array($outcome, self::VALID_OUTCOMES, true)) {
            $errors[] = 'unknown_outcome';
            $outcome = null;
        }

        // Boolean flags must agree with the outcome they imply — a payload
        // claiming both do_not_call=true and outcome=interested is
        // internally inconsistent and gets flagged rather than trusted.
        if (($analysis['do_not_call'] ?? false) && $outcome !== 'do_not_call') {
            $errors[] = 'do_not_call_flag_outcome_mismatch';
        }
        if (($analysis['callback_requested'] ?? false) && $outcome !== 'callback_requested') {
            $errors[] = 'callback_requested_flag_outcome_mismatch';
        }
        if (($analysis['viewing_requested'] ?? false) && $outcome !== 'viewing_requested') {
            $errors[] = 'viewing_requested_flag_outcome_mismatch';
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'outcome' => $outcome,
        ];
    }
}
