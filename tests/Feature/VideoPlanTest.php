<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\VideoTemplate;
use App\Models\VideoPlan;
use App\Models\Yacht;
use App\Jobs\RenderFromPlan;
use App\Jobs\AutoGenerateAiVideoJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class VideoPlanTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private VideoTemplate $template;
    private string $adminToken;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->adminToken = $this->admin->createToken('test')->plainTextToken;

        $this->template = VideoTemplate::create([
            'name'       => 'Premium Horizontal',
            'slug'       => 'premium-horizontal',
            'video_type' => 'horizontal',
            'is_active'  => true,
            'is_default' => true,
            'settings_json' => [
                'pacing_profile'           => 'slow_cinematic',
                'style_name'               => 'luxury',
                'hero_image_duration'      => 5.0,
                'feature_image_duration'   => 4.0,
                'detail_image_duration'    => 2.0,
                'min_duration_per_scene'   => 1.5,
                'max_duration_per_scene'   => 8.0,
                'exterior_before_interior' => true,
                'engine_at_end'            => true,
                'skip_weak_images'         => true,
                'intro_enabled'            => true,
                'intro_type'               => 'title_card',
                'outro_enabled'            => true,
                'cta_text'                 => 'View full specs on Schepenkring.nl',
                'logo_enabled'             => true,
                'show_title_on_hero_only'  => true,
                'overlay_show_price'       => true,
                'overlay_show_specs'       => true,
                'music_family'             => 'luxury_calm',
                'music_intensity'          => 'medium',
                'ducking_enabled'          => true,
                'total_max_duration_sec'   => 90,
                'default_transition_duration' => 0.8,
            ],
            'ai_rules_json' => [
                'ai_enabled'                   => true,
                'auto_apply'                   => false,
                'let_ai_suppress_low_quality'  => true,
                'let_ai_reorder'               => true,
                'let_ai_generate_overlay_text' => true,
            ],
            'created_by' => null,
        ]);
    }

    private function asAdmin(): array
    {
        return ['Authorization' => 'Bearer ' . $this->adminToken];
    }

    private function createYacht(): Yacht
    {
        return Yacht::create([
            'boat_name'    => 'Test Yacht',
            'manufacturer' => 'Test',
            'model'        => 'Model X',
            'status'       => 'active',
        ]);
    }

    public function test_get_video_templates_returns_active_templates(): void
    {
        $this->getJson('/api/video-templates', $this->asAdmin())
            ->assertOk()
            ->assertJsonFragment(['slug' => 'premium-horizontal']);
    }

    public function test_store_video_template_requires_admin(): void
    {
        $user  = User::factory()->create(['role' => 'client']);
        $token = $user->createToken('test')->plainTextToken;

        $this->postJson('/api/video-templates', [], ['Authorization' => 'Bearer ' . $token])
            ->assertForbidden();
    }

    public function test_store_video_template_validates_required_fields(): void
    {
        $this->postJson('/api/video-templates', ['name' => 'Test'], $this->asAdmin())
            ->assertUnprocessable();
    }

    public function test_update_video_template(): void
    {
        $this->putJson("/api/video-templates/{$this->template->id}", [
            'settings_json' => ['cta_text' => 'Updated CTA'],
        ], $this->asAdmin())->assertOk();

        $this->assertDatabaseHas('video_templates', ['id' => $this->template->id]);
    }

    public function test_get_video_plans_for_yacht(): void
    {
        $yacht = $this->createYacht();

        $this->getJson("/api/yachts/{$yacht->id}/video-plans", $this->asAdmin())
            ->assertOk()
            ->assertJsonIsArray();
    }

    public function test_generate_video_plan_requires_images(): void
    {
        $yacht = $this->createYacht();

        $this->postJson("/api/yachts/{$yacht->id}/video-plans", [
            'template_id' => $this->template->id,
        ], $this->asAdmin())
            ->assertUnprocessable()
            ->assertJsonFragment(['error' => 'This yacht has no images. Please upload images before generating a video plan.']);
    }

    public function test_approve_video_plan(): void
    {
        $yacht = $this->createYacht();
        $plan  = VideoPlan::create([
            'yacht_id'        => $yacht->id,
            'template_id'     => $this->template->id,
            'variation'       => 'horizontal',
            'status'          => 'ai_generated',
            'final_plan_json' => ['scenes' => [], 'intro' => [], 'outro' => [], 'audio' => []],
        ]);

        $this->postJson("/api/video-plans/{$plan->id}/approve", [], $this->asAdmin())
            ->assertOk()
            ->assertJsonFragment(['status' => 'approved']);
    }

    public function test_render_requires_approved_status(): void
    {
        $yacht = $this->createYacht();
        $plan  = VideoPlan::create([
            'yacht_id'    => $yacht->id,
            'template_id' => $this->template->id,
            'variation'   => 'horizontal',
            'status'      => 'ai_generated',
        ]);

        $this->postJson("/api/video-plans/{$plan->id}/render", [], $this->asAdmin())
            ->assertUnprocessable();
    }

    public function test_render_dispatches_job_when_approved(): void
    {
        Queue::fake();

        $yacht = $this->createYacht();
        $plan  = VideoPlan::create([
            'yacht_id'        => $yacht->id,
            'template_id'     => $this->template->id,
            'variation'       => 'horizontal',
            'status'          => 'approved',
            'final_plan_json' => ['scenes' => [], 'intro' => [], 'outro' => [], 'audio' => []],
        ]);

        $this->postJson("/api/video-plans/{$plan->id}/render", [], $this->asAdmin())
            ->assertAccepted();

        Queue::assertPushed(RenderFromPlan::class);
    }

    public function test_render_failure_is_stored_as_human_and_technical_messages(): void
    {
        $yacht = $this->createYacht();
        $plan  = VideoPlan::create([
            'yacht_id'        => $yacht->id,
            'template_id'     => $this->template->id,
            'variation'       => 'horizontal',
            'status'          => 'rendering',
            'final_plan_json' => ['scenes' => [], 'intro' => [], 'outro' => [], 'audio' => []],
        ]);

        $job = new RenderFromPlan($plan->id);
        $job->failed(new \RuntimeException("The command 'ffmpeg' ... failed. Exit Code: 255"));

        $plan->refresh();

        $this->assertSame('failed', $plan->status);
        $this->assertIsArray($plan->validation_errors);
        $this->assertCount(2, $plan->validation_errors);
        $this->assertStringContainsString('FFmpeg', $plan->validation_errors[0]);
        $this->assertStringContainsString('Technical details:', $plan->validation_errors[1]);
        $this->assertStringContainsString('ffmpeg', strtolower($plan->validation_errors[1]));
    }

    public function test_failed_hook_does_not_override_already_rendered_plan(): void
    {
        $yacht = $this->createYacht();
        $plan  = VideoPlan::create([
            'yacht_id'          => $yacht->id,
            'template_id'       => $this->template->id,
            'variation'         => 'horizontal',
            'status'            => 'rendered',
            'render_output_url' => 'http://localhost:8000/storage/videos/plans/plan-1.mp4',
            'validation_errors' => ['Old error that should not show'],
            'final_plan_json'   => ['scenes' => [], 'intro' => [], 'outro' => [], 'audio' => []],
        ]);

        $job = new RenderFromPlan($plan->id);
        $job->failed(new \RuntimeException("SIGTERM: worker stopped"));

        $plan->refresh();

        $this->assertSame('rendered', $plan->status);
        $this->assertSame('http://localhost:8000/storage/videos/plans/plan-1.mp4', $plan->render_output_url);
        $this->assertSame([], $plan->validation_errors);
    }

    public function test_show_hides_stale_validation_errors_when_rendered(): void
    {
        $yacht = $this->createYacht();
        $plan  = VideoPlan::create([
            'yacht_id'          => $yacht->id,
            'template_id'       => $this->template->id,
            'variation'         => 'horizontal',
            'status'            => 'rendered',
            'render_output_url' => 'http://localhost:8000/storage/videos/plans/plan-x.mp4',
            'validation_errors' => ['Old failure'],
        ]);

        $this->getJson("/api/video-plans/{$plan->id}", $this->asAdmin())
            ->assertOk()
            ->assertJsonPath('status', 'rendered')
            ->assertJsonPath('validation_errors', []);
    }

    public function test_stream_rendered_plan_returns_video_file(): void
    {
        $yacht = $this->createYacht();
        $plan  = VideoPlan::create([
            'yacht_id'          => $yacht->id,
            'template_id'       => $this->template->id,
            'variation'         => 'horizontal',
            'status'            => 'rendered',
            'render_output_url' => 'http://localhost:8000/storage/videos/plans/plan-x.mp4',
        ]);

        // Fake the file existing on the public disk
        $path = 'videos/plans/plan-x.mp4';
        \Illuminate\Support\Facades\Storage::disk('public')->put($path, 'fake');

        $this->get("/api/video-plans/{$plan->id}/stream", $this->asAdmin())
            ->assertOk()
            ->assertHeader('content-type', 'video/mp4');
    }

    public function test_delete_video_plan(): void
    {
        $yacht = $this->createYacht();
        $plan  = VideoPlan::create([
            'yacht_id'    => $yacht->id,
            'template_id' => $this->template->id,
            'variation'   => 'horizontal',
            'status'      => 'draft',
        ]);

        $this->deleteJson("/api/video-plans/{$plan->id}", [], $this->asAdmin())
            ->assertNoContent();

        $this->assertDatabaseMissing('video_plans', ['id' => $plan->id]);
    }

    public function test_auto_generate_ai_video_job_skips_yacht_without_approved_images(): void
    {
        Queue::fake();

        $yacht = $this->createYacht();

        $job = new AutoGenerateAiVideoJob($yacht->id);
        $job->handle(app(\App\Services\AiVideoPlannerService::class));

        Queue::assertNotPushed(RenderFromPlan::class);
    }

    public function test_boat_status_activated_event_dispatches_auto_generate_job(): void
    {
        Queue::fake();

        $yacht = $this->createYacht();

        event(new \App\Events\BoatStatusActivated($yacht));

        Queue::assertPushed(AutoGenerateAiVideoJob::class);
    }

    public function test_music_tracks_endpoint_returns_list(): void
    {
        $this->getJson('/api/video/music-tracks', $this->asAdmin())
            ->assertOk()
            ->assertJsonIsArray();
    }

    public function test_yacht_video_settings_can_be_read_and_updated(): void
    {
        $yacht = $this->createYacht();

        $this->getJson("/api/yachts/{$yacht->id}/video-settings", $this->asAdmin())
            ->assertOk()
            ->assertJsonPath('settings.yacht_id', $yacht->id)
            ->assertJsonPath('image_count', 0);

        $this->putJson("/api/yachts/{$yacht->id}/video-settings", [
            'auto_publish_social' => true,
            'platforms' => ['instagram', 'facebook'],
            'video_crop_format' => '9:16',
            'auto_generate_caption' => true,
        ], $this->asAdmin())
            ->assertOk()
            ->assertJsonPath('auto_publish_social', true)
            ->assertJsonPath('video_crop_format', '9:16');

        $this->assertDatabaseHas('boat_video_settings', [
            'yacht_id' => $yacht->id,
            'auto_publish_social' => true,
            'video_crop_format' => '9:16',
        ]);
    }

    public function test_admin_can_upload_and_delete_music_track(): void
    {
        $path = resource_path('music/test_upload_track.mp3');
        @unlink($path);

        try {
            $this->post('/api/video/music-tracks', [
                'file' => UploadedFile::fake()->create('test upload track.mp3', 1, 'audio/mpeg'),
            ], $this->asAdmin())
                ->assertCreated()
                ->assertJsonPath('slug', 'test_upload_track');

            $this->assertFileExists($path);

            $this->deleteJson('/api/video/music-tracks/test_upload_track', [], $this->asAdmin())
                ->assertNoContent();

            $this->assertFileDoesNotExist($path);
        } finally {
            @unlink($path);
        }
    }
}
