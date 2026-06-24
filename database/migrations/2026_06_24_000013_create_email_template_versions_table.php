<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_template_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('email_template_id')->constrained('email_templates')->cascadeOnDelete();
            $table->unsignedBigInteger('version');
            $table->json('subject');
            $table->json('blocks');
            $table->string('change_note')->nullable();
            $table->unsignedBigInteger('created_by_id')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['email_template_id', 'version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_template_versions');
    }
};
