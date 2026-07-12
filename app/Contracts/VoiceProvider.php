<?php

namespace App\Contracts;

/**
 * Generic contract every voice-calling backend (Retell, Telnyx, Bland,
 * Vapi, ...) implements, so PhoneCallService and callers never depend on a
 * specific vendor's API shape directly.
 */
interface VoiceProvider
{
    /**
     * Provider slug — matches the `provider` column on call_sessions and
     * HarborChannel (e.g. 'retell', 'telnyx').
     */
    public function name(): string;

    /**
     * Whether this provider needs Laravel to bridge/stream call audio itself.
     * Telnyx Call Control does (see the legacy streaming_start action in
     * TelnyxService); Retell's own infrastructure answers, converses, and
     * streams audio end-to-end, so it doesn't.
     */
    public function usesMediaStreaming(): bool;

    /**
     * Originate an outbound call. $payload carries at minimum 'from'/'to'
     * numbers; providers may accept extra keys (e.g. an agent override or
     * dynamic personalization variables — see RetellVoiceProvider).
     *
     * @return array{external_call_id: ?string, raw: array}
     */
    public function initiateOutboundCall(array $payload): array;

    /**
     * Explicitly answer an inbound call. No-ops for providers (like Retell)
     * that answer configured numbers automatically.
     */
    public function answerCall(string $externalCallId): array;

    public function hangupCall(string $externalCallId, ?string $reason = null): array;

    /**
     * Best-effort status lookup, used for reconciliation / manual refresh.
     */
    public function getCallStatus(string $externalCallId): array;
}
