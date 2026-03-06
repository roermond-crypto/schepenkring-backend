<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── 1. Add role + avatar + phone to users ──────────────────
        Schema::table('users', function (Blueprint $table) {
            $table->string('role')->default('client')->after('email'); // admin | employee | client
            $table->string('phone')->nullable()->after('role');
            $table->string('avatar')->nullable()->after('phone');
            $table->boolean('is_active')->default(true)->after('avatar');
            $table->foreignId('invited_by')->nullable()->after('is_active')
                  ->constrained('users')->nullOnDelete();
            $table->timestamp('last_login_at')->nullable()->after('updated_at');
        });

        // ── 2. Audit log ───────────────────────────────────────────
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action');               // created | updated | deleted | approved | rejected | imported | ai_applied
            $table->string('auditable_type');        // App\Models\Yacht etc.
            $table->unsignedBigInteger('auditable_id');
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->text('reason')->nullable();      // optional human note
            $table->json('metadata')->nullable();    // extra context (AI prompt hash, source, etc.)
            $table->timestamps();

            $table->index(['auditable_type', 'auditable_id']);
            $table->index('action');
            $table->index('user_id');
        });

        // ── 3. App notifications ───────────────────────────────────
        Schema::create('app_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('type');                  // boat_imported | image_enhanced | ai_review_ready | etc.
            $table->string('title');
            $table->text('body')->nullable();
            $table->json('data')->nullable();        // { boat_id, image_id, link, ... }
            $table->string('severity')->default('info'); // info | warning | success | error
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'read_at']);
            $table->index('type');
        });

        // ── 4. Settings (key-value for admin config) ───────────────
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('group')->default('general'); // ai_text | tts | social | image | general
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->string('type')->default('string');   // string | json | boolean | integer
            $table->text('description')->nullable();
            $table->timestamps();
        });

        // ── 5. Processing pipeline tracker ─────────────────────────
        Schema::create('pipeline_runs', function (Blueprint $table) {
            $table->id();
            $table->string('pipeline');              // BoatProcessingPipeline
            $table->morphs('processable');           // yacht, image, etc.
            $table->string('current_step')->nullable();
            $table->string('status')->default('pending'); // pending | running | completed | failed | paused
            $table->json('steps_completed')->nullable();   // ["image_process", "ai_enrich"]
            $table->json('steps_failed')->nullable();
            $table->json('error_log')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pipeline_runs');
        Schema::dropIfExists('settings');
        Schema::dropIfExists('app_notifications');
        Schema::dropIfExists('audit_logs');

        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['invited_by']);
            $table->dropColumn(['role', 'phone', 'avatar', 'is_active', 'invited_by', 'last_login_at']);
        });
    }
};
