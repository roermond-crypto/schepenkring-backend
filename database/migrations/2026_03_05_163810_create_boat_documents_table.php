<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('boat_documents', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('boat_id')->index();
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('set null');
            $table->string('file_path');
            $table->string('file_type')->nullable(); // pdf, docx, etc
            $table->string('document_type')->nullable(); // invoice, tax, registration
            $table->timestamp('uploaded_at')->useCurrent();
            $table->boolean('verified')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('boat_documents');
    }
};
