<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('harbor_widget_settings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('harbor_id'); // Partner user id
            $table->string('domain');
            $table->string('widget_version')->nullable();
            $table->string('placement_default')->nullable(); // header/footer/popup
            $table->string('widget_selector')->nullable(); // CSS selector for screenshot checks
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->index(['harbor_id', 'domain']);
            $table->foreign('harbor_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('harbor_widget_settings');
    }
};
