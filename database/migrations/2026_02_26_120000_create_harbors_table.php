<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('harbors', function (Blueprint $table) {
            $table->id();

            // HISWA source
            $table->string('hiswa_company_id')->unique()->nullable();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();

            // Address
            $table->string('street_address')->nullable();
            $table->string('postal_code', 10)->nullable();
            $table->string('city')->nullable();
            $table->string('province')->nullable();
            $table->string('country', 5)->default('NL');

            // Contact
            $table->string('email')->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('website')->nullable();

            // Meta
            $table->json('facilities')->nullable();   // ["fuel","wifi","crane",...]
            $table->json('tags')->nullable();
            $table->string('source_url')->nullable();
            $table->timestamp('first_seen_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();

            // Google Geocoding
            $table->string('gmaps_place_id')->nullable()->index();
            $table->string('gmaps_formatted_address')->nullable();
            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lng', 10, 7)->nullable();
            $table->json('address_components')->nullable();
            $table->enum('geocode_confidence', ['HIGH', 'MED', 'LOW'])->nullable();
            $table->string('maps_url')->nullable();

            // Google Place Details
            $table->json('opening_hours_json')->nullable();
            $table->decimal('rating', 2, 1)->nullable();
            $table->unsignedInteger('rating_count')->nullable();
            $table->string('primary_phone', 30)->nullable();
            $table->string('google_website')->nullable();
            $table->json('google_photos')->nullable();
            $table->json('place_details_json')->nullable();
            $table->timestamp('last_place_details_fetch_at')->nullable();

            // Status & management
            $table->boolean('needs_review')->default(false);
            $table->boolean('is_published')->default(false);
            $table->foreignId('claimed_by_user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            // Indexes for search
            $table->index(['city', 'is_published']);
            $table->index('postal_code');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('harbors');
    }
};
