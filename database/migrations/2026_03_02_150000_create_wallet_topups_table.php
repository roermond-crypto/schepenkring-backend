<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wallet_topups', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('mollie_payment_id')->unique()->nullable();
            $table->string('idempotency_key')->unique()->nullable();
            $table->string('amount_currency', 3)->default('EUR');
            $table->decimal('amount_value', 10, 2);
            $table->enum('status', [
                'open', 'pending', 'paid', 'failed', 'canceled', 'expired', 'refunded', 'chargeback'
            ])->default('open');
            $table->text('checkout_url')->nullable();
            $table->unsignedInteger('webhook_events_count')->default(0);
            $table->timestamps();

            $table->index('user_id');
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wallet_topups');
    }
};
