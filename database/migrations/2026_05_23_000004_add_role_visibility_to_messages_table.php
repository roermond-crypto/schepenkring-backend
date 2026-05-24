<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            if (!Schema::hasColumn('messages', 'role_visibility')) {
                // message_type values: chat | email | system | signhost | contract | boat | admin_note
                $table->json('role_visibility')->nullable()->after('message_type');
            }
        });
    }

    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            if (Schema::hasColumn('messages', 'role_visibility')) {
                $table->dropColumn('role_visibility');
            }
        });
    }
};
