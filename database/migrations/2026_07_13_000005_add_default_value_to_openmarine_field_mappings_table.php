<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('openmarine_field_mappings', function (Blueprint $table) {
            // Constant/default XML values not read from the yacht at all
            // (price.currency='EUR', price.vat='excluded') or used as a
            // fallback when the resolved yacht value is empty
            // (new_or_used='used', location.country='NL'). When
            // schepenkring_field is '' (no doctrine/dbal in this environment
            // to make the column nullable), default_value is used as-is,
            // never resolved from the yacht.
            $table->string('default_value', 200)->nullable()->after('openmarine_xml_path');
        });
    }

    public function down(): void
    {
        Schema::table('openmarine_field_mappings', function (Blueprint $table) {
            $table->dropColumn('default_value');
        });
    }
};
