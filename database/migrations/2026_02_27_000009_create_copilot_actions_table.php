<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('copilot_actions', function (Blueprint $table) {
            $table->id();
            $table->string('action_id')->unique();
            $table->string('title');
            $table->string('module')->nullable();
            $table->text('description')->nullable();
            $table->string('route_template');
            $table->string('query_template')->nullable();
            $table->json('required_params')->nullable();
            $table->string('permission_key')->nullable();
            $table->enum('risk_level', ['low', 'medium', 'high'])->default('low');
            $table->boolean('confirmation_required')->default(false);
            $table->boolean('enabled')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('copilot_actions');
    }
};
