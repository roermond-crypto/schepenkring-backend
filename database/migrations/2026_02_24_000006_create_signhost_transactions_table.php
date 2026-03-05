<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('signhost_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('deal_id')->constrained('deals')->cascadeOnDelete();
            $table->string('signhost_transaction_id')->unique();
            $table->enum('status', ['pending', 'signing', 'signed', 'rejected', 'expired', 'cancelled'])->default('pending');
            $table->text('signing_url_buyer')->nullable();
            $table->text('signing_url_seller')->nullable();
            $table->string('signed_pdf_path')->nullable();
            $table->json('webhook_last_payload')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('signhost_transactions');
    }
};
