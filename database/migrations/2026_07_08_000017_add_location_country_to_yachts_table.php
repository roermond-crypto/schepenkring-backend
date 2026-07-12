<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('yachts', function (Blueprint $table) {
            // Previously read by OpenMarineService::buildXml() as
            // $yacht->location_country but the column never existed — every
            // export silently fell back to the hardcoded 'NL' default
            // regardless of the yacht's actual location.
            $table->string('location_country', 120)->nullable()->after('location_city');
        });
    }

    public function down(): void
    {
        Schema::table('yachts', function (Blueprint $table) {
            $table->dropColumn('location_country');
        });
    }
};
