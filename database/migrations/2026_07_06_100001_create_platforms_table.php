<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platforms', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('logo_url')->nullable();
            $table->string('website_url')->nullable();
            // openmarine | xml | api | manual
            $table->string('type')->default('openmarine');
            $table->string('export_method')->default('openmarine');
            $table->string('feed_url')->nullable();
            $table->string('api_url')->nullable();
            $table->json('credentials')->nullable();
            $table->boolean('openmarine_enabled')->default(true);
            $table->string('openmarine_dealer_id')->nullable();
            $table->string('openmarine_version')->default('2.0');
            $table->json('openmarine_category_map')->nullable();
            $table->json('supported_countries')->nullable();
            $table->json('supported_languages')->nullable();
            $table->string('contact_name')->nullable();
            $table->string('contact_email')->nullable();
            $table->text('notes')->nullable();
            $table->integer('priority')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platforms');
    }
};
