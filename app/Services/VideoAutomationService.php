<?php

namespace App\Services;

use App\Jobs\RenderMarketingVideo;
use App\Models\Video;
use App\Models\Yacht;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

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

        if ($this->renderableImageCount($yacht) === 0) {
            Log::info('Marketing video skipped because no renderable images are available', [
                'yacht_id' => $yacht->id,
                'trigger' => $trigger,
            ]);

            return null;
        }

        $existing = $this->findReusableVideo($yacht);

        if ($existing) {
            return $existing;
        }

        $video = $this->createQueuedVideo($yacht, config('video_automation.template_type'), $trigger);

        Log::info('Marketing video queued', [
            'yacht_id' => $yacht->id,
            'video_id' => $video->id,
            'trigger' => $trigger,
        ]);

        return $video;
    }

    /**
     * @return array{video: Video, created: bool}
     */
    public function queueManualVideo(Yacht $yacht, ?string $templateType = null, bool $force = false): array
    {
        if (! $force) {
            $existing = $this->findReusableVideo($yacht);
            if ($existing) {
                return [
                    'video' => $existing,
                    'created' => false,
                ];
            }
        }

        return [
            'video' => $this->createQueuedVideo($yacht, $templateType, 'manual'),
            'created' => true,
        ];
    }

    public function findReusableVideo(Yacht $yacht): ?Video
    {
        return Video::where('yacht_id', $yacht->id)
            ->whereIn('status', ['queued', 'processing', 'ready'])
            ->latest()
            ->first();
    }

    /**
     * @return array<int, string>
     */
    public function collectRenderableImagePaths(Yacht $yacht): array
    {
        $paths = [];

        $this->appendRenderablePath($paths, $yacht->main_image);

        $images = $yacht->images()->orderBy('sort_order')->get();
        $approvedImages = $images->where('status', 'approved');
        $sourceImages = $approvedImages->isNotEmpty() ? $approvedImages : $images;

        foreach ($sourceImages as $image) {
            foreach ([
                $image->optimized_master_url,
                $image->url,
                $image->original_kept_url,
                $image->thumb_url,
            ] as $candidate) {
                $this->appendRenderablePath($paths, $candidate);
            }
        }

        return array_values(array_unique($paths));
    }

    public function renderableImageCount(Yacht $yacht): int
    {
        return count($this->collectRenderableImagePaths($yacht));
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

    public function isPublishedStatus(?string $status): bool
    {
        $status = strtolower(trim((string) $status));
        if ($status === '' || in_array($status, ['draft', 'withdrawn'], true)) {
            return false;
        }

        $publishStatuses = array_map('strtolower', config('video_automation.publish_statuses', []));

        if ($publishStatuses === []) {
            return true;
        }

        return in_array($status, $publishStatuses, true);
    }

    private function isPublishable(Yacht $yacht, string $trigger): bool
    {
        return $this->isPublishedStatus($yacht->status);
    }

    private function createQueuedVideo(Yacht $yacht, ?string $templateType = null, ?string $trigger = null): Video
    {
        $video = Video::create([
            'yacht_id' => $yacht->id,
            'status' => 'queued',
            'template_type' => $templateType ?: config('video_automation.template_type'),
            'generation_trigger' => $trigger,
            'whatsapp_status' => config('video_automation.auto_notify_owner_whatsapp', true) ? 'pending' : 'skipped',
        ]);

        RenderMarketingVideo::dispatch($video->id)->onQueue('video-rendering');

        return $video;
    }

    /**
     * @param  array<int, string>  $paths
     */
    private function appendRenderablePath(array &$paths, ?string $candidate): void
    {
        $path = $this->resolveRenderablePath($candidate);
        if ($path) {
            $paths[] = $path;
        }
    }

    private function resolveRenderablePath(?string $candidate): ?string
    {
        if (! is_string($candidate)) {
            return null;
        }

        $candidate = trim($candidate);
        if ($candidate === '' || preg_match('/^https?:\/\//i', $candidate) === 1) {
            return null;
        }

        if (file_exists($candidate)) {
            return $candidate;
        }

        $storagePath = Storage::disk('public')->path(ltrim($candidate, '/'));

        return file_exists($storagePath) ? $storagePath : null;
    }
}
