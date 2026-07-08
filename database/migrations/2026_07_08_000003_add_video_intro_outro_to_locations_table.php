<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('locations', function (Blueprint $table) {
            // A still image or short video clip (mp4/mov/webm) spliced into
            // every yacht video generated for boats at this location, so each
            // location's videos open/close with a distinct, recognizable
            // identity rather than a generic template.
            $table->string('video_intro_media')->nullable()->after('hero_image');
            $table->string('video_outro_media')->nullable()->after('video_intro_media');
        });
    }

    public function down(): void
    {
        Schema::table('locations', function (Blueprint $table) {
            $table->dropColumn(['video_intro_media', 'video_outro_media']);
        });
    }
};
