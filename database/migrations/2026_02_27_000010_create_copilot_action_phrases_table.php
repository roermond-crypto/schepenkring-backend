<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('copilot_action_phrases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('copilot_action_id')->constrained('copilot_actions')->cascadeOnDelete();
            $table->string('phrase');
            $table->string('language', 5)->nullable();
            $table->unsignedInteger('priority')->default(0);
            $table->boolean('enabled')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('copilot_action_phrases');
    }
};
