<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add `offline_uuid` column to `yachts` table for idempotent offline creation.
     * When a boat is created offline, the frontend generates a UUID and sends it
     * as the X-Offline-ID header.  The backend stores it here and uses it to
     * prevent duplicate creation if the same request is replayed.
     */
    public function up(): void
    {
        Schema::table('yachts', function (Blueprint $table) {
            $table->string('offline_uuid', 36)->nullable()->unique()->after('id');
        });
    }

    public function down(): void
    {
        Schema::table('yachts', function (Blueprint $table) {
            $table->dropUnique(['offline_uuid']);
            $table->dropColumn('offline_uuid');
        });
    }
};
