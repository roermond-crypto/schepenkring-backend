<?php

namespace App\Services;

use App\Mail\TemplatedMail;
use App\Models\Campaign;
use App\Models\CampaignTarget;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Lead;
use App\Models\Location;
use App\Models\Message;
use App\Models\User;
use App\Models\Yacht;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Orchestrates the email-first, activity-based funnel from spec §2:
 *   email sent -> open/click tracked -> lead score calculated ->
 *   Retell calls the highest-priority leads -> outcome stored ->
 *   FollowUpService decides what happens next.
 * Entry point is processDueTargets(), called by the campaigns:process
 * scheduled command.
 */
class CampaignService
{
    // Give a recipient time to open/click before scoring and (possibly)
    // calling them — scoring immediately after sending would always see
    // zero engagement.
    private const EMAIL_ENGAGEMENT_WAIT_HOURS = 4;

    public function __construct(
        private EmailTemplateResolver $templates,
        private EmailTrackingService $tracking,
        private LeadScoringService $scoring,
        private ActivityFeedService $activityFeed,
        private ChatContactService $contactService,
        private ChatConversationService $chatService,
        private PhoneCallService $phoneCallService,
    ) {
    }

    public function processDueTargets(): void
    {
        foreach (Campaign::where('status', 'active')->get() as $campaign) {
            if ($campaign->isOverSpendCap()) {
                continue;
            }

            $this->sendPendingEmails($campaign);
            $this->scoreEngagedTargets($campaign);
            $this->callPrioritizedTargets($campaign);
        }
    }

    private function sendPendingEmails(Campaign $campaign): void
    {
        if (! $campaign->email_template_key) {
            return;
        }

        $targets = $campaign->targets()->where('status', 'pending')->limit(100)->get();

        foreach ($targets as $target) {
            try {
                $this->sendCampaignEmail($target, $campaign);
            } catch (\Throwable $e) {
                Log::error('Campaign email send failed', [
                    'campaign_id' => $campaign->id,
                    'campaign_target_id' => $target->id,
                    'error' => $e->getMessage(),
                ]);
                $target->update(['status' => 'failed', 'metadata' => array_merge($target->metadata ?? [], ['error' => $e->getMessage()])]);
            }
        }
    }

    public function sendCampaignEmail(CampaignTarget $target, Campaign $campaign): void
    {
        $info = $this->resolveTargetContactInfo($target);
        if (! $info['email']) {
            $target->update(['status' => 'failed', 'metadata' => array_merge($target->metadata ?? [], ['error' => 'missing_email'])]);

            return;
        }

        $rendered = $this->templates->resolveAndRender(
            $campaign->email_template_key,
            $info['location_id'],
            $info['language'] ?? 'nl',
            $this->buildDynamicVariables($target, $campaign, $info),
        );

        if ($rendered === null) {
            $target->update(['status' => 'failed', 'metadata' => array_merge($target->metadata ?? [], ['error' => 'no_email_template'])]);

            return;
        }

        // Resolve/reuse the same conversation a later call to this target
        // would use, so send/open/click events land in the one Chat Hub
        // thread alongside the call (spec §10).
        $conversation = $this->resolveConversationForTarget($info, 'email');

        $event = $this->tracking->createEvent($target, $campaign->email_template_key, $info['email']);
        $event->update(['conversation_id' => $conversation?->id]);
        $rendered['html'] = $this->tracking->instrument($rendered['html'], $event->token);

        Mail::to($info['email'])->queue(TemplatedMail::fromResolved($rendered));

        if ($conversation) {
            Message::create([
                'conversation_id' => $conversation->id,
                'sender_type' => 'system',
                'text' => "Campagne-e-mail verzonden: {$rendered['subject']}",
                'body' => "Campagne-e-mail verzonden: {$rendered['subject']}",
                'channel' => 'email',
                'message_type' => 'campaign_email_sent',
                'metadata' => ['campaign_id' => $campaign->id, 'email_event_id' => $event->id, 'source' => 'retell_voice'],
            ]);
            $conversation->last_message_at = now();
            $conversation->save();
        }

        $target->update(['status' => 'emailed', 'last_action_at' => now()]);

        $this->activityFeed->record($target->target_type, $target->target_id, 'campaign.email.sent', "Campaign email sent: {$campaign->name}", [
            'campaign_id' => $campaign->id,
            'email_event_id' => $event->id,
        ]);
    }

    /**
     * Shared by sendCampaignEmail() and triggerCall() so an email sent to a
     * target and a later call to the same target land in the same Chat Hub
     * thread — ChatConversationService's own reuse matching is by contact +
     * location, not channel, so this works across both call sites.
     */
    private function resolveConversationForTarget(array $info, string $channelOrigin): ?Conversation
    {
        if (! $info['email'] && ! $info['phone']) {
            return null;
        }

        return $this->chatService->createConversation([
            'contact' => array_filter(['name' => $info['name'], 'email' => $info['email'], 'phone' => $info['phone']]),
            'channel_origin' => $channelOrigin,
            'harbor_id' => $info['location_id'],
            'reuse' => true,
        ], Request::create('/campaigns/resolve-conversation', 'POST'));
    }

    private function scoreEngagedTargets(Campaign $campaign): void
    {
        $targets = $campaign->targets()
            ->where('status', 'emailed')
            ->where('last_action_at', '<=', now()->subHours(self::EMAIL_ENGAGEMENT_WAIT_HOURS))
            ->limit(200)
            ->get();

        foreach ($targets as $target) {
            $score = $this->scoring->score($target);
            $target->update(['score' => $score, 'status' => 'scored', 'last_action_at' => now()]);
        }
    }

