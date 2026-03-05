<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('deals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seller_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('buyer_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('boat_id')->constrained('yachts')->cascadeOnDelete();
            $table->enum('status', [
                'draft',
                'offer_made',
                'contract_prepared',
                'signhost_transaction_created',
                'signing_in_progress',
                'contract_signed',
                'payment_deposit_created',
                'deposit_paid',
                'platform_fee_paid',
                'completed',
                'cancelled',
                'expired',
            ])->default('draft');
            $table->string('contract_pdf_path')->nullable();
            $table->string('contract_sha256')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('deals');
    }
};
