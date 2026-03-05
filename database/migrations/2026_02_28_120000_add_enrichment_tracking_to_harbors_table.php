<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('harbors', function (Blueprint $table) {
            $table->string('geocode_query_hash', 64)->nullable()->after('maps_url');
            $table->timestamp('last_geocode_at')->nullable()->after('geocode_query_hash');
            $table->timestamp('last_place_photos_fetch_at')->nullable()->after('last_place_details_fetch_at');
            $table->json('third_party_enrichment')->nullable()->after('place_details_json');
            $table->timestamp('last_third_party_enrichment_at')->nullable()->after('last_place_photos_fetch_at');
        });
    }

    public function down(): void
    {
        Schema::table('harbors', function (Blueprint $table) {
            $table->dropColumn([
                'geocode_query_hash',
                'last_geocode_at',
                'last_place_photos_fetch_at',
                'third_party_enrichment',
                'last_third_party_enrichment_at',
            ]);
        });
    }
};