    private function callPrioritizedTargets(Campaign $campaign): void
    {
        if (! $campaign->isWithinCallingHours(now())) {
            return;
        }

        $targets = $campaign->targets()
            ->where('status', 'scored')
            ->where('score', '>=', $campaign->min_score_to_call)
            ->where('call_attempts', '<', $campaign->max_call_attempts)
            ->orderByDesc('score')
            ->limit(20)
            ->get();

        foreach ($targets as $target) {
            $this->triggerCall($target, $campaign);
        }
    }

    public function triggerCall(CampaignTarget $target, Campaign $campaign): void
    {
        $info = $this->resolveTargetContactInfo($target);
        if (! $info['phone']) {
            $target->update(['status' => 'failed', 'metadata' => array_merge($target->metadata ?? [], ['error' => 'missing_phone'])]);

            return;
        }

        $contact = $this->contactService->resolveContact(['email' => $info['email'], 'phone' => $info['phone']], null);
        if ($contact?->do_not_contact) {
            $target->update(['status' => 'suppressed', 'suppression_reason' => 'do_not_contact']);

            return;
        }

        $conversation = $this->resolveConversationForTarget($info, 'phone');

        $message = Message::create([
            'conversation_id' => $conversation->id,
            'sender_type' => 'system',
            'message_type' => 'call',
            'status' => 'pending',
            'metadata' => [
                'to_number' => $info['phone'],
                'campaign_id' => $campaign->id,
                'campaign_target_id' => $target->id,
                'seller_id' => $info['user']?->id,
                'dynamic_variables' => $this->buildDynamicVariables($target, $campaign, $info),
            ],
        ]);

        $this->phoneCallService->initiateOutboundCall($message);

        $target->update([
            'status' => 'called',
            'call_attempts' => $target->call_attempts + 1,
            'last_action_at' => now(),
        ]);

        $this->activityFeed->record($target->target_type, $target->target_id, 'campaign.call.triggered', "Outbound call triggered: {$campaign->name}", [
            'campaign_id' => $campaign->id,
            'message_id' => $message->id,
        ]);
    }

    /**
     * @return array{name: ?string, email: ?string, phone: ?string, location_id: ?int, language: ?string, user: ?User}
     */
    private function resolveTargetContactInfo(CampaignTarget $target): array
    {
        $model = $target->targetModel();

        return match (true) {
            $model instanceof Lead => [
                'name' => $model->name,
                'email' => $model->email,
                'phone' => $model->phone,
                'location_id' => $model->location_id,
                'language' => $model->client?->locale,
                'user' => $model->client,
                'yacht_id' => $model->yacht_id,
                'lead' => $model,
            ],
            $model instanceof User => [
                'name' => $model->name,
                'email' => $model->email,
                'phone' => $model->phone,
                'location_id' => $model->client_location_id,
                'language' => $model->locale,
                'user' => $model,
                'yacht_id' => null,
                'lead' => null,
            ],
            $model instanceof Contact => [
                'name' => $model->name,
                'email' => $model->email,
                'phone' => $model->phone,
                'location_id' => null,
                'language' => $model->language_preferred,
                'user' => $model->user,
                'yacht_id' => null,
                'lead' => null,
            ],
            // Harbor/location outreach (spec §5) — prefer the location's
            // assigned owner/manager (default_seller_id) as the addressee;
            // fall back to the location's own contact details when no
            // specific person is on file yet, since outreach to a brand-new
            // prospective partner harbor may not have one.
            $model instanceof Location => [
                'name' => $model->defaultSeller?->name ?? $model->name,
                'email' => $model->defaultSeller?->email ?? $model->email,
                'phone' => $model->defaultSeller?->phone ?? $model->phone,
                'location_id' => $model->id,
                'language' => $model->defaultSeller?->locale,
                'user' => $model->defaultSeller,
                'yacht_id' => null,
                'lead' => null,
            ],
            default => [
                'name' => null, 'email' => null, 'phone' => null, 'location_id' => null,
                'language' => null, 'user' => null, 'yacht_id' => null, 'lead' => null,
            ],
        };
    }

    /**
     * Personalization variables per spec §6.
     */
    private function buildDynamicVariables(CampaignTarget $target, Campaign $campaign, array $info): array
    {
        $yacht = $info['yacht_id'] ? Yacht::find($info['yacht_id']) : null;
        $location = $info['location_id'] ? Location::find($info['location_id']) : null;
        $broker = $info['lead']?->assignedEmployee;
        $frontendBase = rtrim((string) config('app.frontend_url', 'https://schepenkring.nl'), '/');
        $locale = $info['language'] ?? 'nl';

        return array_filter([
            'user_id' => $info['user']?->id,
            'seller_id' => $info['user']?->id,
            'yacht_id' => $info['yacht_id'],
            'location_id' => $info['location_id'],
            'campaign_id' => $campaign->id,
            'user_name' => $info['name'],
            'yacht_name' => $yacht?->boat_name,
            'location_name' => $location?->name,
            'broker_name' => $broker?->name,
            'language' => $locale,
            'onboarding_url' => "{$frontendBase}/{$locale}/boot-aanmelden",
            'account_url' => "{$frontendBase}/{$locale}/dashboard/account",
        ], fn ($value) => $value !== null);
    }
}
