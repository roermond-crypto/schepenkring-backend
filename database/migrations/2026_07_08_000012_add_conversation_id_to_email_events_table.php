<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('email_events', function (Blueprint $table) {
            // Lets a campaign email's send/open/click events post into the
            // same Chat Hub thread as the target's calls (spec §10: "One
            // thread must be able to contain: Email sent, Email opened, CTA
            // clicked, Outbound call started...").
            $table->uuid('conversation_id')->nullable()->after('campaign_target_id');
        });
    }

    public function down(): void
    {
        Schema::table('email_events', function (Blueprint $table) {
            $table->dropColumn('conversation_id');
        });
    }
};
