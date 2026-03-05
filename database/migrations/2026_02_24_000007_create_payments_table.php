<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('deal_id')->constrained('deals')->cascadeOnDelete();
            $table->enum('type', ['deposit', 'platform_fee', 'remaining'])->index();
            $table->string('mollie_payment_id')->unique()->nullable();
            $table->string('amount_currency', 3)->default('EUR');
            $table->decimal('amount_value', 10, 2);
            $table->enum('status', [
                'open', 'pending', 'paid', 'failed', 'canceled', 'expired', 'refunded', 'chargeback'
            ])->default('open');
            $table->text('checkout_url')->nullable();
            $table->unsignedInteger('webhook_events_count')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
