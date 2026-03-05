<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('interaction_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_type_id')->nullable()->constrained('interaction_event_types')->nullOnDelete();
            $table->string('channel', 30);
            $table->string('name');
            $table->string('subject')->nullable();
            $table->longText('body');
            $table->unsignedInteger('version')->default(1);
            $table->boolean('is_active')->default(true);
            $table->json('placeholders')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['event_type_id', 'channel', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('interaction_templates');
    }
};
