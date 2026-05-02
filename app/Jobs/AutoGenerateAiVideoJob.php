<?php

namespace App\Jobs;

use App\Models\Video;
use App\Models\VideoPlan;
use App\Models\VideoTemplate;
use App\Models\Yacht;
use App\Services\AiVideoPlannerService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class AutoGenerateAiVideoJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 5;
    public int $timeout = 120;

    public function __construct(private int $yachtId)
    {
        $this->onQueue('video-rendering');
    }

    public function handle(AiVideoPlannerService $planner): void
    {
        $tag = "AutoGenerateAiVideoJob[yacht:{$this->yachtId}]";
        Log::info("{$tag} started");

        $yacht = Yacht::with('images')->find($this->yachtId);

        if (!$yacht) {
            Log::warning("{$tag} skipped — yacht not found");
            return;
        }

        $status = strtolower((string) $yacht->status);
        if (in_array($status, ['draft', 'withdrawn', ''], true)) {
            Log::info("{$tag} skipped — yacht status is '{$status}'");
            return;
        }

        $approvedImages = $yacht->images->filter(
            fn($img) => in_array($img->status, ['approved', 'ready_for_review'], true)
        );

        if ($approvedImages->isEmpty()) {
            $attempt = $this->attempts();
            if ($attempt < $this->tries) {
                $delay = $attempt * 60;
                Log::info("{$tag} no approved images yet (attempt {$attempt}) — retrying in {$delay}s");
                $this->release($delay);
                return;
            }
            Log::warning("{$tag} skipped — no approved images after {$attempt} attempts");
            return;
        }

        $existingPlan = VideoPlan::where('yacht_id', $this->yachtId)
            ->whereNotIn('status', ['failed', 'validation_failed'])
            ->exists();

        if ($existingPlan) {
            Log::info("{$tag} skipped — non-failed VideoPlan already exists");
            return;
        }

        $existingVideo = Video::where('yacht_id', $this->yachtId)
            ->whereIn('status', ['queued', 'processing', 'ready'])
            ->exists();

        if ($existingVideo) {
            Log::info("{$tag} skipped — Video record already exists");
            return;
        }

        $template = $this->selectTemplate($yacht);

        if (!$template) {
            Log::warning("{$tag} skipped — no active VideoTemplate found");
            return;
        }

        Log::info("{$tag} selected template '{$template->name}' (id:{$template->id})");

        try {
            $result = $planner->generate($yacht, $template, 'horizontal', 'listing');
        } catch (\Throwable $e) {
            Log::error("{$tag} AI plan generation failed — " . $e->getMessage());
            $this->fail($e);
            return;
        }

        if (!empty($result['validation_errors']) || $result['status'] !== 'ai_generated') {
            Log::warning("{$tag} plan validation failed — " . json_encode($result['validation_errors']));
            return;
        }

        $plan = VideoPlan::create([
            'yacht_id'          => $yacht->id,
            'template_id'       => $template->id,
            'variation'         => 'horizontal',
            'status'            => 'approved',
            'ai_input_json'     => $result['ai_input_json'],
            'ai_output_json'    => $result['ai_output_json'],
            'final_plan_json'   => $result['final_plan_json'],
            'validation_errors' => $result['validation_errors'],
            'approved_at'       => now(),
        ]);

        Log::info("{$tag} plan #{$plan->id} generated and auto-approved (" . count($plan->final_plan_json['scenes'] ?? []) . " scenes)");

        RenderFromPlan::dispatch($plan->id);

        Log::info("{$tag} RenderFromPlan dispatched for plan #{$plan->id}");
    }

    private function selectTemplate(Yacht $yacht): ?VideoTemplate
    {
        $templates = VideoTemplate::where('is_active', true)->get();

        if ($templates->isEmpty()) {
            return null;
        }

        $price    = (float) ($yacht->price ?? $yacht->sale_price ?? 0);
        $boatType = strtolower((string) ($yacht->boat_type ?? ''));

        $isLuxury = $price >= 300000
            || str_contains($boatType, 'sail')
            || str_contains($boatType, 'yacht');

        $isSporty = str_contains($boatType, 'sport')
            || str_contains($boatType, 'speed')
            || str_contains($boatType, 'motor');

        $targetStyle = match (true) {
            $isLuxury => 'luxury',
            $isSporty => 'sporty',
            default   => 'modern',
        };

        $match = $templates->first(
            fn($t) => ($t->settings_json['style_name'] ?? '') === $targetStyle
        );

        if ($match) return $match;

        $default = $templates->firstWhere('is_default', true);
        if ($default) return $default;

        return $templates->first();
    }

    public function failed(\Throwable $exception): void
    {
        Log::error("AutoGenerateAiVideoJob[yacht:{$this->yachtId}] job failed — " . $exception->getMessage());
    }
}
