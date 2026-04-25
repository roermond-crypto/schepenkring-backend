<?php

namespace App\Jobs;

use App\Models\HarborChannel;
use App\Models\Video;
use App\Services\PhoneNumberService;
use App\Services\WhatsApp360DialogService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendBoatVideoWhatsappJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public array $backoff = [60, 180, 300];

    public function __construct(
        public int $videoId,
        public bool $force = false
    ) {
        $this->afterCommit();
    }

    public function handle(
        WhatsApp360DialogService $service,
        PhoneNumberService $numbers
    ): void {
        $video = Video::with(['yacht.owner'])->find($this->videoId);
        if (! $video || $video->status !== 'ready' || ! $video->yacht) {
            return;
        }

        if (! $this->force && $video->whatsapp_sent_at && $video->whatsapp_status === 'sent') {
            return;
        }

        $recipient = $numbers->normalize($video->yacht->owner?->phone);
        if (! $recipient) {
            $this->markSkipped($video, 'missing_owner_phone');

            return;
        }

        $locationId = $video->yacht->location_id ?: $video->yacht->owner?->client_location_id;
        if (! $locationId) {
            $this->markFailed($video, 'missing_location');

            return;
        }

        $channel = HarborChannel::query()
            ->where('harbor_id', $locationId)
            ->where('channel', 'whatsapp')
            ->where('provider', '360dialog')
            ->where('status', 'active')
            ->first();

        if (! $channel) {
            $this->markFailed($video, 'missing_whatsapp_channel');

            return;
        }

        $body = $this->buildMessage($video);
        if ($body === null) {
            $this->markFailed($video, 'missing_video_url');

            return;
        }

        $payload = [
            'messaging_product' => 'whatsapp',
            'to' => ltrim($recipient, '+'),
            'type' => 'text',
            'text' => [
                'body' => $body,
            ],
        ];

        $video->update([
            'whatsapp_status' => 'processing',
            'whatsapp_error' => null,
            'whatsapp_recipient' => $payload['to'],
        ]);

        try {
            $response = $service->sendMessage($channel, $payload);

            $video->update([
                'whatsapp_status' => 'sent',
                'whatsapp_sent_at' => now(),
                'whatsapp_message_id' => data_get($response, 'messages.0.id'),
                'whatsapp_recipient' => $payload['to'],
                'whatsapp_error' => null,
            ]);
        } catch (\Throwable $e) {
            Log::error('Boat video WhatsApp send failed', [
                'video_id' => $video->id,
                'error' => $e->getMessage(),
            ]);

            $this->markFailed($video, 'send_failed: '.$e->getMessage());
        }
    }

    private function buildMessage(Video $video): ?string
    {
        $url = $video->video_url;
        if (! $url) {
            return null;
        }

        $lines = [
            'Your boat video is ready',
            '',
            trim((string) ($video->yacht?->boat_name ?: 'Boat listing')),
            'Watch it here:',
            $url,
        ];

        $listingUrl = $video->yacht?->external_url;
        if (is_string($listingUrl) && trim($listingUrl) !== '') {
            $lines[] = '';
            $lines[] = 'Listing:';
            $lines[] = trim($listingUrl);
        }

        return implode("\n", $lines);
    }

    private function markSkipped(Video $video, string $reason): void
    {
        $video->update([
            'whatsapp_status' => 'skipped',
            'whatsapp_error' => $reason,
        ]);
    }

    private function markFailed(Video $video, string $reason): void
    {
        $video->update([
            'whatsapp_status' => 'failed',
            'whatsapp_error' => $reason,
        ]);
    }
}
