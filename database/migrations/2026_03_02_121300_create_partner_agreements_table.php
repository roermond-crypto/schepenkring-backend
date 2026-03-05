<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('partner_agreements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('agreement_version');
            $table->longText('agreement_text');
            $table->string('agreement_hash', 64);
            $table->timestamp('accepted_at');
            $table->string('ip_address', 64)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'agreement_version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('partner_agreements');
    }
};
