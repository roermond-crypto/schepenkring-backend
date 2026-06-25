<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('email_templates')) Schema::create('email_templates', function (Blueprint $table) {
            $table->id();
            $table->string('type');            // offer_received_buyer, offer_received_seller, etc.
            $table->string('name');
            $table->string('description')->nullable();
            $table->boolean('is_global')->default(false);
            $table->foreignId('location_id')->nullable()->constrained('locations')->nullOnDelete();
            $table->unsignedBigInteger('parent_template_id')->nullable(); // which global it was copied from
            $table->string('language_default')->default('nl');
            $table->json('subject');           // {"nl":"...", "en":"...", "de":"...", "fr":"..."}
            $table->json('blocks');            // array of block objects
            $table->string('sender_name_override')->nullable();
            $table->string('sender_email_override')->nullable();
            $table->string('reply_to_override')->nullable();
            $table->string('primary_color_override')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_archived')->default(false);
            $table->unsignedBigInteger('created_by_id')->nullable();
            $table->unsignedBigInteger('current_version')->default(1);
            $table->timestamps();
            $table->index(['type', 'location_id']);
            $table->index(['is_global', 'type']);
            $table->index(['location_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_templates');
    }
};
