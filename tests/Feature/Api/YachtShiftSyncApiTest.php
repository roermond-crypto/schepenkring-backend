<?php

namespace Tests\Feature\Api;

use App\Enums\UserStatus;
use App\Enums\UserType;
use App\Models\AuditLog;
use App\Models\BoatField;
use App\Models\BoatFieldChange;
use App\Models\Location;
use App\Models\User;
use App\Models\Yacht;
use App\Models\YachtshiftSyncConflict;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class YachtShiftSyncApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.yachtshift.api_url' => 'https://ys.test/v1',
            'services.yachtshift.api_key' => 'test-token',
            'services.yachtshift.guard_empty_imports' => true,
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_import_aborts_on_empty_yachtshift_response_and_audits_failure(): void
    {
        Http::fake([
            'https://ys.test/v1/listings' => Http::response(['data' => []], 200),
        ]);

        Sanctum::actingAs($this->admin());

        $this->postJson('/api/admin/yachtshift/sync', [
            'direction' => 'import',
        ])
            ->assertStatus(502)
            ->assertJsonPath('message', 'YachtShift returned an empty listing response; import aborted.');

        $this->assertSame(0, Yacht::query()->count());
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'yachtshift.import.empty_response',
            'result' => 'FAIL',
        ]);
    }

    public function test_import_creates_conflict_and_admin_can_resolve_remote_value(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-05-25 10:00:00'));

        $location = Location::create([
            'name' => 'Sync Harbor',
            'code' => 'SH',
            'status' => 'ACTIVE',
        ]);

        $seller = User::factory()->create([
            'type' => UserType::SELLER,
            'status' => UserStatus::ACTIVE,
            'client_location_id' => $location->id,
        ]);

        $yacht = $this->createYacht($seller, $location, [
            'yachtshift_id' => 'YS-1',
            'boat_name' => 'Local Name',
            'price' => 100000,
            'updated_at' => now(),
        ]);

        Http::fake([
            'https://ys.test/v1/listings' => Http::response([
                'data' => [[
                    'id' => 'YS-1',
                    'name' => 'Remote Name',
                    'brand' => 'Contest',
                    'model' => '38',
                    'price' => 110000,
                    'updated_at' => now()->subDay()->toIso8601String(),
                ]],
            ], 200),
        ]);

        Sanctum::actingAs($this->admin());

        $this->postJson('/api/admin/yachtshift/sync', [
            'direction' => 'import',
        ])
            ->assertOk()
            ->assertJsonPath('import.conflicts', 2)
            ->assertJsonPath('import.skipped', 1);

        $this->assertSame(100000.0, (float) $yacht->fresh()->price);
        $priceConflict = YachtshiftSyncConflict::query()
            ->where('field_name', 'price')
            ->firstOrFail();

        $this->postJson("/api/admin/yachtshift/conflicts/{$priceConflict->id}/resolve", [
            'resolution' => 'remote',
        ])
            ->assertOk()
            ->assertJsonPath('conflict.status', 'resolved')
            ->assertJsonPath('conflict.resolution', 'remote');

        $this->assertSame(110000.0, (float) $yacht->fresh()->price);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'yachtshift.conflict.resolved',
            'target_id' => $yacht->id,
        ]);

        $this->getJson('/api/admin/boat-audit?direction=import&price_changes=1')
            ->assertOk()
            ->assertJsonPath('data.0.field_name', 'price');
    }

    public function test_publish_retry_exports_boat_and_respects_never_export_field_rules(): void
    {
        $location = Location::create([
            'name' => 'Publish Harbor',
            'code' => 'PH',
            'status' => 'ACTIVE',
        ]);

        $seller = User::factory()->create([
            'type' => UserType::SELLER,
            'status' => UserStatus::ACTIVE,
            'client_location_id' => $location->id,
        ]);

        BoatField::create([
            'internal_key' => 'price',
            'labels_json' => ['en' => 'Price'],
            'field_type' => 'number',
            'block_key' => 'pricing',
            'step_key' => 'basics',
            'storage_column' => 'price',
            'can_import' => true,
            'can_export' => true,
            'never_export' => true,
        ]);

        $yacht = $this->createYacht($seller, $location, [
            'boat_name' => 'Publish Yacht',
            'manufacturer' => 'Linssen',
            'model' => 'Grand Sturdy',
            'price' => 250000,
            'yachtshift_publish_status' => 'draft',
        ]);

        Http::fake([
            'https://ys.test/v1/listings' => Http::sequence()
                ->push(['message' => 'bad payload'], 422)
                ->push(['data' => ['id' => 'YS-2']], 200),
        ]);

        Sanctum::actingAs($this->admin());

        $this->postJson("/api/admin/yachts/{$yacht->id}/publish-yachtshift")
            ->assertStatus(502)
            ->assertJsonPath('success', false);

        $this->assertSame('failed', $yacht->fresh()->yachtshift_publish_status);
        $this->assertSame('bad payload', $yacht->fresh()->yachtshift_last_export_error);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'yachtshift.export.failed',
            'result' => 'FAIL',
            'target_id' => $yacht->id,
        ]);

        $this->postJson("/api/admin/yachts/{$yacht->id}/retry-yachtshift-export")
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('external_id', 'YS-2');

        $fresh = $yacht->fresh();
        $this->assertSame('published', $fresh->yachtshift_publish_status);
        $this->assertSame('YS-2', $fresh->yachtshift_id);
        $this->assertNull($fresh->yachtshift_last_export_error);
        $this->assertNotNull($fresh->yachtshift_sync_summary);

        Http::assertSentCount(2);
        Http::assertSent(function ($request) {
            return $request->url() === 'https://ys.test/v1/listings'
                && ! array_key_exists('price', $request->data());
        });

        $this->assertGreaterThanOrEqual(1, BoatFieldChange::query()
            ->where('field_name', 'yachtshift_publish_status')
            ->where('source_type', 'yachtshift')
            ->count());
    }

    private function admin(): User
    {
        return User::factory()->create([
            'type' => UserType::ADMIN,
            'status' => UserStatus::ACTIVE,
        ]);
    }

    private function createYacht(User $seller, Location $location, array $overrides = []): Yacht
    {
        return Yacht::create(array_merge([
            'user_id' => $seller->id,
            'location_id' => $location->id,
            'ref_harbor_id' => $location->id,
            'vessel_id' => 'YS-TST-' . Str::upper(Str::random(8)),
            'boat_name' => 'Sync Yacht',
            'manufacturer' => 'Contest',
            'model' => '38',
            'status' => 'For Sale',
            'price' => 100000,
        ], $overrides));
    }
}
