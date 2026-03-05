<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('partner_contracts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('signhost_transaction_id')->unique();
            $table->enum('status', ['pending', 'signing', 'signed', 'rejected', 'expired', 'cancelled'])->default('pending');
            $table->string('contract_pdf_path')->nullable();
            $table->string('contract_sha256')->nullable();
            $table->text('signed_document_url')->nullable();
            $table->text('audit_trail_url')->nullable();
            $table->timestamp('signed_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('partner_contracts');
    }
};
