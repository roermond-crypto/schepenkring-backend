<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('action_audits', function (Blueprint $table) {
            $table->id();
            $table->string('action_key');
            $table->string('risk_level')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('entity_type')->nullable();
            $table->unsignedBigInteger('entity_id')->nullable();
            $table->string('device_id')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('ip_country', 2)->nullable();
            $table->text('user_agent')->nullable();
            $table->string('request_id')->nullable();
            $table->string('request_method', 10);
            $table->string('request_path', 255);
            $table->string('request_hash', 64);
            $table->json('old_state')->nullable();
            $table->json('new_state')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['action_key', 'created_at']);
            $table->index(['entity_type', 'entity_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('action_audits');
    }
};
