<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conversations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->unsignedBigInteger('harbor_id')->default(1);
            $table->unsignedBigInteger('boat_id')->nullable();
            $table->uuid('contact_id')->nullable();
            $table->string('visitor_id')->nullable();
            $table->string('status')->default('open');
            $table->string('priority')->default('normal');
            $table->string('channel_origin')->default('web_widget');
            $table->string('ai_mode')->default('auto');
            $table->string('language_preferred', 5)->nullable();
            $table->string('language_detected', 5)->nullable();
            $table->text('page_url')->nullable();
            $table->string('utm_source')->nullable();
            $table->string('utm_medium')->nullable();
            $table->string('utm_campaign')->nullable();
            $table->string('ref_code')->nullable();
            $table->timestamp('last_message_at')->nullable();
            $table->timestamp('last_customer_message_at')->nullable();
            $table->timestamp('last_staff_message_at')->nullable();
            $table->timestamp('first_response_due_at')->nullable();
            $table->unsignedBigInteger('assigned_to')->nullable();
            $table->timestamps();

            $table->index(['harbor_id', 'status']);
            $table->index('last_message_at');
            $table->index('visitor_id');
            $table->index('contact_id');
            $table->index('assigned_to');

            $table->foreign('harbor_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('boat_id')->references('id')->on('yachts')->nullOnDelete();
            $table->foreign('contact_id')->references('id')->on('contacts')->nullOnDelete();
            $table->foreign('assigned_to')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conversations');
    }
};
