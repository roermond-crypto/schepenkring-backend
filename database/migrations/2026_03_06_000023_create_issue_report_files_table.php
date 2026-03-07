<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('issue_report_files', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('issue_report_id');
            $table->string('storage_disk', 50)->default('public');
            $table->string('storage_path');
            $table->string('original_name')->nullable();
            $table->string('mime_type', 100)->nullable();
            $table->unsignedBigInteger('size')->nullable();
            $table->timestamps();

            $table->index('issue_report_id');
            $table->foreign('issue_report_id')->references('id')->on('issue_reports')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('issue_report_files');
    }
};
