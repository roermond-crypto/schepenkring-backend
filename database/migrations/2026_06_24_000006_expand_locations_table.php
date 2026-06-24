<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('locations', function (Blueprint $table) {
            // Public identity
            $table->string('slug')->nullable()->unique()->after('code');
            $table->boolean('public_visible')->default(false)->after('status');
            $table->string('location_color', 20)->nullable()->after('public_visible');

            // Rich content
            $table->string('hero_image')->nullable()->after('location_color');
            $table->text('description_nl')->nullable()->after('hero_image');
            $table->text('description_en')->nullable()->after('description_nl');
            $table->text('description_de')->nullable()->after('description_en');

            // Opening hours (JSON): { mon: { open: "09:00", close: "17:00", closed: false }, ... }
            $table->json('opening_hours')->nullable()->after('description_de');

            // Default salesperson
            $table->unsignedBigInteger('default_seller_id')->nullable()->after('opening_hours');
            $table->foreign('default_seller_id')->references('id')->on('users')->nullOnDelete();

            // SEO
            $table->string('seo_title')->nullable()->after('default_seller_id');
            $table->text('seo_description')->nullable()->after('seo_title');
            $table->string('seo_keywords')->nullable()->after('seo_description');
        });
    }

    public function down(): void
    {
        Schema::table('locations', function (Blueprint $table) {
            $table->dropForeign(['default_seller_id']);
            $table->dropColumn([
                'slug', 'public_visible', 'location_color', 'hero_image',
                'description_nl', 'description_en', 'description_de',
                'opening_hours', 'default_seller_id',
                'seo_title', 'seo_description', 'seo_keywords',
            ]);
        });
    }
};
