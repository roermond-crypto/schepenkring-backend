<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contract_parties', function (Blueprint $table) {
            $table->id();
            $table->string('role_type', 20);
            $table->string('name');
            $table->string('company_name')->nullable();
            $table->string('address')->nullable();
            $table->string('postal_code', 50)->nullable();
            $table->string('city', 120)->nullable();
            $table->string('phone', 80)->nullable();
            $table->string('email')->nullable();
            $table->string('passport_number', 120)->nullable();
            $table->string('partner_name')->nullable();
            $table->boolean('married')->default(false);
            $table->foreignId('location_id')->nullable()->constrained('locations')->nullOnDelete();
            $table->timestamps();

            $table->index('role_type');
            $table->index(['role_type', 'location_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contract_parties');
    }
};
