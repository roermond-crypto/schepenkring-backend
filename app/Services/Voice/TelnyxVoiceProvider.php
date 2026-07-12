<?php

namespace App\Services\Voice;

use App\Contracts\VoiceProvider;
use App\Services\TelnyxService;

/**
 * Legacy voice provider, kept behind the same VoiceProvider contract as
 * RetellVoiceProvider so a rollback (config/voice.php's 'provider' key)
 * stays a config change rather than a code change. Not bound by default —
 * see AppServiceProvider.
 */
class TelnyxVoiceProvider implements VoiceProvider
{
    public function __construct(private TelnyxService $telnyx)
    {
    }

    public function name(): string
    {
        return 'telnyx';
    }

    public function usesMediaStreaming(): bool
    {
        // Telnyx Call Control requires Laravel to explicitly bridge/stream
        // call audio to the AI gateway (see config('voice.gateway_url'));
        // this is the one behavior PhoneCallService still special-cases.
        return true;
    }

    public function initiateOutboundCall(array $payload): array
    {
        $body = ['to' => $payload['to'] ?? null, 'from' => $payload['from'] ?? null];

        $connectionId = $payload['connection_id'] ?? config('services.telnyx.connection_id');
        $applicationId = $payload['application_id'] ?? config('services.telnyx.application_id');
        if ($connectionId) {
            $body['connection_id'] = $connectionId;
        }
        if ($applicationId) {
            $body['application_id'] = $applicationId;
        }

        $response = $this->telnyx->initiateCall($body);

        return [
            'external_call_id' => data_get($response, 'data.call_control_id'),
            'raw' => $response,
        ];
    }

    public function answerCall(string $externalCallId): array
    {
        return $this->telnyx->answerCall($externalCallId);
    }

    public function hangupCall(string $externalCallId, ?string $reason = null): array
    {
        return $this->telnyx->hangupCall($externalCallId, $reason);
    }

    public function getCallStatus(string $externalCallId): array
    {
        // TelnyxService never implemented a status-lookup call — this
        // provider was driven entirely by webhook events. Not needed for
        // rollback purposes today; left unimplemented rather than guessing
        // at an endpoint shape that was never exercised.
        return [];
    }

    public function startStreaming(string $externalCallId, string $streamUrl, ?string $track = null): array
    {
        return $this->telnyx->startStreaming($externalCallId, $streamUrl, $track);
    }
}
