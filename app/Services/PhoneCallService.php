<?php

namespace App\Services;

use App\Contracts\VoiceProvider;
use App\Models\CallSession;
use App\Models\ChannelIdentity;
use App\Models\Conversation;
use App\Models\HarborChannel;
use App\Models\Message;
use App\Services\Voice\TelnyxVoiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class PhoneCallService
{
    public function __construct(
        private PhoneNumberService $numbers,
        private PhoneAbuseService $abuse,
        private ChatConversationService $chatService,
        private ChatContactService $contactService,
        private VoiceProvider $voiceProvider,
        private PhoneBillingService $billing,
        private RetellCallOutcomeValidator $outcomeValidator,
        private FollowUpService $followUps,
        private ActivityFeedService $activityFeed,
    ) {
    }

    public function handleCallInitiated(array $payload, ?string $occurredAt = null): void
    {
        $callControlId = $payload['call_control_id'] ?? null;
        if (! $callControlId) {
            return;
        }

        $direction = $this->normalizeDirection($payload['direction'] ?? ($payload['call_direction'] ?? null));
        $startedAt = $this->parseTimestamp($payload['timestamp'] ?? $occurredAt) ?? now();

        $fromNumber = $this->numbers->normalize($this->extractNumber($payload, 'from') ?? ($payload['from_phone_number'] ?? null));
        $toNumber = $this->numbers->normalize($this->extractNumber($payload, 'to') ?? ($payload['to_phone_number'] ?? null));

        $session = CallSession::firstOrNew(['call_control_id' => $callControlId]);
        $session->direction = $session->direction ?: ($direction ?: 'unknown');
        $session->status = $session->status ?: 'initiated';
        $session->from_number = $session->from_number ?: $fromNumber;
        $session->to_number = $session->to_number ?: $toNumber;
        $session->call_leg_id = $session->call_leg_id ?: ($payload['call_leg_id'] ?? null);
        $session->telnyx_call_session_id = $session->telnyx_call_session_id ?: ($payload['call_session_id'] ?? null);
        $session->started_at = $session->started_at ?: $startedAt;
        $session->metadata = array_merge($session->metadata ?? [], ['raw' => $payload]);
        $session->save();

        if ($direction === 'inbound') {
            $this->handleInboundCall($session, $payload, $fromNumber, $toNumber);
        }
    }

    public function handleCallAnswered(array $payload, ?string $occurredAt = null): void
    {
        $callControlId = $payload['call_control_id'] ?? null;
        if (! $callControlId) {
            return;
        }

        $session = CallSession::where('call_control_id', $callControlId)->first();
        if (! $session) {
            return;
        }

        $answeredAt = $this->parseTimestamp($payload['timestamp'] ?? $occurredAt) ?? now();
        $session->answered_at = $session->answered_at ?: $answeredAt;
        $session->call_leg_id = $session->call_leg_id ?: ($payload['call_leg_id'] ?? null);
        $session->status = 'answered';
        $session->save();

        if ($this->voiceProvider->usesMediaStreaming() && $this->voiceProvider instanceof TelnyxVoiceProvider) {
            $alreadyStreaming = data_get($session->metadata, 'streaming_started_at');
            $gatewayUrl = $this->buildStreamUrl($session);
            if ($gatewayUrl && ! $alreadyStreaming) {
                $this->voiceProvider->startStreaming($callControlId, $gatewayUrl, 'both');
                $session->metadata = array_merge($session->metadata ?? [], [
                    'streaming_started_at' => now()->toIso8601String(),
                ]);
                $session->save();
            }
        }
    }

    public function handleCallEnded(array $payload, ?string $occurredAt = null, ?string $eventType = null): void
    {
        $callControlId = $payload['call_control_id'] ?? null;
        if (! $callControlId) {
            return;
        }

        $session = CallSession::where('call_control_id', $callControlId)->first();
        if (! $session) {
            return;
        }

        $endedAt = $this->parseTimestamp($payload['timestamp'] ?? $occurredAt) ?? now();
        $session->ended_at = $session->ended_at ?: $endedAt;
        $session->call_leg_id = $session->call_leg_id ?: ($payload['call_leg_id'] ?? null);

        $duration = $payload['call_duration']
            ?? $payload['duration_seconds']
            ?? $payload['duration']
            ?? null;

        if ($duration === null && $session->answered_at) {
            $duration = $session->answered_at->diffInSeconds($session->ended_at ?? now());
        }

        $session->duration_seconds = $session->duration_seconds ?: ($duration !== null ? (int) $duration : null);
        $session->outcome = $session->outcome ?: ($session->answered_at ? 'completed' : 'missed');

        $failureReason = $payload['hangup_cause'] ?? $payload['reason'] ?? null;
        if ($failureReason) {
            $session->failure_reason = $failureReason;
        }

        if ($session->duration_seconds !== null) {
            $costData = $this->billing->computeCost((int) $session->duration_seconds);
            $session->billable_seconds = $costData['billable_seconds'];
            $session->cost_eur = $costData['cost'];
        }

        $session->status = $session->status === 'rejected' ? 'rejected' : 'ended';
        $session->save();

        if ($session->conversation_id) {
            $conversation = Conversation::find($session->conversation_id);
            if ($conversation) {
                $conversation->last_call_at = now();
                $conversation->save();
            }
        }

        $this->createSummaryMessage($session);
        $this->createTranscriptMessage($session);
    }

    public function handleRecordingSaved(array $payload): void
    {
        $callControlId = $payload['call_control_id'] ?? null;
        if (! $callControlId) {
            return;
        }

        $session = CallSession::where('call_control_id', $callControlId)->first();
        if (! $session) {
            return;
        }

        $recordingUrl = $payload['recording_url']
            ?? ($payload['recording_urls'][0] ?? null)
            ?? ($payload['recording'] ?? null);

        if (! $recordingUrl) {
            return;
        }

        $session->recording_url = $recordingUrl;

        if (config('voice.recordings.download')) {
            $storagePath = $this->downloadRecording($session, $recordingUrl);
            if ($storagePath) {
                $session->recording_storage_path = $storagePath;
            }
        }

        $session->save();

        if ($session->conversation_id) {
            $this->createSystemMessage($session->conversation_id, 'Call recording available.', 'call_recording', [
                'call_session_id' => $session->id,
                'recording_url' => $recordingUrl,
                'recording_storage_path' => $session->recording_storage_path,
            ]);
        }
    }

    // ── Retell webhook handlers ─────────────────────────────
    // Retell's event/payload shape is fundamentally different from Telnyx's
    // (flat call object vs. nested Call Control event envelope), so these
    // are purpose-built rather than forced through the handleCall*() methods
    // above, which stay exactly as they were for a Telnyx rollback.
    // Payload field names below reflect Retell's documented v2 webhook
    // shape as of when this was written — no live account existed to
    // verify against; re-check against Retell's current webhook reference
    // if fields come through empty/renamed in production.

    public function handleRetellCallStarted(array $call): void
    {
        $externalCallId = $call['call_id'] ?? null;
        if (! $externalCallId) {
            return;
        }

        $direction = $this->normalizeDirection($call['direction'] ?? null);
        $fromNumber = $this->numbers->normalize($call['from_number'] ?? null);
        $toNumber = $this->numbers->normalize($call['to_number'] ?? null);
        $metadata = is_array($call['metadata'] ?? null) ? $call['metadata'] : [];

        $session = CallSession::firstOrNew(['external_call_id' => $externalCallId]);
        $isNew = ! $session->exists;

        $session->provider = 'retell';
        $session->direction = $session->direction ?: ($direction ?: 'unknown');
        $session->status = $session->status ?: 'initiated';
        $session->from_number = $session->from_number ?: $fromNumber;
        $session->to_number = $session->to_number ?: $toNumber;
        $session->call_control_id = $session->call_control_id ?: $externalCallId;
        $session->started_at = $session->started_at ?: $this->parseRetellTimestamp($call['start_timestamp'] ?? null) ?? now();
        $session->agent_id = $session->agent_id ?: ($call['agent_id'] ?? null);
        $session->agent_version = $session->agent_version ?: (isset($call['agent_version']) ? (string) $call['agent_version'] : null);
        $session->campaign_id = $session->campaign_id ?: ($metadata['campaign_id'] ?? null);
        $session->seller_id = $session->seller_id ?: ($metadata['seller_id'] ?? null);
        $session->yacht_id = $session->yacht_id ?: ($metadata['yacht_id'] ?? null);
        $session->deal_id = $session->deal_id ?: ($metadata['deal_id'] ?? null);
        $session->owner_bid_id = $session->owner_bid_id ?: ($metadata['bid_id'] ?? $metadata['owner_bid_id'] ?? null);
        $session->metadata = array_merge($session->metadata ?? [], ['dynamic_variables' => $metadata, 'raw_started' => $call]);
        $session->save();

        if ($isNew) {
            $this->activityFeed->record('call_session', $session->id, 'call.created', "Retell {$direction} call started", [
                'external_call_id' => $externalCallId,
            ]);
        }

        if ($isNew && $direction === 'inbound' && $fromNumber && $toNumber) {
            $this->handleInboundCall($session, ['to' => ['phone_number' => $toNumber]], $fromNumber, $toNumber);
        }
    }

    public function handleRetellCallEnded(array $call): void
    {
        $externalCallId = $call['call_id'] ?? null;
        if (! $externalCallId) {
            return;
        }

        $session = CallSession::where('external_call_id', $externalCallId)->first();
        if (! $session) {
            return;
        }

        $session->ended_at = $session->ended_at ?: ($this->parseRetellTimestamp($call['end_timestamp'] ?? null) ?? now());

        $durationMs = $call['duration_ms'] ?? null;
        $duration = $durationMs !== null ? (int) round($durationMs / 1000) : null;
        if ($duration === null && $session->answered_at) {
            $duration = $session->answered_at->diffInSeconds($session->ended_at ?? now());
        }
        $session->duration_seconds = $session->duration_seconds ?: $duration;
        $session->transfer_status = $call['transfer_status'] ?? $session->transfer_status;
        $session->outcome = $session->outcome ?: ($session->answered_at ? 'completed' : 'missed');

        $callCost = data_get($call, 'call_cost.combined_cost');
        if ($callCost !== null) {
            // Retell reports cost directly (in cents, per their docs) rather
            // than needing PhoneBillingService's minute-rate calculation —
            // prefer it when present, fall back to the existing calculator.
            $session->cost_eur = round(((float) $callCost) / 100, 2);
            $session->billable_seconds = $session->duration_seconds;
        } elseif ($session->duration_seconds !== null) {
            $costData = $this->billing->computeCost((int) $session->duration_seconds);
            $session->billable_seconds = $costData['billable_seconds'];
            $session->cost_eur = $costData['cost'];
        }

        $session->status = 'ended';
        $session->metadata = array_merge($session->metadata ?? [], ['raw_ended' => $call]);
        $session->save();

        if ($session->conversation_id) {
            $conversation = Conversation::find($session->conversation_id);
            if ($conversation) {
                $conversation->last_call_at = now();
                $conversation->save();
            }
        }

        $this->createSummaryMessage($session);

        $this->activityFeed->record('call_session', $session->id, 'call.ended', "Call ended ({$session->duration_seconds}s, {$session->outcome})", [
            'duration_seconds' => $session->duration_seconds,
            'cost_eur' => $session->cost_eur,
        ]);
    }

    public function handleRetellCallAnalyzed(array $call): void
    {
        $externalCallId = $call['call_id'] ?? null;
        if (! $externalCallId) {
            return;
        }

        $session = CallSession::where('external_call_id', $externalCallId)->first();
        if (! $session) {
            return;
        }

        $session->transcript_text = $call['transcript'] ?? $session->transcript_text;
        $session->recording_url = $call['recording_url'] ?? $session->recording_url;
        $session->analysis = data_get($call, 'call_analysis');

        $summary = data_get($call, 'call_analysis.call_summary');
        if ($summary) {
            $session->metadata = array_merge($session->metadata ?? [], ['ai_summary' => $summary]);
        }

        $session->save();

        if (config('voice.recordings.download') && $session->recording_url && ! $session->recording_storage_path) {
            $storagePath = $this->downloadRecording($session, $session->recording_url);
            if ($storagePath) {
                $session->recording_storage_path = $storagePath;
                $session->save();
            }
        }

        $this->createTranscriptMessage($session);

        if ($session->transcript_text) {
            $this->activityFeed->record('call_session', $session->id, 'call.transcript_received', 'Transcript received');
        }

        $this->applyCallOutcome($session, data_get($call, 'call_analysis.custom_analysis_data', []));
    }

    /**
     * Validates Retell's structured outcome before letting anything act on
     * it (spec §16 — "Laravel validates this before updating statuses,
     * creating appointments, sending emails, suppressing future calls, or
     * updating deal/bid records"). An invalid/unrecognized outcome is
     * logged to the Activity Feed for manual review instead of being
     * silently dropped or blindly trusted.
     */
    private function applyCallOutcome(CallSession $session, array $analysisData): void
    {
        if (empty($analysisData)) {
            return;
        }

        $result = $this->outcomeValidator->validate($analysisData);

        if (! $result['valid']) {
            $this->activityFeed->record('call_session', $session->id, 'call.outcome.invalid', 'Retell reported an unrecognized or inconsistent call outcome', [
                'errors' => $result['errors'],
                'raw' => $analysisData,
            ]);

            return;
        }

        [$subjectType, $subjectId] = $this->resolveOutcomeSubject($session);
        if (! $subjectId) {
            return;
        }

        $campaignTargetId = data_get($session->metadata, 'campaign_target_id');

        $this->followUps->applyOutcome($subjectType, $subjectId, $result['outcome'], array_filter([
            'campaign_target_id' => $campaignTargetId,
            'ai_summary' => data_get($session->metadata, 'ai_summary'),
            'related_yacht_id' => $session->yacht_id,
            'related_deal_id' => $session->deal_id,
            'related_chat_thread_id' => $session->conversation_id,
        ], fn ($value) => $value !== null));

        $session->outcome = $result['outcome'];
        $session->save();

        $this->activityFeed->record($subjectType, $subjectId, 'call.outcome.applied', "Call outcome applied: {$result['outcome']}", [
            'call_session_id' => $session->id,
        ]);
    }

    /**
     * @return array{0: string, 1: int|string|null}
     */
    private function resolveOutcomeSubject(CallSession $session): array
    {
        if ($session->seller_id) {
            return ['user', $session->seller_id];
        }

        $leadId = data_get($session->metadata, 'lead_id');
        if ($leadId) {
            return ['lead', $leadId];
        }

        if ($session->contact_id) {
            return ['contact', $session->contact_id];
        }

        return ['call_session', $session->id];
    }

    private function parseRetellTimestamp(mixed $value): ?Carbon
    {
        if (! $value) {
            return null;
        }

        try {
            // Retell timestamps are milliseconds since epoch.
            return Carbon::createFromTimestampMs((int) $value);
        } catch (\Throwable) {
            return null;
        }
    }

    public function initiateOutboundCall(Message $message): void
    {
        if ($message->message_type !== 'call') {
            return;
        }

        if (! empty(data_get($message->metadata, 'call_session_id'))) {
            return;
        }

        $conversation = $message->conversation;
        if (! $conversation) {
            $message->status = 'failed';
            $message->metadata = array_merge($message->metadata ?? [], ['error' => 'missing_conversation']);
            $message->save();

            return;
        }

        $contact = $conversation->contact;
        if ($contact && $contact->do_not_contact) {
            $message->status = 'failed';
            $message->metadata = array_merge($message->metadata ?? [], ['error' => 'do_not_contact']);
            $message->save();

            return;
        }

        $toNumber = data_get($message->metadata, 'to_number')
            ?? data_get($message->metadata, 'phone_number')
            ?? ($contact?->phone);
        $toNumber = $this->numbers->normalize($toNumber);
        if (! $toNumber) {
            $message->status = 'failed';
            $message->metadata = array_merge($message->metadata ?? [], ['error' => 'missing_phone']);
            $message->save();

            return;
        }

        $channel = $this->resolveHarborChannelForHarbor($conversation->location_id);
        if (! $channel || ! $channel->isActive()) {
            $message->status = 'failed';
            $message->metadata = array_merge($message->metadata ?? [], ['error' => 'phone_channel_inactive']);
            $message->save();

            return;
        }

        $fromNumber = $this->numbers->normalize($channel->from_number);
        if (! $fromNumber) {
            $message->status = 'failed';
            $message->metadata = array_merge($message->metadata ?? [], ['error' => 'missing_from_number']);
            $message->save();

            return;
        }

        $session = CallSession::create([
            'conversation_id' => $conversation->id,
            'harbor_id' => $conversation->location_id,
            'contact_id' => $conversation->contact_id,
            'initiated_by_user_id' => $message->sender_type === 'admin'
                ? data_get($message->metadata, 'initiated_by_user_id')
                : null,
            'direction' => 'outbound',
            'status' => 'initiated',
            'from_number' => $fromNumber,
            'to_number' => $toNumber,
            'started_at' => now(),
            'provider' => $this->voiceProvider->name(),
            'campaign_id' => data_get($message->metadata, 'campaign_id'),
            'seller_id' => data_get($message->metadata, 'seller_id'),
            'yacht_id' => data_get($message->metadata, 'yacht_id'),
            'deal_id' => data_get($message->metadata, 'deal_id'),
            'owner_bid_id' => data_get($message->metadata, 'owner_bid_id'),
            'agent_id' => data_get($channel->metadata, 'agent_id') ?? data_get($message->metadata, 'agent_id'),
        ]);

        $conversation->last_call_at = now();
        $conversation->save();

        $payload = [
            'to' => $toNumber,
            'from' => $fromNumber,
            // Telnyx-specific routing hints; RetellVoiceProvider ignores
            // these and reads 'agent_id'/'dynamic_variables'/'metadata' instead.
            'connection_id' => data_get($channel->metadata, 'connection_id') ?? config('services.telnyx.connection_id'),
            'application_id' => data_get($channel->metadata, 'application_id') ?? config('services.telnyx.application_id'),
            'agent_id' => $session->agent_id,
            // Personalization variables per spec §6 (user_name, yacht_name,
            // onboarding_url, ...) — set by the caller (e.g. CampaignService)
            // on $message->metadata['dynamic_variables'].
            'dynamic_variables' => data_get($message->metadata, 'dynamic_variables'),
            'metadata' => array_filter([
                'campaign_id' => $session->campaign_id,
                'campaign_target_id' => data_get($message->metadata, 'campaign_target_id'),
                'seller_id' => $session->seller_id,
                'yacht_id' => $session->yacht_id,
                'deal_id' => $session->deal_id,
                'owner_bid_id' => $session->owner_bid_id,
            ], fn ($value) => $value !== null),
        ];

        $result = $this->voiceProvider->initiateOutboundCall(array_filter($payload, fn ($value) => $value !== null));
        $externalCallId = $result['external_call_id'] ?? null;

        if (! $externalCallId) {
            $session->status = 'failed';
            $session->failure_reason = $this->voiceProvider->name().'_initiate_failed';
            $session->save();

            $message->status = 'failed';
            $message->metadata = array_merge($message->metadata ?? [], ['error' => $this->voiceProvider->name().'_initiate_failed']);
            $message->save();

            return;
        }

        // Both columns get the same value for a new call: external_call_id is
        // the provider-agnostic column going forward; call_control_id is kept
        // in step so any code still querying by the old Telnyx-era column
        // name keeps working.
        $session->call_control_id = $externalCallId;
        $session->external_call_id = $externalCallId;
        $session->status = 'ringing';
        $session->save();

        $message->status = 'calling';
        $message->metadata = array_merge($message->metadata ?? [], [
            'call_session_id' => $session->id,
            'call_control_id' => $externalCallId,
        ]);
        $message->save();
    }

    private function handleInboundCall(CallSession $session, array $payload, ?string $fromNumber, ?string $toNumber): void
    {
        if (in_array($session->status, ['answered', 'ended', 'rejected'], true)) {
            return;
        }

        if (! $fromNumber || ! $toNumber) {
            $this->rejectCall($session, 'missing_number');

            return;
        }

        $channel = $this->resolveHarborChannel($payload, $toNumber);
        if (! $channel || ! $channel->isActive()) {
            $this->rejectCall($session, 'harbor_channel_not_found');

            return;
        }

        $session->harbor_id = $channel->harbor_id;
        $session->save();

        if ($this->abuse->isBlocked($fromNumber)) {
            $this->rejectCall($session, 'blocked_contact');

            return;
        }

        if (! $this->abuse->registerCallAttempt($fromNumber)) {
            $this->rejectCall($session, 'rate_limited');

            return;
        }

        $conversation = $this->resolveConversation($channel->harbor_id, $fromNumber);
        $session->conversation_id = $conversation?->id;
        $session->contact_id = $conversation?->contact_id;
        $session->from_number = $fromNumber;
        $session->to_number = $toNumber;
        $session->status = 'ringing';
        $session->save();

        if ($conversation) {
            $conversation->last_call_at = now();
            $conversation->save();
        }

        $this->voiceProvider->answerCall($session->call_control_id);
    }

    private function rejectCall(CallSession $session, string $reason): void
    {
        $session->status = 'rejected';
        $session->outcome = 'rejected';
        $session->failure_reason = $reason;
        $session->ended_at = $session->ended_at ?: now();
        $session->save();

        if ($session->call_control_id) {
            $this->voiceProvider->hangupCall($session->call_control_id, $reason);
        }
    }

    private function resolveHarborChannel(array $payload, string $toNumber): ?HarborChannel
    {
        $phoneNumberId = data_get($payload, 'to.phone_number_id')
            ?? data_get($payload, 'phone_number_id')
            ?? data_get($payload, 'called_number_id');

        $query = HarborChannel::query()
            ->where('channel', 'phone')
            ->where('provider', $this->voiceProvider->name())
            ->where('status', 'active');

        $query->where(function ($sub) use ($toNumber, $phoneNumberId) {
            $sub->where('from_number', $toNumber);
            if ($phoneNumberId) {
                $sub->orWhere('metadata->phone_number_id', $phoneNumberId);
            }
        });

        return $query->first();
    }

    private function resolveHarborChannelForHarbor(?int $harborId): ?HarborChannel
    {
        if (! $harborId) {
            return null;
        }

        return HarborChannel::query()
            ->where('channel', 'phone')
            ->where('provider', $this->voiceProvider->name())
            ->where('harbor_id', $harborId)
            ->where('status', 'active')
            ->first();
    }

    private function resolveConversation(int $harborId, string $caller): ?Conversation
    {
        $contact = $this->contactService->resolveContact([
            'phone' => $caller,
        ], null);

        if (! $contact) {
            return null;
        }

        $reuseDays = (int) config('voice.conversation_reuse_days', 90);
        $recent = Conversation::query()
            ->where('location_id', $harborId)
            ->where('contact_id', $contact->id)
            ->where('channel_origin', 'phone')
            ->where('last_call_at', '>=', now()->subDays($reuseDays))
            ->orderByDesc('last_call_at')
            ->first();

        if ($recent) {
            $threadKey = $this->threadKey($harborId, $caller);
            ChannelIdentity::updateOrCreate([
                'conversation_id' => $recent->id,
                'type' => 'phone',
                'external_thread_id' => $threadKey,
            ], [
                'external_user_id' => $caller,
            ]);

            return $recent;
        }

        $conversation = $this->chatService->createConversation([
            'contact' => [
                'phone' => $caller,
            ],
            'channel_origin' => 'phone',
            'harbor_id' => $harborId,
            'reuse' => false,
            'skip_rate_limit' => true,
            'allow_blocked_contacts' => true,
        ], $this->fakeRequest());

        $threadKey = $this->threadKey($harborId, $caller);
        ChannelIdentity::updateOrCreate([
            'conversation_id' => $conversation->id,
            'type' => 'phone',
            'external_thread_id' => $threadKey,
        ], [
            'external_user_id' => $caller,
        ]);

        return $conversation;
    }

    private function threadKey(int $harborId, string $caller): string
    {
        return 'phone:'.$harborId.':'.$caller;
    }

    private function extractNumber(array $payload, string $key): ?string
    {
        $value = $payload[$key] ?? null;
        if (is_array($value)) {
            foreach (['phone_number', 'number', 'uri', 'caller_id', 'phone'] as $field) {
                if (! empty($value[$field])) {
                    return $value[$field];
                }
            }
        }

        if (is_string($value)) {
            return $value;
        }

        return null;
    }

    private function normalizeDirection(?string $direction): string
    {
        $direction = strtolower((string) $direction);
        if (in_array($direction, ['incoming', 'inbound'], true)) {
            return 'inbound';
        }
        if (in_array($direction, ['outgoing', 'outbound'], true)) {
            return 'outbound';
        }

        return $direction !== '' ? $direction : 'unknown';
    }

    private function parseTimestamp(mixed $value): ?Carbon
    {
        if (! $value) {
            return null;
        }

        try {
            if (is_numeric($value)) {
                return Carbon::createFromTimestamp((int) $value);
            }

            return Carbon::parse((string) $value);
        } catch (\Throwable) {
            return null;
        }
    }

    private function buildStreamUrl(CallSession $session): ?string
    {
        $base = (string) config('voice.gateway_url');
        if ($base === '') {
            return null;
        }

        $separator = str_contains($base, '?') ? '&' : '?';
        $query = http_build_query([
            'call_session_id' => $session->id,
            'call_control_id' => $session->call_control_id,
            'harbor_id' => $session->harbor_id,
        ]);

        return $base.$separator.$query;
    }

    private function downloadRecording(CallSession $session, string $url): ?string
    {
        try {
            $disk = config('voice.recordings.disk', 'public');
            $basePath = trim((string) config('voice.recordings.path', 'call-recordings'), '/');

            $extension = pathinfo(parse_url($url, PHP_URL_PATH) ?? '', PATHINFO_EXTENSION);
            $extension = $extension ?: 'mp3';

            $filename = $session->id.'.'.$extension;
            $path = $basePath.'/'.$filename;

            $contents = file_get_contents($url);
            if ($contents === false) {
                return null;
            }

            Storage::disk($disk)->put($path, $contents);

            return $path;
        } catch (\Throwable $e) {
            Log::warning('Failed to download call recording', [
                'call_session_id' => $session->id,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    private function createSummaryMessage(CallSession $session): void
    {
        if (! $session->conversation_id) {
            return;
        }

        $exists = Message::where('conversation_id', $session->conversation_id)
            ->where('message_type', 'call_summary')
            ->where('metadata->call_session_id', $session->id)
            ->exists();

        if ($exists) {
            return;
        }

        $duration = $session->duration_seconds ?? 0;
        $minutes = floor($duration / 60);
        $seconds = $duration % 60;
        $durationLabel = sprintf('%d:%02d', $minutes, $seconds);

        $label = match ($session->outcome) {
            'missed' => "Missed call ({$durationLabel})",
            'rejected' => "Call rejected ({$durationLabel})",
            default => "Call ended ({$durationLabel})",
        };

        $this->createSystemMessage($session->conversation_id, $label, 'call_summary', [
            'call_session_id' => $session->id,
            'duration_seconds' => $session->duration_seconds,
            'cost_eur' => $session->cost_eur,
            'source' => $this->messageSource($session),
        ]);
    }

    private function createTranscriptMessage(CallSession $session): void
    {
        if (! $session->conversation_id || ! $session->transcript_text) {
            return;
        }

        $exists = Message::where('conversation_id', $session->conversation_id)
            ->where('message_type', 'call_transcript')
            ->where('metadata->call_session_id', $session->id)
            ->exists();

        if ($exists) {
            return;
        }

        $this->createSystemMessage($session->conversation_id, $session->transcript_text, 'call_transcript', [
            'call_session_id' => $session->id,
            'source' => $this->messageSource($session),
            'ai_summary' => data_get($session->metadata, 'ai_summary'),
        ]);
    }

    /**
     * Lets the Chat Hub UI visually distinguish an AI-driven Retell call
     * from a staff-initiated Telnyx call in the same thread (spec §10:
     * "Store AI call summaries as Chat Hub messages: source = retell_voice").
     */
    private function messageSource(CallSession $session): string
    {
        return $session->provider === 'retell' ? 'retell_voice' : (string) ($session->provider ?? 'phone');
    }

    private function createSystemMessage(string $conversationId, string $text, string $messageType, array $metadata = []): void
    {
        $conversation = Conversation::find($conversationId);
        if (! $conversation) {
            return;
        }

        Message::create([
            'conversation_id' => $conversationId,
            'sender_type' => 'system',
            'text' => $text,
            'body' => $text,
            'channel' => 'phone',
            'message_type' => $messageType,
            'metadata' => $metadata,
        ]);

        $conversation->last_message_at = now();
        $conversation->save();
    }

    private function fakeRequest(?string $ip = null): Request
    {
        $request = Request::create('/webhooks/telnyx/voice', 'POST', []);
        $request->server->set('REMOTE_ADDR', $ip ?: '127.0.0.1');

        return $request;
    }
}
