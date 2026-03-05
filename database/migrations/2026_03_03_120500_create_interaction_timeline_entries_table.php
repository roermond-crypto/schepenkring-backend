<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('interaction_timeline_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->uuid('contact_id')->nullable();
            $table->uuid('conversation_id')->nullable();
            $table->foreignId('event_type_id')->nullable()->constrained('interaction_event_types')->nullOnDelete();
            $table->string('channel', 30);
            $table->string('direction', 20)->default('system');
            $table->string('title')->nullable();
            $table->longText('body')->nullable();
            $table->json('metadata')->nullable();
            $table->foreignId('template_id')->nullable()->constrained('interaction_templates')->nullOnDelete();
            $table->unsignedInteger('template_version')->nullable();
            $table->string('source_type')->nullable();
            $table->string('source_id')->nullable();
            $table->timestamp('occurred_at')->useCurrent();
            $table->timestamps();

            $table->index(['user_id', 'occurred_at']);
            $table->index(['contact_id', 'occurred_at']);
            $table->index(['conversation_id', 'occurred_at']);
            $table->index(['channel', 'direction']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('interaction_timeline_entries');
    }
};
