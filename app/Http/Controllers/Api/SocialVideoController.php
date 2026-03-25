<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\RenderMarketingVideo;
use App\Jobs\SendBoatVideoWhatsappJob;
use App\Models\Yacht;
use App\Models\Video;
use App\Models\VideoPost;
use App\Models\User;
use App\Services\LocationAccessService;
use App\Services\VideoAutomationService;
use App\Services\VideoSchedulerService;
use Illuminate\Database\Eloquent\Builder;
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
        $user = $this->requireUser($request);

        $validated = $request->validate([
            'yacht_id' => ['required', 'integer', 'exists:yachts,id'],
            'boat_id' => ['nullable', 'integer'],
            'image_ids' => ['nullable', 'array', 'min:1'],
            'image_ids.*' => ['integer', 'exists:yacht_images,id'],
            'approved_image_ids' => ['nullable', 'array', 'min:1'],
            'approved_image_ids.*' => ['integer', 'exists:yacht_images,id'],
            'use_approved_images_only' => ['nullable', 'boolean'],
            'template_type' => ['nullable', 'string', 'max:100'],
            'force' => ['nullable', 'boolean'],
        ]);

        if (
            isset($validated['boat_id']) &&
            (int) $validated['boat_id'] !== (int) $validated['yacht_id']
        ) {
            return response()->json([
                'message' => 'boat_id must match yacht_id for video generation.',
            ], 422);
        }

        $yacht = Yacht::findOrFail($validated['yacht_id']);
        $this->authorizeYachtAccess($user, $yacht);

        $selectedSourceImageIds = collect(
            $validated['approved_image_ids'] ?? $validated['image_ids'] ?? []
        )
            ->map(static fn ($id) => (int) $id)
            ->filter(static fn (int $id) => $id > 0)
            ->unique()
            ->values()
            ->all();

        $enforceApprovedSelection =
            (bool) ($validated['use_approved_images_only'] ?? false) ||
            array_key_exists('approved_image_ids', $validated);

        if ($selectedSourceImageIds !== []) {
            $availableImageIds = $yacht->images()
                ->when($enforceApprovedSelection, static function (Builder $query): void {
                    $query->where('status', 'approved');
                })
                ->whereIn('id', $selectedSourceImageIds)
                ->pluck('id')
                ->map(static fn ($id) => (int) $id)
                ->all();

            $availableIndex = array_flip($availableImageIds);
            $selectedSourceImageIds = array_values(array_filter(
                $selectedSourceImageIds,
                static fn (int $id): bool => isset($availableIndex[$id])
            ));

            if (count($selectedSourceImageIds) !== count($validated['approved_image_ids'] ?? $validated['image_ids'] ?? [])) {
                return response()->json([
                    'message' => 'Selected images must belong to the requested yacht and be approved.',
                ], 422);
            }
        }

        $force = (bool) ($validated['force'] ?? false);
        $renderableImageCount = $this->automation->renderableImageCount($yacht, $selectedSourceImageIds);
        if ($renderableImageCount === 0) {
            return response()->json([
                'message' => 'No usable boat images found for video generation.',
            ], 422);
        }

        $result = $this->automation->queueManualVideo(
            $yacht,
            $validated['template_type'] ?? null,
            $force,
            $selectedSourceImageIds,
            $force ? 'manual_force' : 'manual'
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
        $user = $this->requireUser($request);

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

        $videos = Video::query()
            ->with('yacht.owner')
            ->whereIn('id', $validated['video_ids'])
            ->get()
            ->keyBy('id');

        foreach ($validated['video_ids'] as $videoId) {
            $video = $videos->get((int) $videoId);
            if (! $video) {
                abort(404, 'Video not found.');
            }

            $this->authorizeVideoAccess($user, $video);
        }

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
        $user = $this->requireUser($request);
        $query = Video::query()->with(['posts', 'yacht.owner', 'yacht.videoSetting']);

        $this->applyVisibleVideoScope($query, $user);

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        if ($request->filled('yacht_id')) {
            $query->where('yacht_id', $request->integer('yacht_id'));
        }

        $videos = $query->orderBy('created_at', 'desc')->paginate(20);

        return response()->json($videos);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $user = $this->requireUser($request);
        $video = Video::query()
            ->with(['posts', 'yacht.owner', 'yacht.videoSetting'])
            ->findOrFail($id);

        $this->authorizeVideoAccess($user, $video);

        return response()->json($video);
    }

    public function listPosts(Request $request): JsonResponse
    {
        $user = $this->requireUser($request);
        $query = VideoPost::query()->with('video.yacht');

        $this->applyVisiblePostScope($query, $user);

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
        $user = $this->requireUser($request);
        $post = $this->findAuthorizedPost($user, $id);

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

    public function retry(Request $request, int $id): JsonResponse
    {
        $user = $this->requireUser($request);
        $post = $this->findAuthorizedPost($user, $id);

        $post->update([
            'status' => 'scheduled',
            'error_message' => null,
        ]);

        return response()->json([
            'message' => 'Post queued for retry',
            'post' => $post,
        ]);
    }

    public function regenerate(Request $request, int $id): JsonResponse
    {
        $user = $this->requireUser($request);
        $video = $this->findAuthorizedVideo($user, $id);

        $video->update([
            'status' => 'queued',
            'error_message' => null,
            'generation_provider' => config('video_automation.provider', 'openai_sora'),
            'provider_job_id' => null,
            'provider_status' => null,
            'provider_progress' => null,
            'provider_payload' => null,
        ]);

        RenderMarketingVideo::dispatch($video->id)->onQueue('video-rendering');

        return response()->json([
            'message' => 'Video regeneration queued',
            'video' => $video,
        ], 202);
    }

    public function notifyOwner(Request $request, int $id): JsonResponse
    {
        $user = $this->requireUser($request);
        $video = $this->findAuthorizedVideo($user, $id);

        if ($video->status !== 'ready' || ! $video->video_url) {
            return response()->json([
                'message' => 'Video must be ready before WhatsApp delivery can be queued.',
            ], 422);
        }

        $force = (bool) $request->boolean('force', false);
        SendBoatVideoWhatsappJob::dispatch($video->id, $force)->onQueue('whatsapp');

        return response()->json([
            'message' => 'Owner WhatsApp delivery queued',
            'video' => $video->fresh(['posts', 'yacht.owner', 'yacht.videoSetting']),
        ], 202);
    }

    private function requireUser(Request $request): User
    {
        $user = $request->user();

        if (! $user) {
            abort(401, 'Unauthorized');
        }

        return $user;
    }

    private function findAuthorizedVideo(User $user, int $videoId): Video
    {
        $video = Video::query()
            ->with('yacht.owner')
            ->findOrFail($videoId);

        $this->authorizeVideoAccess($user, $video);

        return $video;
    }

    private function findAuthorizedPost(User $user, int $postId): VideoPost
    {
        $post = VideoPost::query()
            ->with('video.yacht.owner')
            ->findOrFail($postId);

        $this->authorizePostAccess($user, $post);

        return $post;
    }

    private function authorizeVideoAccess(User $user, Video $video): void
    {
        $yacht = $video->yacht;
        if (! $yacht) {
            abort(404, 'Yacht not found.');
        }

        $this->authorizeYachtAccess($user, $yacht);
    }

    private function authorizePostAccess(User $user, VideoPost $post): void
    {
        $video = $post->video;
        if (! $video) {
            abort(404, 'Video not found.');
        }

        $this->authorizeVideoAccess($user, $video);
    }

    private function applyVisibleVideoScope(Builder $query, User $user): Builder
    {
        if ($user->isAdmin()) {
            return $query;
        }

        return $query->whereHas('yacht', function (Builder $builder) use ($user) {
            $this->applyVisibleYachtScope($builder, $user);
        });
    }

    private function applyVisiblePostScope(Builder $query, User $user): Builder
    {
        if ($user->isAdmin()) {
            return $query;
        }

        return $query->whereHas('video.yacht', function (Builder $builder) use ($user) {
            $this->applyVisibleYachtScope($builder, $user);
        });
    }

    private function applyVisibleYachtScope(Builder $query, User $user): Builder
    {
        if ($user->isAdmin()) {
            return $query;
        }

        if ($user->isClient()) {
            return $query->where('user_id', $user->id);
        }

        if ($user->isEmployee()) {
            $locationIds = $this->locationAccess->accessibleLocationIds($user);
            if ($locationIds === []) {
                return $query->whereRaw('1 = 0');
            }

            return $query->where(function (Builder $builder) use ($locationIds) {
                $builder->whereIn('ref_harbor_id', $locationIds)
                    ->orWhereHas('owner', function (Builder $ownerQuery) use ($locationIds) {
                        $ownerQuery->whereIn('client_location_id', $locationIds);
                    });
            });
        }

        return $query->whereRaw('1 = 0');
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
