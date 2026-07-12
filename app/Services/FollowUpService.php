<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\CampaignTarget;
use App\Models\Contact;
use App\Models\FollowUp;
use App\Models\Lead;
use Illuminate\Support\Carbon;

/**
 * Implements the outcome -> action rules table from the Retell spec (§7):
 * every call/campaign outcome maps to exactly one of these actions. Laravel
 * owns this logic — Retell only ever reports an outcome, it never decides
 * what happens next.
 *
 * $context keys used across the action handlers below:
 *   campaign_target_id, callback_at, ai_summary, assigned_employee_id,
 *   related_yacht_id, related_deal_id, related_chat_thread_id,
 *   retry_delay_hours, cooldown_days, suppression_reason
 */
class FollowUpService
{
    public function __construct(private ActivityFeedService $activityFeed)
    {
    }

    public function applyOutcome(string $subjectType, int|string $subjectId, string $outcome, array $context = []): ?FollowUp
    {
        $followUp = match ($outcome) {
            'no_answer', 'busy' => $this->retryLater($subjectType, $subjectId, $context),
            'callback_requested' => $this->useCallbackTime($subjectType, $subjectId, $context),
            'information_requested' => $this->sendAiEmailAndFollowUp($subjectType, $subjectId, $context),
            'interested', 'seller_onboarding_link_requested' => $this->sendOnboardingLink($subjectType, $subjectId, $context),
            'seller_onboarding_incomplete' => $this->createUrgentFollowUp($subjectType, $subjectId, $context),
            'viewing_requested' => $this->createAppointmentWorkflow($subjectType, $subjectId, $context),
            'bid_support_requested' => $this->openBidTask($subjectType, $subjectId, $context),
            'broker_contact_requested' => $this->warmTransferOrCallback($subjectType, $subjectId, $context),
            'contract_question' => $this->routeToContractSupport($subjectType, $subjectId, $context),
            'not_interested' => $this->stopOrCooldown($subjectType, $subjectId, $context),
            'do_not_call' => $this->suppressPermanently($subjectType, $subjectId, $context),
            'wrong_number', 'wrong_contact' => $this->markInvalid($subjectType, $subjectId, $context),
            default => null,
        };

        $this->applyCampaignTargetStatus($outcome, $context);

        return $followUp;
    }

    private function retryLater(string $subjectType, $subjectId, array $context): FollowUp
    {
        $delayHours = (int) ($context['retry_delay_hours'] ?? 24);

        return $this->create($subjectType, $subjectId, 'retry_call', $context, [
            'due_at' => now()->addHours($delayHours),
            'last_outcome' => 'no_answer',
            'retry_count' => (int) ($context['retry_count'] ?? 0) + 1,
        ]);
    }

    private function useCallbackTime(string $subjectType, $subjectId, array $context): FollowUp
    {
        $callbackAt = $context['callback_at'] ?? null;

        return $this->create($subjectType, $subjectId, 'retry_call', $context, [
            'due_at' => $callbackAt ? Carbon::parse($callbackAt) : now()->addHours(24),
            'last_outcome' => 'callback_requested',
        ]);
    }

    private function sendAiEmailAndFollowUp(string $subjectType, $subjectId, array $context): FollowUp
    {
        // The actual AI-drafted email send is CampaignService's job (reuses
        // the existing EmailTemplate system) — this just records that one
        // is owed and tracks it through to completion.
        return $this->create($subjectType, $subjectId, 'send_ai_email', $context, [
            'due_at' => now(),
            'last_outcome' => 'information_requested',
        ]);
    }

    private function sendOnboardingLink(string $subjectType, $subjectId, array $context): FollowUp
    {
        return $this->create($subjectType, $subjectId, 'send_onboarding_link', $context, [
            'due_at' => now(),
            'last_outcome' => 'interested',
        ]);
    }

    private function createUrgentFollowUp(string $subjectType, $subjectId, array $context): FollowUp
    {
        return $this->create($subjectType, $subjectId, 'create_urgent_followup', $context, [
            'due_at' => now()->addHours(4),
            'last_outcome' => 'seller_onboarding_incomplete',
        ]);
    }

    private function createAppointmentWorkflow(string $subjectType, $subjectId, array $context): FollowUp
    {
        return $this->create($subjectType, $subjectId, 'create_appointment', $context, [
            'due_at' => now(),
            'last_outcome' => 'viewing_requested',
        ]);
    }

    private function openBidTask(string $subjectType, $subjectId, array $context): FollowUp
    {
        return $this->create($subjectType, $subjectId, 'open_bid_task', $context, [
            'due_at' => now(),
            'last_outcome' => 'bid_support_requested',
        ]);
    }

