<?php

namespace App\Services;

use App\Models\Yacht;
use App\Models\VideoTemplate;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AiVideoPlannerService
{
    private const ALLOWED_TRANSITIONS = ['fade', 'dissolve', 'fadeblack', 'slideright', 'slideleft', 'crossfade'];
    private const ALLOWED_MOTIONS     = ['slow_zoom_in', 'slow_zoom_out', 'pan_left', 'pan_right', 'very_subtle_pan', 'static'];
    private const ALLOWED_SCENE_TYPES = [
        'hero_exterior', 'secondary_exterior', 'cockpit', 'deck', 'deck_cockpit',
        'saloon', 'galley', 'cabin', 'master_cabin', 'bathroom',
        'helm', 'helm_station', 'engine', 'engine_machinery',
        'detail', 'detail_finish', 'lifestyle_ambiance', 'general',
    ];

    /**
     * Generate an AI video plan for a yacht using the given template.
     * Returns the validated plan array or throws on failure.
     */
    public function generate(Yacht $yacht, VideoTemplate $template, string $variation = 'horizontal', ?string $outputType = null): array
    {
        $input   = $this->buildInput($yacht, $template, $variation, $outputType);
        $raw     = $this->callOpenAi($input);
        $validImageIds = $yacht->images->pluck('id')->all();
        $errors  = $this->validate($raw, $template, $validImageIds);

        return [
            'ai_input_json'    => $input,
            'ai_output_json'   => $raw,
            'final_plan_json'  => empty($errors) ? $raw : null,
            'validation_errors'=> $errors,
            'status'           => empty($errors) ? 'ai_generated' : 'validation_failed',
        ];
    }

    /**
     * Build the structured JSON input sent to AI.
     */
    public function buildInput(Yacht $yacht, VideoTemplate $template, string $variation, ?string $outputType = null): array
    {
        $yacht->loadMissing('images');
        $settings = $template->settings_json;
        $rules    = $template->ai_rules_json;

        // output_type: caller overrides template default
        $outputType    = $outputType ?? $settings['output_type'] ?? 'listing';
        $pacingProfile = $settings['pacing_profile'] ?? 'balanced';

        // Boat type pacing override
        $boatType = strtolower((string) ($yacht->boat_type ?? ''));
        $boatTypePacing = $rules['boat_type_pacing'] ?? [];
        foreach ($boatTypePacing as $key => $pacing) {
            if (str_contains($boatType, str_replace('_', ' ', $key)) || str_contains($boatType, $key)) {
                $pacingProfile = $pacing;
                break;
            }
        }

        // Premium price override
        $price = (float) ($yacht->price ?? $yacht->sale_price ?? 0);
        $premiumThreshold = (float) ($rules['premium_price_threshold'] ?? 300000);
        if ($price >= $premiumThreshold) {
            $pacingProfile = 'slow_cinematic';
        }

        // Reel/social overrides
        $maxScenes = ($outputType === 'reel' || $variation === 'vertical')
            ? ($rules['reel_max_scenes'] ?? $settings['max_scenes_social'] ?? 8)
            : 20;

        $images = $yacht->images->map(fn ($img) => array_filter([
            'id'            => $img->id,
            'url'           => $img->url,
            'category'      => $img->category ?? 'general',
            'image_type'    => $img->image_type,
            'quality_score' => $img->quality_score !== null ? round($img->quality_score / 100, 2) : null,
            'sort_order'    => $img->sort_order,
            'is_main'       => (bool) ($img->sort_order === 0),
        ]))->values()->all();

        return [
            'template' => [
                'name'                        => $template->slug,
                'video_type'                  => $variation,
                'output_type'                 => $outputType,
                'pacing_profile'              => $pacingProfile,
                'style_name'                  => $settings['style_name'] ?? 'luxury',
                'target_platforms'            => $settings['target_platforms'] ?? [],
                'default_image_duration'      => $settings['default_image_duration'] ?? 3.0,
                'hero_image_duration'         => $settings['hero_image_duration'] ?? 5.0,
                'feature_image_duration'      => $settings['feature_image_duration'] ?? 4.0,
                'detail_image_duration'       => $settings['detail_image_duration'] ?? 2.0,
                'min_duration_per_scene'      => $settings['min_duration_per_scene'] ?? 1.5,
                'max_duration_per_scene'      => $settings['max_duration_per_scene'] ?? 8.0,
                'preferred_transitions'       => $settings['preferred_transitions'] ?? ['fade', 'dissolve'],
                'default_transition_duration' => $settings['default_transition_duration'] ?? 0.8,
                'motion_style'                => $settings['motion_style'] ?? 'slow_zoom_in',
                'exterior_before_interior'    => $settings['exterior_before_interior'] ?? true,
                'engine_at_end'               => $settings['engine_at_end'] ?? true,
                'skip_weak_images'            => $settings['skip_weak_images'] ?? true,
                'intro_enabled'               => $settings['intro_enabled'] ?? true,
                'intro_type'                  => $settings['intro_type'] ?? 'title_card',
                'outro_enabled'               => $settings['outro_enabled'] ?? true,
                'cta_text'                    => $settings['cta_text'] ?? 'Contact NauticSecure for a viewing',
                'logo_enabled'                => $settings['logo_enabled'] ?? true,
                'whatsapp_url'                => $settings['whatsapp_url'] ?? null,
                'website_url'                 => $settings['website_url'] ?? null,
                'music_family'                => $settings['music_family'] ?? 'luxury_calm',
                'music_intensity'             => $settings['music_intensity'] ?? 'medium',
                'ducking_enabled'             => $settings['ducking_enabled'] ?? true,
                'show_title_on_hero_only'     => $settings['show_title_on_hero_only'] ?? true,
                'ai_generate_overlay_text'    => $settings['ai_generate_overlay_text'] ?? true,
                'overlay_show_price'          => $settings['overlay_show_price'] ?? true,
                'overlay_show_specs'          => $settings['overlay_show_specs'] ?? true,
                'max_scenes'                  => $maxScenes,
                'total_max_duration_sec'      => $settings['total_max_duration_sec'] ?? 90,
            ],
            'ai_rules' => [
                'ai_enabled'                  => $rules['ai_enabled'] ?? true,
                'auto_apply'                  => $rules['auto_apply'] ?? false,
                'let_ai_suppress_low_quality' => $rules['let_ai_suppress_low_quality'] ?? true,
                'let_ai_reorder'              => $rules['let_ai_reorder'] ?? true,
                'let_ai_generate_overlay_text'=> $rules['let_ai_generate_overlay_text'] ?? true,
            ],
            'rules' => $rules,
            'yacht' => array_filter([
                'id'        => $yacht->id,
                'title'     => trim((string) ($yacht->boat_name ?: ($yacht->manufacturer . ' ' . $yacht->model))),
                'price'     => $yacht->price ?? $yacht->sale_price,
                'year'      => $yacht->year,
                'length_m'  => $yacht->length_overall,
                'location'  => $yacht->location_city,
                'category'  => $yacht->boat_type,
            ]),
            'images' => $images,
        ];
    }

    /**
     * Call OpenAI and return the parsed video_plan array.
     */
    private function callOpenAi(array $input): array
    {
        $apiKey = config('services.openai.key');

        $systemPrompt = <<<PROMPT
You are an AI video planner for yacht marketing videos.
Return ONLY a valid JSON object with key "video_plan" containing exactly: intro, scenes[], outro, audio.

Required scene fields:
- image_id (integer)
- scene_type (one of: hero_exterior, secondary_exterior, deck_cockpit, saloon, galley, master_cabin, bathroom, helm_station, engine_machinery, detail_finish, lifestyle_ambiance, general)
- importance (one of: hero, feature, detail, weak)
- duration (float, seconds) — must be between template.min_duration_per_scene and template.max_duration_per_scene
- transition_in (one of: fade, dissolve, fadeblack, slideright, slideleft)
- transition_out (one of: fade, dissolve, fadeblack, slideright, slideleft)
- motion.enabled (boolean)
- motion.style (one of: slow_zoom_in, slow_zoom_out, pan_left, pan_right, very_subtle_pan, static) — only when enabled=true
- overlay.enabled (boolean)
- overlay.headline (string, short scene label) — when enabled=true
- overlay.subline (string, optional secondary text) — when enabled=true

Rules:
- output_type=listing: full video, all scenes, template.hero_image_duration / feature_image_duration / detail_image_duration
- output_type=reel: use only top template.max_scenes images (best exterior + lifestyle), durations 1.5–2.5s, fast cuts
- output_type=ad: strongest 3–5 visuals only, first scene must be hero_exterior, durations 1.5–2.0s
- Follow template.pacing_profile: slow_cinematic=longer durations+soft transitions, social_fast=shorter+faster cuts
- If template.exterior_before_interior=true: all exterior scenes before interior scenes
- If template.engine_at_end=true: engine_machinery and detail_finish scenes go last
- If template.skip_weak_images=true: exclude images with quality_score < 0.4 (importance=weak)
- If ai_rules.let_ai_reorder=true: reorder scenes for best visual flow; otherwise keep input order
- If ai_rules.let_ai_suppress_low_quality=true: drop duplicate angles, keep only best quality per scene_type
- Hero exterior: duration=template.hero_image_duration, motion.style=slow_zoom_in, overlay with yacht name
- Feature (saloon/cockpit/master_cabin): duration=template.feature_image_duration, motion.style=very_subtle_pan or static
- Detail/engine: duration=template.detail_image_duration, motion.enabled=false, overlay.enabled=false
- If template.show_title_on_hero_only=true: overlay.enabled=true only for hero scene, false for all others
- If ai_rules.let_ai_generate_overlay_text=true: write overlay.headline and overlay.subline text
- Exterior motion: slow_zoom_in or slow_zoom_out; Interior motion: very_subtle_pan or static
- lifestyle_ambiance: treat as feature, motion=slow_zoom_out
- Do not repeat same scene_type consecutively
- Total duration must not exceed template.total_max_duration_sec
- intro: use template.intro_type, include title from yacht.title, subtitle from yacht specs
- outro: include template.cta_text, show_logo=template.logo_enabled, whatsapp_url and website_url if provided
- audio: music_profile=template.music_family, music_volume based on template.music_intensity (low=0.2, medium=0.35, high=0.5), ducking=template.ducking_enabled
- Return no explanation, only valid JSON
PROMPT;

        $response = Http::withToken($apiKey)
            ->timeout(60)
            ->post('https://api.openai.com/v1/chat/completions', [
                'model'           => 'gpt-5.5',
                'response_format' => ['type' => 'json_object'],
                'messages'        => [
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user',   'content' => json_encode($input)],
                ],
            ]);

        if (!$response->successful()) {
            throw new \RuntimeException('OpenAI video planner failed: ' . $response->body());
        }

        $content = $response->json('choices.0.message.content');
        $decoded = json_decode($content, true);

        if (!isset($decoded['video_plan'])) {
            throw new \RuntimeException('AI response missing video_plan key.');
        }

        return $decoded['video_plan'];
    }

    /**
     * Validate AI output against template rules.
     * Returns array of error strings (empty = valid).
     */
    public function validate(array $plan, VideoTemplate $template, array $validImageIds = []): array
    {
        $errors   = [];
        $settings = $template->settings_json;
        $maxDur   = $settings['total_max_duration_sec'] ?? 90;
        $minScene = $settings['min_duration_per_scene'] ?? $settings['min_scene_duration_sec'] ?? 1.5;
        $maxScene = $settings['max_duration_per_scene'] ?? $settings['max_scene_duration_sec'] ?? 8.0;
        $allowed_t = $settings['preferred_transitions'] ?? self::ALLOWED_TRANSITIONS;

        if (empty($plan['scenes'])) {
            $errors[] = 'scenes array is empty or missing';
            return $errors;
        }

        if (!isset($plan['intro'])) $errors[] = 'missing intro object';
        if (!isset($plan['outro'])) $errors[] = 'missing outro object';
        if (!isset($plan['audio'])) $errors[] = 'missing audio object';

        $total = ($plan['intro']['duration'] ?? 0)
               + ($plan['outro']['duration'] ?? 0);

        foreach ($plan['scenes'] as $i => $scene) {
            // Required fields
            foreach (['image_id', 'scene_type', 'importance', 'duration', 'transition_in', 'transition_out'] as $req) {
                if (!isset($scene[$req])) {
                    $errors[] = "scene {$i}: missing required field '{$req}'";
                }
            }

            // image_id must belong to this yacht
            $imageId = $scene['image_id'] ?? null;
            if ($imageId !== null && !empty($validImageIds) && !in_array($imageId, $validImageIds, false)) {
                $errors[] = "scene {$i}: image_id {$imageId} does not belong to this yacht";
            }

            $dur = (float) ($scene['duration'] ?? 0);
            $total += $dur;

            if ($dur < $minScene || $dur > $maxScene) {
                $errors[] = "scene {$i}: duration {$dur}s out of range [{$minScene},{$maxScene}]";
            }

            $ti = $scene['transition_in'] ?? '';
            $to = $scene['transition_out'] ?? '';
            if ($ti && !in_array($ti, self::ALLOWED_TRANSITIONS, true)) {
                $errors[] = "scene {$i}: invalid transition_in '{$ti}'";
            }
            if ($to && !in_array($to, self::ALLOWED_TRANSITIONS, true)) {
                $errors[] = "scene {$i}: invalid transition_out '{$to}'";
            }

            $motion = $scene['motion']['style'] ?? '';
            if ($motion && !in_array($motion, self::ALLOWED_MOTIONS, true)) {
                $errors[] = "scene {$i}: invalid motion style '{$motion}'";
            }

            $sceneType = $scene['scene_type'] ?? '';
            if ($sceneType && !in_array($sceneType, self::ALLOWED_SCENE_TYPES, true)) {
                $errors[] = "scene {$i}: unknown scene_type '{$sceneType}'";
            }

            $subline = $scene['overlay']['subline'] ?? null;
            if ($subline !== null && !is_string($subline)) {
                $errors[] = "scene {$i}: overlay.subline must be a string";
            }
        }

        if ($total > $maxDur + 2) {
            $errors[] = "total duration {$total}s exceeds max {$maxDur}s";
        }

        return $errors;
    }
}
