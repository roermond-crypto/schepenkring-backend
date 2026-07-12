<?php

namespace App\Services;

use App\Models\BoatIntake;
use App\Models\CampaignTarget;
use App\Models\Contact;
use App\Models\Lead;
use App\Models\User;

/**
 * Scores a CampaignTarget so CampaignService can decide who's worth calling
 * (spec's "Do not randomly call all leads" priority list). Point values are
 * a reasonable first pass, not calibrated against real conversion data —
 * expect to tune these once real campaign results exist.
 */
class LeadScoringService
{
    private const CALLBACK_REQUESTED = 40;
    private const EMAIL_CLICKED = 20;
    private const EMAIL_OPENED_MULTIPLE = 15;
    private const EMAIL_OPENED_ONCE = 5;
    private const SELLER_ONBOARDING_INCOMPLETE = 30;
    private const VIEWING_OR_BID_SIGNAL = 25;
    private const INFORMATION_REQUESTED = 20;

    public function score(CampaignTarget $target): int
    {
        $target->loadMissing('emailEvents');

        if ($target->isSuppressed()) {
            return 0;
        }

        $score = 0;
        $score += $this->emailEngagementScore($target);
        $score += $this->leadSignalScore($target);
        $score += $this->onboardingIncompleteScore($target);

        return min($score, 100);
    }

    private function emailEngagementScore(CampaignTarget $target): int
    {
        $events = $target->emailEvents;
        if ($events->isEmpty()) {
            return 0;
        }

        $score = 0;
        $totalOpens = (int) $events->sum('open_count');
        $totalClicks = (int) $events->sum('click_count');

        if ($totalClicks > 0) {
            $score += self::EMAIL_CLICKED;
        }
        if ($totalOpens >= 2) {
            $score += self::EMAIL_OPENED_MULTIPLE;
        } elseif ($totalOpens === 1) {
            $score += self::EMAIL_OPENED_ONCE;
        }

        return $score;
    }

    private function leadSignalScore(CampaignTarget $target): int
    {
        $lead = $this->resolveLead($target);
        if (! $lead) {
            return 0;
        }

        $status = strtolower((string) $lead->status);
        $score = 0;

        // Lead.status is a free-form string set by whichever flow created
        // the lead — matched defensively against the values those flows
        // are known to use rather than a hard enum.
        if (in_array($status, ['callback_requested', 'callback'], true)) {
            $score += self::CALLBACK_REQUESTED;
        }
        if (in_array($status, ['information_requested', 'question'], true)) {
            $score += self::INFORMATION_REQUESTED;
        }
        if (in_array($status, ['viewing_requested', 'bid_received', 'bid_unanswered'], true)) {
            $score += self::VIEWING_OR_BID_SIGNAL;
        }

        return $score;
    }

    private function onboardingIncompleteScore(CampaignTarget $target): int
    {
        $user = $this->resolveUser($target);
        if (! $user) {
            return 0;
        }

        $hasIncompleteIntake = BoatIntake::where('seller_user_id', $user->id)
            ->whereNotIn('status', ['ready_for_admin_review', 'accepted'])
            ->exists();

        return $hasIncompleteIntake ? self::SELLER_ONBOARDING_INCOMPLETE : 0;
    }

    private function resolveLead(CampaignTarget $target): ?Lead
    {
        return match ($target->target_type) {
            'lead' => Lead::find($target->target_id),
            default => null,
        };
    }

    private function resolveUser(CampaignTarget $target): ?User
    {
        return match ($target->target_type) {
            'user' => User::find($target->target_id),
            'lead' => Lead::find($target->target_id)?->client,
            'contact' => Contact::find($target->target_id)?->user,
            default => null,
        };
    }
}
