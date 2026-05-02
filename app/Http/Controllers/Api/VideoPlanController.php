<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\RenderFromPlan;
use App\Models\VideoPlan;
use App\Models\VideoTemplate;
use App\Models\Yacht;
use App\Services\AiVideoPlannerService;
use App\Services\FFmpegService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class VideoPlanController extends Controller
{
    public function __construct(private AiVideoPlannerService $planner) {}

    /** GET /api/video-templates */
    public function templates(): JsonResponse
    {
        return response()->json(VideoTemplate::where('is_active', true)->get());
    }

    /** POST /api/video-templates */
    public function storeTemplate(Request $request): JsonResponse
    {
        $this->authorizeAdmin($request);

        $data = $request->validate([
            'name'          => 'required|string|max:255',
            'slug'          => 'required|string|unique:video_templates,slug',
            'video_type'    => 'in:horizontal,vertical,square',
            'is_default'    => 'boolean',
            'settings_json' => 'required|array',
            'settings_json.pacing_profile'           => 'required|in:slow_cinematic,balanced,social_fast',
            'settings_json.style_name'               => 'required|in:luxury,sporty,social,broker',
            'settings_json.target_platforms'         => 'sometimes|array',
            'settings_json.hero_image_duration'      => 'required|numeric|min:1|max:10',
            'settings_json.feature_image_duration'   => 'required|numeric|min:1|max:10',
            'settings_json.detail_image_duration'    => 'required|numeric|min:1|max:10',
            'settings_json.min_duration_per_scene'   => 'required|numeric|min:0.5|max:5',
            'settings_json.max_duration_per_scene'   => 'required|numeric|min:2|max:15',
            'settings_json.exterior_before_interior' => 'required|boolean',
            'settings_json.engine_at_end'            => 'required|boolean',
            'settings_json.skip_weak_images'         => 'required|boolean',
            'settings_json.intro_enabled'            => 'required|boolean',
            'settings_json.intro_type'               => 'sometimes|in:title_card,logo_reveal,fade_in',
            'settings_json.outro_enabled'            => 'required|boolean',
            'settings_json.cta_text'                 => 'required|string|max:255',
            'settings_json.logo_enabled'             => 'required|boolean',
            'settings_json.whatsapp_url'             => 'sometimes|nullable|url',
            'settings_json.website_url'              => 'sometimes|nullable|url',
            'settings_json.music_family'             => 'sometimes|in:luxury_calm,upbeat,cinematic,minimal',
            'settings_json.music_intensity'          => 'sometimes|in:low,medium,high',
            'settings_json.ducking_enabled'          => 'sometimes|boolean',
            'settings_json.show_title_on_hero_only'  => 'required|boolean',
            'settings_json.overlay_show_price'       => 'required|boolean',
            'settings_json.overlay_show_specs'       => 'required|boolean',
            'ai_rules_json'                          => 'required|array',
            'ai_rules_json.ai_enabled'               => 'required|boolean',
            'ai_rules_json.auto_apply'               => 'required|boolean',
            'ai_rules_json.let_ai_suppress_low_quality' => 'required|boolean',
            'ai_rules_json.let_ai_reorder'           => 'required|boolean',
            'ai_rules_json.let_ai_generate_overlay_text' => 'required|boolean',
        ]);

        if (!empty($data['is_default'])) {
            VideoTemplate::where('is_default', true)->update(['is_default' => false]);
        }

        $template = VideoTemplate::create($data + ['created_by' => $request->user()->id]);
        return response()->json($template, 201);
    }

    /** PUT /api/video-templates/{id} */
    public function updateTemplate(Request $request, int $id): JsonResponse
    {
        $this->authorizeAdmin($request);
        $template = VideoTemplate::findOrFail($id);

        $data = $request->validate([
            'name'          => 'sometimes|string|max:255',
            'is_active'     => 'sometimes|boolean',
            'is_default'    => 'sometimes|boolean',
            'settings_json' => 'sometimes|array',
            'settings_json.pacing_profile'           => 'sometimes|in:slow_cinematic,balanced,social_fast',
            'settings_json.style_name'               => 'sometimes|in:luxury,sporty,social,broker',
            'settings_json.target_platforms'         => 'sometimes|array',
            'settings_json.hero_image_duration'      => 'sometimes|numeric|min:1|max:10',
            'settings_json.feature_image_duration'   => 'sometimes|numeric|min:1|max:10',
            'settings_json.detail_image_duration'    => 'sometimes|numeric|min:1|max:10',
            'settings_json.min_duration_per_scene'   => 'sometimes|numeric|min:0.5|max:5',
            'settings_json.max_duration_per_scene'   => 'sometimes|numeric|min:2|max:15',
            'settings_json.exterior_before_interior' => 'sometimes|boolean',
            'settings_json.engine_at_end'            => 'sometimes|boolean',
            'settings_json.skip_weak_images'         => 'sometimes|boolean',
            'settings_json.intro_enabled'            => 'sometimes|boolean',
            'settings_json.intro_type'               => 'sometimes|in:title_card,logo_reveal,fade_in',
            'settings_json.outro_enabled'            => 'sometimes|boolean',
            'settings_json.cta_text'                 => 'sometimes|string|max:255',
            'settings_json.logo_enabled'             => 'sometimes|boolean',
            'settings_json.whatsapp_url'             => 'sometimes|nullable|url',
            'settings_json.website_url'              => 'sometimes|nullable|url',
            'settings_json.music_family'             => 'sometimes|in:luxury_calm,upbeat,cinematic,minimal',
            'settings_json.music_intensity'          => 'sometimes|in:low,medium,high',
            'settings_json.ducking_enabled'          => 'sometimes|boolean',
            'settings_json.show_title_on_hero_only'  => 'sometimes|boolean',
            'settings_json.overlay_show_price'       => 'sometimes|boolean',
            'settings_json.overlay_show_specs'       => 'sometimes|boolean',
            'ai_rules_json'                          => 'sometimes|array',
            'ai_rules_json.ai_enabled'               => 'sometimes|boolean',
            'ai_rules_json.auto_apply'               => 'sometimes|boolean',
            'ai_rules_json.let_ai_suppress_low_quality' => 'sometimes|boolean',
            'ai_rules_json.let_ai_reorder'           => 'sometimes|boolean',
            'ai_rules_json.let_ai_generate_overlay_text' => 'sometimes|boolean',
        ]);

        if (!empty($data['is_default'])) {
            VideoTemplate::where('is_default', true)->update(['is_default' => false]);
        }

        $template->update($data);
        return response()->json($template);
    }

    /** GET /api/yachts/{id}/video-plans */
    public function index(int $yachtId): JsonResponse
    {
        return response()->json(
            VideoPlan::where('yacht_id', $yachtId)->with('template')->latest()->get()
        );
    }

    /** POST /api/yachts/{id}/video-plans — generate AI plan */
    public function generate(Request $request, int $yachtId): JsonResponse
    {
        $this->authorizeAdmin($request);

        $yacht = Yacht::findOrFail($yachtId);
        $data  = $request->validate([
            'template_id' => 'required|exists:video_templates,id',
            'variation'   => 'in:horizontal,vertical,square,teaser',
            'output_type' => 'in:listing,reel,ad',
        ]);

        $template   = VideoTemplate::findOrFail($data['template_id']);
        $variation  = $data['variation'] ?? 'horizontal';
        $outputType = $data['output_type'] ?? null;

        $yacht->loadMissing('images');
        if ($yacht->images->isEmpty()) {
            return response()->json(['error' => 'This yacht has no images. Please upload images before generating a video plan.'], 422);
        }

        try {
            $result = $this->planner->generate($yacht, $template, $variation, $outputType);
        } catch (\Throwable $e) {
            Log::error('AI video plan generation failed: ' . $e->getMessage());
            return response()->json(['error' => 'AI planning failed: ' . $e->getMessage()], 500);
        }

        $plan = VideoPlan::create([
            'yacht_id'          => $yacht->id,
            'template_id'       => $template->id,
            'variation'         => $variation,
            'status'            => $result['status'],
            'ai_input_json'     => $result['ai_input_json'],
            'ai_output_json'    => $result['ai_output_json'],
            'final_plan_json'   => $result['final_plan_json'],
            'validation_errors' => $result['validation_errors'],
            'created_by'        => $request->user()->id,
        ]);

        return response()->json($plan, 201);
    }

    /** GET /api/video-plans/{id} */
    public function show(int $id): JsonResponse
    {
        $plan = VideoPlan::with('template', 'yacht')->findOrFail($id);

        // Defensive: rendered plans should not keep stale failure output.
        if ($plan->status === 'rendered' && !empty($plan->validation_errors)) {
            $plan->validation_errors = [];
        }

        return response()->json($plan);
    }

    /** GET /api/video-plans/{id}/stream */
    public function streamRenderedPlan(int $id): \Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        $plan = VideoPlan::findOrFail($id);
        abort_unless($plan->status === 'rendered' && is_string($plan->render_output_url) && $plan->render_output_url !== '', 404);

        // Expected URL format: {APP_URL}/storage/{storagePath}
        $urlPath = (string) parse_url($plan->render_output_url, PHP_URL_PATH);
        $needle  = '/storage/';
        $pos     = strpos($urlPath, $needle);
        abort_unless($pos !== false, 404);

        $storagePath = ltrim(substr($urlPath, $pos + strlen($needle)), '/');
        $fullPath    = Storage::disk('public')->path($storagePath);
        abort_unless(file_exists($fullPath), 404);

        // BinaryFileResponse supports byte-range requests (seeking) out of the box.
        return response()->file($fullPath, ['Content-Type' => 'video/mp4']);
    }

    /** PATCH /api/video-plans/{id} — admin edits final_plan_json */
    public function update(Request $request, int $id): JsonResponse
    {
        $this->authorizeAdmin($request);
        $plan = VideoPlan::findOrFail($id);

        $data = $request->validate(['final_plan_json' => 'required|array']);

        $plan->loadMissing('yacht.images');
        $validImageIds = $plan->yacht->images->pluck('id')->all();
        $errors = $this->planner->validate($data['final_plan_json'], $plan->template, $validImageIds);
        if (!empty($errors)) {
            return response()->json(['errors' => $errors], 422);
        }

        $plan->update(['final_plan_json' => $data['final_plan_json']]);
        return response()->json($plan);
    }

    /** POST /api/video-plans/{id}/approve */
    public function approve(Request $request, int $id): JsonResponse
    {
        $this->authorizeAdmin($request);
        $plan = VideoPlan::findOrFail($id);

        if (!$plan->final_plan_json) {
            return response()->json(['error' => 'No valid plan to approve'], 422);
        }

        $plan->update([
            'status'      => 'approved',
            'approved_by' => $request->user()->id,
            'approved_at' => now(),
        ]);

        return response()->json($plan);
    }

    /** POST /api/video-plans/{id}/render */
    public function render(Request $request, int $id): JsonResponse
    {
        $this->authorizeAdmin($request);
        $plan = VideoPlan::findOrFail($id);

        if ($plan->status !== 'approved') {
            return response()->json(['error' => 'Plan must be approved before rendering'], 422);
        }

        $plan->update(['status' => 'rendering', 'render_started_at' => now()]);
        RenderFromPlan::dispatch($plan->id);

        return response()->json(['message' => 'Render job queued', 'plan_id' => $plan->id], 202);
    }

    /** POST /api/video-plans/{id}/preview */
    public function preview(Request $request, int $id): JsonResponse
    {
        $this->authorizeAdmin($request);
        $plan = VideoPlan::with('yacht.images')->findOrFail($id);

        $scenes = $plan->final_plan_json['scenes'] ?? $plan->ai_output_json['scenes'] ?? [];
        if (empty($scenes)) {
            return response()->json(['error' => 'No scenes available to preview'], 422);
        }

        $imageMap = $plan->yacht->images->keyBy('id');
        $imgUrl   = null;
        foreach ($scenes as $scene) {
            if (($scene['importance'] ?? '') === 'weak') continue;
            $img = $imageMap[$scene['image_id'] ?? null] ?? null;
            if ($img) { $imgUrl = $img->url; break; }
        }

        if (!$imgUrl) {
            return response()->json(['error' => 'No usable image found in plan scenes'], 422);
        }

        $ffmpeg  = new FFmpegService();
        $srcPath = Storage::disk('public')->path($imgUrl);

        if (!file_exists($srcPath)) {
            $fallbackUrl = rtrim(config('app.url'), '/') . '/storage/' . $imgUrl;
            try {
                $response = \Illuminate\Support\Facades\Http::timeout(10)->get($fallbackUrl);
                if ($response->successful()) {
                    $tmp = tempnam(sys_get_temp_dir(), 'preview_img_') . '.jpg';
                    file_put_contents($tmp, $response->body());
                    $srcPath = $tmp;
                }
            } catch (\Throwable) {}
        }

        if (!file_exists($srcPath)) {
            return response()->json(['error' => 'Source image not accessible'], 422);
        }

        $thumbPath     = "videos/previews/plan-{$plan->id}-preview.jpg";
        $thumbFullPath = Storage::disk('public')->path($thumbPath);
        $dir = dirname($thumbFullPath);
        if (!is_dir($dir)) mkdir($dir, 0777, true);

        $tmpClip = tempnam(sys_get_temp_dir(), 'plan_preview_') . '.mp4';
        try {
            $ffmpeg->renderKenBurnsClip($srcPath, $tmpClip, 1.0);
            $ffmpeg->createThumbnail($tmpClip, $thumbFullPath, 0);
        } finally {
            @unlink($tmpClip);
        }

        return response()->json(['preview_url' => Storage::disk('public')->url($thumbPath)]);
    }

    /** POST /api/video-plans/{id}/retry */
    public function retry(Request $request, int $id): JsonResponse
    {
        $this->authorizeAdmin($request);
        $plan = VideoPlan::findOrFail($id);

        if (!in_array($plan->status, ['failed', 'rendering'], true)) {
            return response()->json(['error' => 'Only failed or stuck rendering plans can be retried'], 422);
        }

        if (!$plan->final_plan_json) {
            return response()->json(['error' => 'No valid plan to render'], 422);
        }

        Cache::forget('laravel_unique_job:' . RenderFromPlan::class . ':' . $plan->id);

        $plan->update(['status' => 'rendering', 'render_started_at' => now()]);
        RenderFromPlan::dispatch($plan->id);

        return response()->json(['message' => 'Render job re-queued', 'plan_id' => $plan->id], 202);
    }

    /** DELETE /api/video-plans/{id} */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $this->authorizeAdmin($request);
        VideoPlan::findOrFail($id)->delete();
        return response()->json(null, 204);
    }

    /** GET /api/video/music-tracks */
    public function musicTracks(Request $request): JsonResponse
    {
        $dir = resource_path('music');
        if (!is_dir($dir)) return response()->json([]);

        $tracks = [];
        foreach (glob("{$dir}/*.mp3") as $file) {
            $slug     = pathinfo($file, PATHINFO_FILENAME);
            $tracks[] = [
                'slug'    => $slug,
                'name'    => ucwords(str_replace('_', ' ', $slug)),
                'size_kb' => round(filesize($file) / 1024),
                'url'     => $request->getSchemeAndHttpHost() . '/api/video/music-tracks/' . $slug . '/stream',
            ];
        }
        return response()->json($tracks);
    }

    /** POST /api/video/music-tracks */
    public function uploadMusicTrack(Request $request): JsonResponse
    {
        $this->authorizeAdmin($request);

        $request->validate([
            'file' => 'required|file|mimes:mpga,mp3|max:51200',
        ]);

        $dir = resource_path('music');
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $slug = preg_replace('/[^a-z0-9_]/', '_', strtolower(
            pathinfo($request->file('file')->getClientOriginalName(), PATHINFO_FILENAME)
        ));

        $request->file('file')->move($dir, "{$slug}.mp3");

        return response()->json([
            'slug' => $slug,
            'name' => ucwords(str_replace('_', ' ', $slug)),
        ], 201);
    }

    /** DELETE /api/video/music-tracks/{slug} */
    public function deleteMusicTrack(Request $request, string $slug): JsonResponse
    {
        $this->authorizeAdmin($request);

        $path = resource_path("music/{$slug}.mp3");
        if (! file_exists($path)) {
            return response()->json(['error' => 'Track not found'], 404);
        }

        unlink($path);

        return response()->json(null, 204);
    }

    /** GET /api/video/music-tracks/{slug}/stream */
    public function streamMusicTrack(string $slug): \Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        $path = resource_path("music/{$slug}.mp3");
        abort_unless(file_exists($path), 404);
        return response()->file($path, ['Content-Type' => 'audio/mpeg']);
    }

    private function authorizeAdmin(Request $request): void
    {
        if (!in_array($request->user()?->role, ['admin', 'Admin', 'Partner'], true)) {
            abort(403);
        }
    }
}
