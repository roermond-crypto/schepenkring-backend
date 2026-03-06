<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('webhook_events', function (Blueprint $table) {
            $table->id();
            $table->string('provider');
            $table->string('event_key')->unique();
            $table->string('idempotency_key')->nullable()->index();
            $table->json('payload_json')->nullable();
            $table->timestamp('processing_at')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->index(['provider', 'idempotency_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_events');
    }
};
