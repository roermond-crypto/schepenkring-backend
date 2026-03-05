<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('harbor_widget_events', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('harbor_id');
            $table->string('event_type');
            $table->string('placement')->nullable();
            $table->string('url')->nullable();
            $table->string('referrer')->nullable();
            $table->string('device_type')->nullable();
            $table->unsignedInteger('viewport_width')->nullable();
            $table->unsignedInteger('viewport_height')->nullable();
            $table->unsignedInteger('scroll_depth')->nullable();
            $table->unsignedInteger('time_on_page_before_click')->nullable();
            $table->string('widget_version')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['harbor_id', 'event_type', 'created_at']);
            $table->foreign('harbor_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('harbor_widget_events');
    }
};
