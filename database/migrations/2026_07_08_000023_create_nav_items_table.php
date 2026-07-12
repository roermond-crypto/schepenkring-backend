<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nav_items', function (Blueprint $table) {
            $table->id();
            $table->string('location'); // header | footer
            // Groups footer links into columns (e.g. 'company', 'support',
            // 'legal') — meaningless when location='header'.
            $table->string('footer_column')->nullable();
            $table->foreignId('parent_id')->nullable()->constrained('nav_items')->cascadeOnDelete();
            $table->json('label'); // {nl,en,de,fr}
            $table->string('url');
            $table->integer('sort_order')->default(0);
            $table->boolean('is_visible')->default(true);
            $table->boolean('open_in_new_tab')->default(false);
            $table->string('required_role')->nullable();
            $table->timestamps();

            $table->index(['location', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nav_items');
    }
};
