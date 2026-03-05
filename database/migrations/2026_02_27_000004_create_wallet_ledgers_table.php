<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wallet_ledgers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->enum('type', [
                'COMMISSION_PENDING',
                'COMMISSION_REALIZED',
                'HARBOR_SPLIT',
                'LISTING_FEE',
                'REFUND',
                'PAYOUT',
                'CORRECTION',
                'LOCKED',
            ])->index();
            $table->decimal('amount', 12, 2);
            $table->string('currency', 3)->default('EUR');
            $table->string('reference_type', 64)->nullable()->index();
            $table->unsignedBigInteger('reference_id')->nullable()->index();
            $table->string('reference_key', 128)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(
                ['user_id', 'type', 'reference_type', 'reference_id', 'reference_key'],
                'wallet_ledgers_unique_ref'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wallet_ledgers');
    }
};
