<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('harbor_widget_daily_snapshots', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('harbor_id');
            $table->string('domain');
            $table->string('desktop_screenshot_path')->nullable();
            $table->string('mobile_screenshot_path')->nullable();
            $table->boolean('widget_found')->default(false);
            $table->boolean('widget_visible')->default(false);
            $table->boolean('widget_clickable')->default(false);
            $table->json('console_errors')->nullable();
            $table->unsignedInteger('load_time_ms')->nullable();
            $table->timestamp('checked_at')->nullable();
            $table->timestamps();

            $table->index(['harbor_id', 'checked_at']);
            $table->foreign('harbor_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('harbor_widget_daily_snapshots');
    }
};
