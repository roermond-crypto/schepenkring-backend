<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attachments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('message_id')->nullable();
            $table->string('storage_key');
            $table->string('mime_type');
            $table->unsignedBigInteger('size');
            $table->string('checksum')->nullable();
            $table->text('extracted_text')->nullable();
            $table->text('ai_summary')->nullable();
            $table->timestamps();

            $table->index('message_id');
            $table->index('mime_type');
            $table->unique('storage_key');

            $table->foreign('message_id')->references('id')->on('messages')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attachments');
    }
};
