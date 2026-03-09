<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('locations', function (Blueprint $blueprint) {
            $blueprint->boolean('chat_widget_enabled')->default(true);
            $blueprint->text('chat_widget_welcome_text')->nullable();
            $blueprint->string('chat_widget_theme')->default('ocean');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('locations', function (Blueprint $blueprint) {
            $blueprint->dropColumn(['chat_widget_enabled', 'chat_widget_welcome_text', 'chat_widget_theme']);
        });
    }
};
