<?php

namespace App\Services;

use App\Jobs\RenderMarketingVideo;
use App\Models\Video;
use App\Models\Yacht;
use Illuminate\Support\Facades\Log;

class VideoAutomationService
{
    public function handleYachtCreated(Yacht $yacht): ?Video
    {
        if (!config('video_automation.enabled') || !config('video_automation.auto_on_create')) {
            return null;
        }

        return $this->queueVideoIfEligible($yacht, 'created');
    }

    public function handleYachtPublished(Yacht $yacht): ?Video
    {
        if (!config('video_automation.enabled') || !config('video_automation.auto_on_publish')) {
            return null;
        }

        return $this->queueVideoIfEligible($yacht, 'published');
    }

    public function queueVideoIfEligible(Yacht $yacht, string $trigger): ?Video
    {
        if (!$this->isPublishable($yacht, $trigger)) {
            return null;
        }

        $existing = Video::where('yacht_id', $yacht->id)
            ->whereIn('status', ['queued', 'processing', 'ready'])
            ->latest()
            ->first();

        if ($existing) {
            return $existing;
        }

        $video = Video::create([
            'yacht_id' => $yacht->id,
            'status' => 'queued',
            'template_type' => config('video_automation.template_type'),
        ]);

        RenderMarketingVideo::dispatch($video->id)->onQueue('video-rendering');

        Log::info('Marketing video queued', [
            'yacht_id' => $yacht->id,
            'video_id' => $video->id,
            'trigger' => $trigger,
        ]);

        return $video;
    }

    public function buildClickthroughUrl(Yacht $yacht, ?Video $video = null): string
    {
        $base = rtrim(config('app.url'), '/');
        $utm = config('video_automation.utm');
        $content = $video ? 'video' . $video->id : 'video';

        return sprintf(
            '%s/boats/%s?utm_source=%s&utm_medium=%s&utm_campaign=%s&utm_content=%s',
            $base,
            $yacht->id,
            urlencode((string) ($utm['source'] ?? 'yext')),
            urlencode((string) ($utm['medium'] ?? 'social')),
            urlencode((string) ($utm['campaign'] ?? 'boat_video')),
            urlencode($content)
        );
    }

    private function isPublishable(Yacht $yacht, string $trigger): bool
    {
        if ($trigger === 'created') {
            return true;
        }

        $status = strtolower((string) $yacht->status);
        $publishStatuses = array_map('strtolower', config('video_automation.publish_statuses', []));

        if (in_array($status, ['draft', 'withdrawn'], true)) {
            return false;
        }

        if (empty($publishStatuses)) {
            return $status !== '';
        }

        return in_array($status, $publishStatuses, true);
    }
}
