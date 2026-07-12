<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Singleton table (always exactly one row, id=1) — footer-wide
        // content that isn't a navigable link: tagline, contact details,
        // social links.
        Schema::create('site_settings', function (Blueprint $table) {
            $table->id();
            $table->json('footer_tagline')->nullable(); // {nl,en,de,fr}
            $table->string('contact_email')->nullable();
            $table->string('contact_phone')->nullable();
            $table->string('contact_address')->nullable();
            $table->json('social_links')->nullable(); // [{platform,url}]
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_settings');
    }
};
