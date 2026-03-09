<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Yacht;
use App\Models\YachtDraft;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class YachtDraftApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_create_and_patch_yacht_draft(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $create = $this->postJson('/api/yacht-drafts', [
            'draft_id' => 'draft-create-001',
            'wizard_step' => 1,
            'payload_json' => [
                'step1' => ['boatHint' => 'Initial hint'],
            ],
            'ui_state_json' => [
                'currentStep' => 1,
            ],
        ]);

        $create->assertStatus(201)
            ->assertJsonPath('draft_id', 'draft-create-001')
            ->assertJsonPath('version', 1)
            ->assertJsonPath('payload_json.step1.boatHint', 'Initial hint');

        $patch = $this->patchJson('/api/yacht-drafts/draft-create-001', [
            'version' => 1,
            'wizard_step' => 2,
            'payload_patch' => [
                'step2' => ['selectedYacht' => ['boat_name' => 'Test Boat']],
            ],
            'ui_state_patch' => [
                'currentStep' => 2,
            ],
        ]);

        $patch->assertStatus(200)
            ->assertJsonPath('version', 2)
            ->assertJsonPath('wizard_step', 2)
            ->assertJsonPath('payload_json.step2.selectedYacht.boat_name', 'Test Boat')
            ->assertJsonPath('ui_state_json.currentStep', 2);

        $this->assertDatabaseHas('yacht_drafts', [
            'user_id' => $user->id,
            'draft_id' => 'draft-create-001',
            'wizard_step' => 2,
            'version' => 2,
        ]);
    }

    public function test_patch_returns_conflict_on_version_mismatch(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        YachtDraft::create([
            'user_id' => $user->id,
            'draft_id' => 'draft-conflict-001',
            'version' => 5,
            'wizard_step' => 1,
            'payload_json' => ['step1' => ['boatHint' => 'x']],
        ]);

        $response = $this->patchJson('/api/yacht-drafts/draft-conflict-001', [
            'version' => 4,
            'payload_patch' => [
                'step1' => ['boatHint' => 'changed'],
            ],
        ]);

        $response->assertStatus(409)
            ->assertJsonPath('code', 'version_conflict')
            ->assertJsonPath('server.version', 5);
    }

    public function test_user_can_attach_yacht_and_commit_draft(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $draft = YachtDraft::create([
            'user_id' => $user->id,
            'draft_id' => 'draft-attach-001',
            'version' => 1,
            'wizard_step' => 1,
        ]);

        $yacht = Yacht::create([
            'user_id' => $user->id,
            'boat_name' => 'Attach Boat',
            'status' => 'Draft',
        ]);

        $attach = $this->postJson('/api/yacht-drafts/draft-attach-001/attach-yacht', [
            'yacht_id' => $yacht->id,
            'version' => 1,
        ]);

        $attach->assertStatus(200)
            ->assertJsonPath('yacht_id', $yacht->id)
            ->assertJsonPath('version', 2);

        $commit = $this->postJson('/api/yacht-drafts/draft-attach-001/commit', [
            'version' => 2,
        ]);

        $commit->assertStatus(200)
            ->assertJsonPath('status', 'submitted')
            ->assertJsonPath('version', 3);

        $this->assertDatabaseHas('yacht_drafts', [
            'id' => $draft->id,
            'yacht_id' => $yacht->id,
            'status' => 'submitted',
            'version' => 3,
        ]);
    }

    public function test_user_cannot_attach_other_users_yacht(): void
    {
        $owner = User::factory()->create();
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $draft = YachtDraft::create([
            'user_id' => $user->id,
            'draft_id' => 'draft-attach-forbidden-001',
            'version' => 1,
            'wizard_step' => 1,
        ]);

        $foreignYacht = Yacht::create([
            'user_id' => $owner->id,
            'boat_name' => 'Foreign Boat',
            'status' => 'Draft',
        ]);

        $response = $this->postJson('/api/yacht-drafts/draft-attach-forbidden-001/attach-yacht', [
            'yacht_id' => $foreignYacht->id,
            'version' => 1,
        ]);

        $response->assertStatus(403);
        $this->assertDatabaseHas('yacht_drafts', [
            'id' => $draft->id,
            'yacht_id' => null,
            'version' => 1,
        ]);
    }
}

