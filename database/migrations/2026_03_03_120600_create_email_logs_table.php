<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->uuid('contact_id')->nullable();
            $table->string('email_address');
            $table->foreignId('template_id')->nullable()->constrained('interaction_templates')->nullOnDelete();
            $table->unsignedInteger('template_version')->nullable();
            $table->foreignId('event_type_id')->nullable()->constrained('interaction_event_types')->nullOnDelete();
            $table->string('subject')->nullable();
            $table->string('status', 20)->default('queued');
            $table->timestamp('sent_at')->nullable();
            $table->text('error_message')->nullable();
            $table->string('provider_message_id')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index(['email_address']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_logs');
    }
};
