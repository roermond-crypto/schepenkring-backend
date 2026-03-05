<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoice_documents', function (Blueprint $table) {
            $table->id();
            $table->enum('type', ['incoming', 'outgoing'])->index();
            $table->enum('status', ['received', 'processing', 'approved', 'void', 'archived', 'credited'])
                ->default('received')
                ->index();
            $table->string('storage_disk')->default('local');
            $table->string('storage_path')->unique();
            $table->string('source_filename');
            $table->string('file_hash', 64);
            $table->string('hash_algo', 20)->default('sha256');
            $table->unsignedBigInteger('file_size');
            $table->string('mime_type', 64);
            $table->date('retention_until')->index();
            $table->longText('raw_text')->nullable();
            $table->json('ocr_json')->nullable();
            $table->json('metadata')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_documents');
    }
};
