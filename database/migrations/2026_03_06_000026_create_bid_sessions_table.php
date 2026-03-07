<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bid_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bidder_id')->constrained('bidders')->onDelete('cascade');
            $table->string('token_hash', 64)->unique();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();

            $table->index(['bidder_id', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bid_sessions');
    }
};
