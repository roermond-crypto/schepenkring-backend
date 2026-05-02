<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('renders', function (Blueprint $table) {
            $table->id();
            $table->morphs('renderable');
            $table->string('language_code', 10)->nullable();
            $table->string('format')->default('horizontal');
            $table->enum('status', ['queued', 'processing', 'done', 'failed'])->default('queued');
            $table->string('output_url')->nullable();
            $table->integer('duration_seconds')->nullable();
            $table->string('error_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('triggered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('renders');
    }
};
