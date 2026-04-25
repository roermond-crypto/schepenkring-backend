<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('boat_channel_listings')) {
            Schema::create('boat_channel_listings', function (Blueprint $table) {
                $table->id();
                $table->foreignId('boat_id')->constrained('yachts')->cascadeOnDelete();
                $table->string('channel_name', 50);
                $table->boolean('is_enabled')->default(false);
                $table->boolean('auto_publish')->default(false);
                $table->string('external_id')->nullable();
                $table->string('external_url')->nullable();
                $table->string('status', 50)->default('draft');
                $table->string('payload_hash', 64)->nullable();
                $table->json('settings_json')->nullable();
                $table->json('last_request_payload_json')->nullable();
                $table->json('last_response_payload_json')->nullable();
                $table->json('last_validation_errors_json')->nullable();
                $table->timestamp('published_at')->nullable();
                $table->timestamp('last_sync_at')->nullable();
                $table->timestamp('last_success_at')->nullable();
                $table->timestamp('last_error_at')->nullable();
                $table->text('last_error_message')->nullable();
                $table->timestamp('removed_at')->nullable();
                $table->timestamps();

                $table->unique(['boat_id', 'channel_name']);
                $table->index(['channel_name', 'status']);
                $table->index(['channel_name', 'external_id']);
            });
        }

        if (! Schema::hasTable('boat_channel_logs')) {
            Schema::create('boat_channel_logs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('boat_id')->constrained('yachts')->cascadeOnDelete();
                $table->foreignId('boat_channel_listing_id')->nullable()->constrained('boat_channel_listings')->nullOnDelete();
                $table->string('channel_name', 50);
                $table->string('action', 50);
                $table->string('status', 50);
                $table->json('request_payload_json')->nullable();
                $table->json('response_payload_json')->nullable();
                $table->text('error_message')->nullable();
                $table->timestamps();

                $table->index(['boat_id', 'channel_name', 'created_at']);
                $table->index(['boat_channel_listing_id', 'created_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('boat_channel_logs');
        Schema::dropIfExists('boat_channel_listings');
    }
};
