<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('videos') || Schema::hasColumn('videos', 'source_image_ids_json')) {
            return;
        }

        Schema::table('videos', function (Blueprint $table) {
            $table->json('source_image_ids_json')->nullable()->after('template_type');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('videos') || ! Schema::hasColumn('videos', 'source_image_ids_json')) {
            return;
        }

        Schema::table('videos', function (Blueprint $table) {
            $table->dropColumn('source_image_ids_json');
        });
    }
};
