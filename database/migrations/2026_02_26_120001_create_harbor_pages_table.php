<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('harbor_pages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('harbor_id')->constrained('harbors')->cascadeOnDelete();
            $table->string('locale', 5)->default('nl');
            $table->json('page_content')->nullable();
            $table->timestamp('generated_at')->nullable();
            $table->string('source_data_hash', 64)->nullable(); // MD5 of input data to detect changes
            $table->timestamps();

            $table->unique(['harbor_id', 'locale']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('harbor_pages');
    }
};
