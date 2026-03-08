<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->string('source')->nullable()->after('location_id');
            $table->uuid('conversation_id')->nullable()->change();
            $table->string('source_url')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropColumn('source');
            $table->uuid('conversation_id')->nullable(false)->change();
            $table->string('source_url')->nullable(false)->change();
        });
    }
};
