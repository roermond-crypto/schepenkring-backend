<?php

namespace App\Services;

use App\Models\CallSession;
use App\Models\ChannelIdentity;
use App\Models\Conversation;
use App\Models\HarborChannel;
use App\Models\Message;
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
        private TelnyxService $telnyx,
        private PhoneBillingService $billing
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

        $alreadyStreaming = data_get($session->metadata, 'streaming_started_at');
        $gatewayUrl = $this->buildStreamUrl($session);
        if ($gatewayUrl && ! $alreadyStreaming) {
            $this->telnyx->startStreaming($callControlId, $gatewayUrl, 'both');
            $session->metadata = array_merge($session->metadata ?? [], [
                'streaming_started_at' => now()->toIso8601String(),
            ]);
            $session->save();
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
        ]);

        $conversation->last_call_at = now();
        $conversation->save();

        $payload = [
            'to' => $toNumber,
            'from' => $fromNumber,
        ];

        $connectionId = data_get($channel->metadata, 'connection_id') ?? config('services.telnyx.connection_id');
        $applicationId = data_get($channel->metadata, 'application_id') ?? config('services.telnyx.application_id');

        if ($connectionId) {
            $payload['connection_id'] = $connectionId;
        }
        if ($applicationId) {
            $payload['application_id'] = $applicationId;
        }

        $response = $this->telnyx->initiateCall($payload);
        $callControlId = data_get($response, 'data.call_control_id');

        if (! $callControlId) {
            $session->status = 'failed';
            $session->failure_reason = 'telnyx_initiate_failed';
            $session->save();

            $message->status = 'failed';
            $message->metadata = array_merge($message->metadata ?? [], ['error' => 'telnyx_initiate_failed']);
            $message->save();

            return;
        }

        $session->call_control_id = $callControlId;
        $session->status = 'ringing';
        $session->save();

        $message->status = 'calling';
        $message->metadata = array_merge($message->metadata ?? [], [
            'call_session_id' => $session->id,
            'call_control_id' => $callControlId,
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

        $this->telnyx->answerCall($session->call_control_id);
    }

    private function rejectCall(CallSession $session, string $reason): void
    {
        $session->status = 'rejected';
        $session->outcome = 'rejected';
        $session->failure_reason = $reason;
        $session->ended_at = $session->ended_at ?: now();
        $session->save();

        if ($session->call_control_id) {
            $this->telnyx->hangupCall($session->call_control_id, $reason);
        }
    }

    private function resolveHarborChannel(array $payload, string $toNumber): ?HarborChannel
    {
        $phoneNumberId = data_get($payload, 'to.phone_number_id')
            ?? data_get($payload, 'phone_number_id')
            ?? data_get($payload, 'called_number_id');

        $query = HarborChannel::query()
            ->where('channel', 'phone')
            ->where('provider', 'telnyx')
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
            ->where('provider', 'telnyx')
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
        ]);
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
