<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('yachts', 'short_description_fr')) {
            Schema::table('yachts', function (Blueprint $table) {
                $table->text('short_description_fr')->nullable()->after('short_description_de');
            });
        }

        if (! Schema::hasColumn('platform_errors', 'ai_user_message_fr')) {
            Schema::table('platform_errors', function (Blueprint $table) {
                $table->text('ai_user_message_fr')->nullable()->after('ai_user_message_de');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('yachts', 'short_description_fr')) {
            Schema::table('yachts', function (Blueprint $table) {
                $table->dropColumn('short_description_fr');
            });
        }

        if (Schema::hasColumn('platform_errors', 'ai_user_message_fr')) {
            Schema::table('platform_errors', function (Blueprint $table) {
                $table->dropColumn('ai_user_message_fr');
            });
        }
    }
};
