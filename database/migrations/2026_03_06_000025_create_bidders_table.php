<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bidders', function (Blueprint $table) {
            $table->id();
            $table->string('full_name');
            $table->string('address');
            $table->string('postal_code', 40);
            $table->string('city');
            $table->string('phone', 50);
            $table->string('email')->unique();
            $table->timestamp('verified_at')->nullable();
            $table->string('verification_token_hash', 64)->nullable()->index();
            $table->timestamp('verification_expires_at')->nullable();
            $table->timestamp('verification_sent_at')->nullable();
            $table->string('verification_ip', 45)->nullable();
            $table->timestamps();

            $table->index('verified_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bidders');
    }
};
