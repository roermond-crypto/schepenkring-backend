<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_events', function (Blueprint $table) {
            $table->id();
            // Random token embedded in the tracking pixel/click-redirect
            // URLs — self-hosted rather than provider-webhook-based, since
            // the production MAIL_MAILER isn't knowable from here and this
            // approach works regardless of which one is configured.
            $table->string('token')->unique();
            $table->foreignId('campaign_target_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('email_template_key')->nullable();
            $table->string('recipient_email')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('opened_at')->nullable();
            $table->unsignedInteger('open_count')->default(0);
            $table->timestamp('first_clicked_at')->nullable();
            $table->unsignedInteger('click_count')->default(0);
            $table->json('clicked_urls')->nullable();
            $table->timestamps();

            $table->index('campaign_target_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_events');
    }
};
