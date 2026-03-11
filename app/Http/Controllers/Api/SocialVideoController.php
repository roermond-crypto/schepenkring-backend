<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\RenderMarketingVideo;
use App\Models\Yacht;
use App\Models\Video;
use App\Models\VideoPost;
use App\Models\User;
use App\Services\LocationAccessService;
use App\Services\VideoAutomationService;
use App\Services\VideoSchedulerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SocialVideoController extends Controller
{
    public function __construct(
        private LocationAccessService $locationAccess,
        private VideoAutomationService $automation
    ) {
    }

    public function generate(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $validated = $request->validate([
            'yacht_id' => ['required', 'integer', 'exists:yachts,id'],
            'template_type' => ['nullable', 'string', 'max:100'],
            'force' => ['nullable', 'boolean'],
        ]);

        $yacht = Yacht::findOrFail($validated['yacht_id']);
        $this->authorizeYachtAccess($user, $yacht);

        $force = (bool) ($validated['force'] ?? false);
        if (! $force) {
            $existing = $this->automation->findReusableVideo($yacht);
            if ($existing) {
                return response()->json([
                    'message' => 'Existing generated video returned',
                    'video' => $existing->load('posts'),
                    'renderable_image_count' => $this->automation->renderableImageCount($yacht),
                ]);
            }
        }

        $renderableImageCount = $this->automation->renderableImageCount($yacht);
        if ($renderableImageCount === 0) {
            return response()->json([
                'message' => 'No usable boat images found for video generation.',
            ], 422);
        }

        $result = $this->automation->queueManualVideo(
            $yacht,
            $validated['template_type'] ?? null,
            $force
        );

        return response()->json([
            'message' => $result['created']
                ? 'Video generation queued'
                : 'Existing generated video returned',
            'video' => $result['video']->load('posts'),
            'renderable_image_count' => $renderableImageCount,
        ], $result['created'] ? 202 : 200);
    }

    public function schedule(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'start_date' => ['required', 'date'],
            'cadence' => ['required', 'in:daily'],
            'time' => ['required', 'date_format:H:i'],
            'video_ids' => ['required', 'array', 'min:1'],
            'video_ids.*' => ['integer', 'exists:videos,id'],
            'publishers' => ['nullable', 'array'],
            'publishers.*' => ['string'],
            'skip_weekends' => ['nullable', 'boolean'],
            'yext_account_id' => ['nullable', 'string'],
            'yext_entity_id' => ['nullable', 'string'],
        ]);

        $scheduled = app(VideoSchedulerService::class)->scheduleVideos(
            $validated['video_ids'],
            $validated['start_date'],
            $validated['time'],
            (bool) ($validated['skip_weekends'] ?? config('video_automation.skip_weekends', false)),
            $validated['publishers'] ?? config('video_automation.default_publishers', []),
            $validated['yext_account_id'] ?? null,
            $validated['yext_entity_id'] ?? null
        );

        return response()->json([
            'scheduled' => $scheduled,
            'count' => $scheduled->count(),
        ]);
    }

    public function listVideos(Request $request): JsonResponse
    {
        $query = Video::query()->with('posts');

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        if ($request->filled('yacht_id')) {
            $query->where('yacht_id', $request->integer('yacht_id'));
        }

        $videos = $query->orderBy('created_at', 'desc')->paginate(20);

        return response()->json($videos);
    }

    public function listPosts(Request $request): JsonResponse
    {
        $query = VideoPost::query()->with('video.yacht');

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        if ($request->filled('from')) {
            $query->where('scheduled_at', '>=', $request->date('from'));
        }

        if ($request->filled('to')) {
            $query->where('scheduled_at', '<=', $request->date('to'));
        }

        $posts = $query->orderBy('scheduled_at', 'desc')->paginate(20);

        return response()->json($posts);
    }

    public function reschedule(Request $request, int $id): JsonResponse
    {
        $post = VideoPost::findOrFail($id);

        $validated = $request->validate([
            'scheduled_at' => ['required', 'date'],
        ]);

        $post->update([
            'scheduled_at' => $validated['scheduled_at'],
            'status' => 'scheduled',
            'error_message' => null,
        ]);

        return response()->json([
            'message' => 'Post rescheduled',
            'post' => $post,
        ]);
    }

    public function retry(int $id): JsonResponse
    {
        $post = VideoPost::findOrFail($id);

        $post->update([
            'status' => 'scheduled',
            'error_message' => null,
        ]);

        return response()->json([
            'message' => 'Post queued for retry',
            'post' => $post,
        ]);
    }

    public function regenerate(int $id): JsonResponse
    {
        $video = Video::findOrFail($id);

        $video->update([
            'status' => 'queued',
            'error_message' => null,
        ]);

        RenderMarketingVideo::dispatch($video->id)->onQueue('video-rendering');

        return response()->json([
            'message' => 'Video regeneration queued',
            'video' => $video,
        ], 202);
    }

    private function authorizeYachtAccess(User $user, Yacht $yacht): void
    {
        if ($user->isAdmin()) {
            return;
        }

        if ($user->isClient() && (int) $yacht->user_id === (int) $user->id) {
            return;
        }

        if ($user->isEmployee()) {
            $locationId = $this->resolveYachtLocationId($yacht);
            if ($locationId !== null && $this->locationAccess->sharesLocation($user, $locationId)) {
                return;
            }
        }

        abort(403, 'Forbidden');
    }

    private function resolveYachtLocationId(Yacht $yacht): ?int
    {
        if ($yacht->ref_harbor_id) {
            return (int) $yacht->ref_harbor_id;
        }

        return $yacht->owner?->client_location_id ? (int) $yacht->owner->client_location_id : null;
    }
}