    private function warmTransferOrCallback(string $subjectType, $subjectId, array $context): FollowUp
    {
        return $this->create($subjectType, $subjectId, 'warm_transfer', $context, [
            'due_at' => now(),
            'last_outcome' => 'broker_contact_requested',
        ]);
    }

    private function routeToContractSupport(string $subjectType, $subjectId, array $context): FollowUp
    {
        return $this->create($subjectType, $subjectId, 'route_to_contract_support', $context, [
            'due_at' => now(),
            'last_outcome' => 'contract_question',
        ]);
    }

    private function stopOrCooldown(string $subjectType, $subjectId, array $context): ?FollowUp
    {
        $cooldownDays = $context['cooldown_days'] ?? null;
        if (! $cooldownDays) {
            // Fully stopped — no further automatic contact, but not a
            // permanent suppression (do_not_call is the permanent one).
            return null;
        }

        return $this->create($subjectType, $subjectId, 'stop', $context, [
            'due_at' => now()->addDays((int) $cooldownDays),
            'last_outcome' => 'not_interested',
        ]);
    }

    private function suppressPermanently(string $subjectType, $subjectId, array $context): FollowUp
    {
        $this->markContactDoNotContact($subjectType, $subjectId);

        // A separate, security-flavored AuditLog entry (not just the
        // generic Activity Feed followup.created event created below) —
        // permanent suppression is exactly the kind of event spec §19 calls
        // out by name ("Suppression applied") and callers reviewing the
        // compliance trail shouldn't have to infer it from a follow-up row.
        // AuditLog.target_id is a legacy unsignedBigInteger column — only
        // set it when the subject actually has a numeric id (call_session
        // uses UUIDs and goes through meta instead, matching the same
        // pattern used in RetellToolHelpers::safe()).
        AuditLog::create([
            'action' => 'suppression.applied',
            'category' => 'voice_ai',
            'risk_level' => 'low',
            'result' => 'success',
            'target_type' => is_numeric($subjectId) ? $subjectType : null,
            'target_id' => is_numeric($subjectId) ? $subjectId : null,
            'meta' => [
                'reason' => $context['suppression_reason'] ?? 'requested_do_not_call',
                'subject_type' => $subjectType,
                'subject_id' => $subjectId,
            ],
        ]);

        return $this->create($subjectType, $subjectId, 'suppress', $context, [
            'status' => 'done',
            'last_outcome' => 'do_not_call',
            'suppression_reason' => $context['suppression_reason'] ?? 'requested_do_not_call',
        ]);
    }

    private function markInvalid(string $subjectType, $subjectId, array $context): FollowUp
    {
        return $this->create($subjectType, $subjectId, 'mark_invalid', $context, [
            'status' => 'done',
            'last_outcome' => 'wrong_contact',
            'suppression_reason' => $context['suppression_reason'] ?? 'wrong_number_or_contact',
        ]);
    }

    private function create(string $subjectType, $subjectId, string $nextAction, array $context, array $overrides = []): FollowUp
    {
        $followUp = FollowUp::create(array_merge([
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'next_action' => $nextAction,
            'status' => 'open',
            'assigned_employee_id' => $context['assigned_employee_id'] ?? null,
            'ai_summary' => $context['ai_summary'] ?? null,
            'related_yacht_id' => $context['related_yacht_id'] ?? null,
            'related_deal_id' => $context['related_deal_id'] ?? null,
            'related_chat_thread_id' => $context['related_chat_thread_id'] ?? null,
        ], $overrides));

        $this->activityFeed->record($subjectType, $subjectId, 'followup.created', "Follow-up created: {$nextAction}", [
            'follow_up_id' => $followUp->id,
            'next_action' => $nextAction,
        ]);

        return $followUp;
    }

    private function applyCampaignTargetStatus(string $outcome, array $context): void
    {
        $targetId = $context['campaign_target_id'] ?? null;
        if (! $targetId) {
            return;
        }

        $target = CampaignTarget::find($targetId);
        if (! $target) {
            return;
        }

        $target->status = match ($outcome) {
            'do_not_call', 'wrong_number', 'wrong_contact' => 'suppressed',
            'not_interested' => $context['cooldown_days'] ?? null ? $target->status : 'completed',
            'interested', 'seller_onboarding_link_requested', 'viewing_requested', 'bid_support_requested' => 'completed',
            default => $target->status,
        };
        $target->last_action_at = now();
        $target->save();
    }

    private function markContactDoNotContact(string $subjectType, $subjectId): void
    {
        $contact = match ($subjectType) {
            'contact' => Contact::find($subjectId),
            'lead' => $this->contactFromLead($subjectId),
            default => null,
        };

        $contact?->update(['do_not_contact' => true]);
    }

    private function contactFromLead($leadId): ?Contact
    {
        $lead = Lead::find($leadId);
        if (! $lead || ! $lead->email) {
            return null;
        }

        return Contact::where('email', $lead->email)->first();
    }
}
