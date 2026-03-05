<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('harbor_widget_weekly_metrics', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('harbor_id');
            $table->date('week_start');
            $table->unsignedInteger('impressions')->default(0);
            $table->decimal('visible_rate', 5, 2)->default(0);
            $table->unsignedInteger('clicks')->default(0);
            $table->decimal('ctr', 5, 2)->default(0);
            $table->decimal('mobile_ctr', 5, 2)->default(0);
            $table->decimal('desktop_ctr', 5, 2)->default(0);
            $table->unsignedInteger('avg_scroll_before_click')->default(0);
            $table->unsignedInteger('avg_time_before_click')->default(0);
            $table->unsignedInteger('error_count')->default(0);
            $table->unsignedInteger('reliability_score')->default(0);
            $table->unsignedInteger('conversion_score')->default(0);
            $table->timestamp('computed_at')->nullable();
            $table->timestamps();

            $table->unique(['harbor_id', 'week_start']);
            $table->index(['harbor_id', 'week_start']);
            $table->foreign('harbor_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('harbor_widget_weekly_metrics');
    }
};
