<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('yacht_channel_listings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('yacht_id')->index();
            $table->string('channel_name', 64); // e.g. "marktplaats"
            $table->boolean('is_enabled')->default(false);
            $table->boolean('auto_publish')->default(false);
            $table->string('status', 64)->nullable(); // active, paused, error, pending
            $table->json('settings_json')->nullable();
            $table->json('capabilities')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();

            $table->unique(['yacht_id', 'channel_name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('yacht_channel_listings');
    }
};
