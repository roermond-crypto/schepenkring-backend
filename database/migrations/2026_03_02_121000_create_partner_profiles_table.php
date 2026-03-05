<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('partner_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('place_id')->unique();
            $table->string('company_name');
            $table->string('street')->nullable();
            $table->string('postal_code')->nullable();
            $table->string('city')->nullable();
            $table->string('country')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('agreement_version')->nullable();
            $table->timestamp('agreement_accepted_at')->nullable();
            $table->timestamp('contract_signed_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'place_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('partner_profiles');
    }
};
