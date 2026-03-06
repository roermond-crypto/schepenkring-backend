<?php

namespace App\Services;

use App\Models\Video;
use App\Models\VideoPost;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class VideoSchedulerService
{
    public function scheduleVideos(
        array $videoIds,
        string $startDate,
        string $time,
        bool $skipWeekends,
        array $publishers = [],
        ?string $yextAccountId = null,
        ?string $yextEntityId = null
    ): Collection {
        $scheduled = collect();
        $cursor = Carbon::parse($startDate . ' ' . $time);

        foreach ($videoIds as $videoId) {
            $video = Video::find($videoId);
            if (!$video) {
                continue;
            }

            if ($video->status !== 'ready') {
                continue;
            }

            $existing = VideoPost::where('video_id', $video->id)
                ->whereIn('status', ['scheduled', 'publishing', 'published'])
                ->exists();
            if ($existing) {
                continue;
            }

            $cursor = $this->nextAvailableSlot($cursor, $skipWeekends);

            $post = VideoPost::create([
                'video_id' => $video->id,
                'publishers' => $publishers,
                'scheduled_at' => $cursor->copy(),
                'status' => 'scheduled',
                'yext_account_id' => $yextAccountId,
                'yext_entity_id' => $yextEntityId,
            ]);

            $scheduled->push($post);

            $cursor->addDay();
        }

        return $scheduled;
    }

    public function scheduleNextAvailable(
        Video $video,
        string $time,
        bool $skipWeekends,
        array $publishers = [],
        ?string $yextAccountId = null,
        ?string $yextEntityId = null
    ): ?VideoPost {
        if ($video->status !== 'ready') {
            return null;
        }

        $existing = VideoPost::where('video_id', $video->id)
            ->whereIn('status', ['scheduled', 'publishing', 'published'])
            ->exists();
        if ($existing) {
            return null;
        }

        $cursor = Carbon::now()->addDay()->setTimeFromTimeString($time);
        $cursor = $this->nextAvailableSlot($cursor, $skipWeekends);

        return VideoPost::create([
            'video_id' => $video->id,
            'publishers' => $publishers,
            'scheduled_at' => $cursor,
            'status' => 'scheduled',
            'yext_account_id' => $yextAccountId,
            'yext_entity_id' => $yextEntityId,
        ]);
    }

    private function nextAvailableSlot(Carbon $cursor, bool $skipWeekends): Carbon
    {
        while (true) {
            if ($skipWeekends && $cursor->isWeekend()) {
                $time = $cursor->format('H:i');
                $cursor->addDay()->startOfDay()->setTimeFromTimeString($time);
                continue;
            }

            $hasConflict = VideoPost::whereDate('scheduled_at', $cursor->toDateString())
                ->whereIn('status', ['scheduled', 'publishing', 'published'])
                ->exists();

            if ($hasConflict) {
                $cursor->addDay();
                continue;
            }

            break;
        }

        return $cursor;
    }
}
