<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('harbor_channels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('harbor_id')->constrained('locations')->cascadeOnDelete();
            $table->string('channel');
            $table->string('provider');
            $table->string('from_number')->nullable();
            $table->string('api_key_encrypted')->nullable();
            $table->string('webhook_token')->nullable()->index();
            $table->string('webhook_secret')->nullable();
            $table->string('status')->default('active');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['harbor_id', 'channel', 'provider']);
            $table->index(['channel', 'provider']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('harbor_channels');
    }
};
