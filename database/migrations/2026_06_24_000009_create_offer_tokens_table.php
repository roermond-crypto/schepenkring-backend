<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('offer_tokens', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('offer_id');
            $table->foreign('offer_id')->references('id')->on('offers')->cascadeOnDelete();

            // Signed token (64 chars, single-use)
            $table->string('token', 64)->unique();

            // Action this token performs: accept | reject | counter
            $table->string('action', 20);

            // For counter action: amount entered by seller
            $table->decimal('counter_amount', 12, 2)->nullable();
            $table->text('counter_message')->nullable();

            $table->boolean('used')->default(false);
            $table->timestamp('used_at')->nullable();
            $table->timestamp('expires_at');

            $table->string('ip_address', 45)->nullable();
            $table->timestamps();

            $table->index(['token', 'used']);
            $table->index(['offer_id', 'action']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('offer_tokens');
    }
};
