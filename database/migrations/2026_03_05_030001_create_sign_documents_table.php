<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sign_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sign_request_id')->constrained('sign_requests')->cascadeOnDelete();
            $table->string('file_path');
            $table->string('sha256', 64);
            $table->string('type');
            $table->timestamps();

            $table->index(['sign_request_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sign_documents');
    }
};
