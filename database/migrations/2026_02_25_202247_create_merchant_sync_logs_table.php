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
        Schema::create('merchant_sync_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('yacht_id')->constrained()->cascadeOnDelete();
            $table->string('action'); // upsert, out_of_stock, delete
            $table->string('status'); // success, error
            $table->json('request_payload')->nullable();
            $table->json('response_payload')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('merchant_sync_logs');
    }
};
