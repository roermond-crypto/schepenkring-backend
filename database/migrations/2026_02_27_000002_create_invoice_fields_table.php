<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoice_fields', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_document_id')
                ->constrained('invoice_documents')
                ->restrictOnDelete();
            $table->json('extracted_fields')->nullable();
            $table->json('field_confidence')->nullable();
            $table->json('normalized_fields')->nullable();
            $table->json('approved_fields')->nullable();
            $table->dateTime('approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique('invoice_document_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_fields');
    }
};
